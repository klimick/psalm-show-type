<?php

declare(strict_types=1);

namespace Klimick\PsalmShowType;

use Psalm\Type;
use Psalm\Type\Union;
use Psalm\Type\Atomic;
use Psalm\Codebase;
use Psalm\Storage\ClassLikeStorage;

final class ShowTypePrettier
{
    public static function pretty(Union $union, Codebase $codebase): string
    {
        return "\n" . self::union($union, $codebase) . "\n";
    }

    private static function union(Union $union, Codebase $codebase, int $level = 1): string
    {
        return implode(' | ', array_map(
            fn($atomic) => self::atomic($atomic, $codebase, $level),
            $union->getAtomicTypes(),
        ));
    }

    private static function atomic(Atomic $atomic, Codebase $codebase, int $level): string
    {
        return match (true) {
            $atomic instanceof Atomic\TList => self::list($atomic, $codebase, $level),
            $atomic instanceof Atomic\TArray => self::array($atomic, $codebase, $level),
            $atomic instanceof Atomic\TIterable => self::iterable($atomic, $codebase, $level),
            $atomic instanceof Atomic\TClosure => self::callable($atomic, $codebase, $level),
            $atomic instanceof Atomic\TCallable => self::callable($atomic, $codebase, $level),
            $atomic instanceof Atomic\TClassString => self::classString($atomic, $codebase, $level),
            $atomic instanceof Atomic\TNamedObject => self::namedObject($atomic, $codebase, $level),
            $atomic instanceof Atomic\TKeyedArray => self::keyedArray($atomic, $codebase, $level),
            $atomic instanceof Atomic\TTemplateParam => self::templateParam($atomic, $codebase, $level),
            default => $atomic->getId(),
        };
    }

    private static function iterable(Atomic\TIterable $atomic, Codebase $codebase, int $level): string
    {
        $key = self::union($atomic->type_params[0], $codebase, $level);
        $val = self::union($atomic->type_params[1], $codebase, $level);

        return "iterable<TKey: {$key}, TValue: $val>";
    }

    private static function classString(Atomic\TClassString $atomic, Codebase $codebase, int $level): string
    {
        return null !== $atomic->as_type
            ? 'class-string<' . self::namedObject($atomic->as_type, $codebase, $level) . '>'
            : 'class-string';
    }

    private static function templateParam(Atomic\TTemplateParam $atomic, Codebase $codebase, int $level): string
    {
        $shortClassName = self::shortClassName($atomic->defining_class);
        $as = self::union($atomic->as, $codebase, $level);

        return "from {$shortClassName} as {$as}";
    }

    private static function array(Atomic\TArray $atomic, Codebase $codebase, int $level): string
    {
        $key = self::union($atomic->type_params[0], $codebase, $level);
        $val = self::union($atomic->type_params[1], $codebase, $level);

        return $atomic instanceof Atomic\TNonEmptyArray
            ? "non-empty-array<TKey: {$key}, TValue: {$val}>"
            : "array<TKey: {$key}, TValue: $val>";
    }

    private static function list(Atomic\TList $atomic, Codebase $codebase, int $level): string
    {
        $type = self::union($atomic->type_param, $codebase, $level);

        return $atomic instanceof Atomic\TNonEmptyList
            ? "non-empty-list<TValue: {$type}>"
            : "list<TValue: {$type}>";
    }

    private static function callable(Atomic\TClosure|Atomic\TCallable $atomic, Codebase $codebase, int $level): string
    {
        $return = self::union($atomic->return_type ?? Type::getVoid(), $codebase, $level);

        $params = implode(', ', array_map(
            function($param) use ($codebase, $level) {
                $paramType = $param->type ?? Type::getMixed();
                $paramName = $param->by_ref ? "&\${$param->name}" : "\${$param->name}";
                $variadic = $param->is_variadic ? '...' : '';

                return trim($variadic . self::union($paramType, $codebase, $level) . " {$paramName}");
            },
            $atomic->params ?? [],
        ));

        $pure = $atomic->is_pure ? 'pure-' : '';

        return $atomic instanceof Atomic\TClosure
            ? "{$pure}Closure({$params}): {$return}"
            : "{$pure}callable({$params}): {$return}";
    }

    private static function getGenerics(Codebase $codebase, ClassLikeStorage $class, Atomic\TGenericObject $atomic, int $level): string
    {
        if (null !== $class->template_types) {
            $paramNames = array_keys($class->template_types);

            return implode(', ', array_map(
                fn(int $key, Union $param) => "{$paramNames[$key]}: " . self::union($param, $codebase, $level),
                array_keys($atomic->type_params),
                array_values($atomic->type_params),
            ));
        }

        return implode(', ', array_map(
            fn(Union $param) => self::union($param, $codebase, $level),
            $atomic->type_params,
        ));
    }

    private static function shortClassName(string $class): string
    {
        if (1 === preg_match('~(\\\\)?(?<short_class_name>\w+)$~', $class, $m)) {
            return $m['short_class_name'];
        }

        return $class;
    }

    private static function namedObject(Atomic\TNamedObject $atomic, Codebase $codebase, int $level): string
    {
        /** @psalm-suppress InternalMethod */
        $classStorage = $codebase->classlike_storage_provider->get($atomic->value);

        $generics = $atomic instanceof Atomic\TGenericObject
            ? self::getGenerics($codebase, $classStorage, $atomic, $level)
            : null;

        $shortClassName = self::shortClassName($classStorage->name);
        $mainSide = null !== $generics ? "{$shortClassName}<{$generics}>" : $shortClassName;

        $intersectionTypes = $atomic->getIntersectionTypes();

        $intersectionSide = null !== $intersectionTypes
            ? implode(', ', array_map(fn(Atomic $a) => self::atomic($a, $codebase, $level), $intersectionTypes))
            : null;

        return null !== $intersectionSide ? "{$mainSide} & {$intersectionSide}" : $mainSide;
    }

    private static function keyedArray(Atomic\TKeyedArray $atomic, Codebase $codebase, int $level): string
    {
        $tab = fn(int $l): string => str_repeat("\t", $l);

        $openBracket = 'array{';
        $closeBracket = $level === 1 ? '}' : $tab($level - 1) . '}';

        $shape = $atomic->is_list
            ? array_map(
                fn(Union $type) => self::union($type, $codebase, $level + 1),
                $atomic->properties,
            )
            : array_map(
                fn(int|string $property, Union $type) => implode('', [
                    $tab($level),
                    $type->possibly_undefined ? "{$property}?: " : "{$property}: ",
                    self::union($type, $codebase, $level + 1),
                ]),
                array_keys($atomic->properties),
                array_values($atomic->properties),
            );

        return $atomic->is_list
            ? $openBracket . implode(", ", array_values($shape)) . $closeBracket
            : $openBracket . "\n" . implode(",\n", array_values($shape)) . ",\n" . $closeBracket;
    }
}
