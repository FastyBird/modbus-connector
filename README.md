# FastyBird IoT Modbus connector

[![Build Status](https://badgen.net/github/checks/FastyBird/modbus-connector/master?cache=300&style=flat-square)](https://github.com/FastyBird/modbus-connector/actions)
[![Licence](https://badgen.net/github/license/FastyBird/modbus-connector?cache=300&style=flat-square)](https://github.com/FastyBird/modbus-connector/blob/master/LICENSE.md)
[![Code coverage](https://badgen.net/coveralls/c/github/FastyBird/modbus-connector?cache=300&style=flat-square)](https://coveralls.io/r/FastyBird/modbus-connector)

![PHP](https://badgen.net/packagist/php/FastyBird/modbus-connector?cache=300&style=flat-square)
[![PHP latest stable](https://badgen.net/packagist/v/FastyBird/modbus-connector/latest?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/modbus-connector)
[![PHP downloads total](https://badgen.net/packagist/dt/FastyBird/modbus-connector?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/modbus-connector)
[![PHPStan](https://img.shields.io/badge/phpstan-enabled-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

## What is FastyBird IoT Modbus connector?

Modbus connector is a combined [FastyBird IoT](https://www.fastybird.com) extension which is integrating [Modbus](https://www.modbus.org) devices into [FastyBird](https://www.fastybird.com) IoT system

[FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) Modbus connector is
an [Apache2 licensed](http://www.apache.org/licenses/LICENSE-2.0) distributed extension, developed
in [PHP](https://www.php.net) with [Nette framework](https://nette.org) and in [Python](https://python.org).

### Features:

- [Modbus RTU](https://en.wikipedia.org/wiki/Modbus) support
- Modbus connector management for [FastyBird IoT](https://www.fastybird.com) [devices module](https://github.com/FastyBird/devices-module)
- Modbus device management for [FastyBird IoT](https://www.fastybird.com) [devices module](https://github.com/FastyBird/devices-module)
- [{JSON:API}](https://jsonapi.org/) schemas for full api access

## Requirements

PHP part of [FastyBird](https://www.fastybird.com) Modbus connector is tested against PHP 7.4
and [ReactPHP http](https://github.com/reactphp/http) 0.8 event-driven, streaming plaintext HTTP server
and [Nette framework](https://nette.org/en/) 3.0 PHP framework for real programmers

Python part of [FastyBird](https://www.fastybird.com) Modbus connector is tested against [Python 3.7](http://python.org)

## Installation

### Manual installation

The best way to install **fastybird/modbus-connector** is using [Composer](http://getcomposer.org/):

```sh
composer require fastybird/modbus-connector
```

### Marketplace installation

You could install this connector in your [FastyBird IoT](https://www.fastybird.com) application under marketplace section

## Documentation

Learn how to connect Modbus devices to FastyBird IoT system
in [documentation](https://github.com/FastyBird/modbus-connector/blob/master/.docs/en/index.md).

## Feedback

Use the [issue tracker](https://github.com/FastyBird/modbus-connector/issues) for bugs
or [mail](mailto:code@fastybird.com) or [Tweet](https://twitter.com/fastybird) us for any idea that can improve the
project.

Thank you for testing, reporting and contributing.

## Changelog

For release info check [release page](https://github.com/FastyBird/modbus-connector/releases)

## Maintainers

<table>
	<tbody>
		<tr>
			<td align="center">
				<a href="https://github.com/akadlec">
					<img width="80" height="80" src="https://avatars3.githubusercontent.com/u/1866672?s=460&amp;v=4">
				</a>
				<br>
				<a href="https://github.com/akadlec">Adam Kadlec</a>
			</td>
		</tr>
	</tbody>
</table>

***
Homepage [https://www.fastybird.com](https://www.fastybird.com) and
repository [https://github.com/fastybird/modbus-connector](https://github.com/fastybird/modbus-connector).
