<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
    </a>
    <h1 align="center">Yii3 Queue Nats Adapter</h1>
    <br>
</p>

## Requirements

- PHP 8.1 or higher.

## Installation

The package could be installed with composer:

```shell
composer require g41977/queue-nats
```

## General usage

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
make test
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
make static-analyze
```

### Code style

Use [Rector](https://github.com/rectorphp/rector) to make codebase follow some specific rules or 
use either newest or any specific version of PHP: 

```shell
make rector
```

### Dependencies

Use [ComposerRequireChecker](https://github.com/maglnet/ComposerRequireChecker) to detect transitive 
[Composer](https://getcomposer.org/) dependencies.

## License

The Yii3 Queue Nats Adapter is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.
