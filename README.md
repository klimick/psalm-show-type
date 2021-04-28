# psalm-show-type
Analog for psalm-trace but with pretty print

### Installation

```console
$ composer require --dev klimick/psalm-show-type
$ vendor/bin/psalm-plugin enable Klimick\\PsalmShowType\\ShowTypePlugin
```


### Usage

```php
<?php

// With assignment:
/** @show-type */
$a = 42;

// With return statement (not supported by @psalm-trace):
$fn = function(): array {
    /** @show-type */
    return [
        'value' => 42,
    ];
};

// Type from arrow fn expression (not supported by @psalm-trace):
$arrowFn = fn() => /** @show-type */ str_contains('psalm-show-type', 'show-type');
```

### Output examples

#### class

```
@psalm-trace: Foo\Bar\Str
```
```
@show-type: Str
```

#### generic class

```
@psalm-trace: SplDoublyLinkedList<int, Foo\Bar\Str>
```
```
@show-type: SplDoublyLinkedList<TKey: int, TValue: Str>
```

#### array/iterable

```
@psalm-trace: array<int, Foo\Bar\Str>
```
```
@show-type: array<TKey: int, TValue: Str>
```

#### list

```
@psalm-trace: list<Foo\Bar\Str>
```
```
@show-type: list<TValue: Str>
```

#### array shape

```
@psalm-trace: array{prop1: Foo\Bar\Str, prop2: SplDoublyLinkedList<int, Foo\Bar\Str>}
```
```
@show-type: array{
    prop1: Str,
    prop2: SplDoublyLinkedList<TKey: int, TValue: Str>,
}
```

#### callable/closure

```
@psalm-trace: callable(Foo\Bar\Num): array{prop1: Foo\Bar\Str, prop2: SplDoublyLinkedList<int, Foo\Bar\Str>}
```
```
@show-type: callable(Num): array{
    prop1: Str,
    prop2: SplDoublyLinkedList<TKey: int, TValue: Str>,
}
```
