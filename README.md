# Yii3 Queue NATS Adapter


[![tests](https://github.com/g41797/queue-nats/actions/workflows/tests.yml/badge.svg)](https://github.com/g41797/queue-nats/actions/workflows/tests.yml)

## Description

Yii3 Queue [**NATS**](https://nats.io/) Adapter is new adapter in [Yii3 Queue Adapters family.](https://github.com/yiisoft/queue/blob/master/docs/guide/en/adapter-list.md)

It uses [JetStream](https://docs.nats.io/nats-concepts/jetstream) subsystem of NATS.

Implementation of adapter based on [basis company](https://github.com/basis-company/nats.php) library.

## Requirements

- PHP 8.2 or higher.

## Installation

The package could be installed with composer:

```shell
composer require g41797/queue-nats
```

## General usage

- As part of [Yii3 Queue Framework](https://github.com/yiisoft/queue/blob/master/docs/guide/en/README.md)
- Stand-alone

## License

The Yii3 Queue NATS Adapter is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.
