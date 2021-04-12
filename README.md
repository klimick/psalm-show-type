# psalm-show-type
Analog for psalm-trace but with pretty print

Installation:

```console
$ composer require --dev klimick/psalm-show-type
$ vendor/bin/psalm-plugin enable klimick/psalm-show-type
```


Usage:

```php
<?php

// With assignment:
/** @show-type */
$a = 42;

// Or return (not supported by @psalm-trace):
$fn = function(): array {
    /** @show-type */
    return [
        'value' => 42,
    ];
};
```
