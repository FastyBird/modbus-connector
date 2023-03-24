# FastyBird IoT Modbus connector

[![Build Status](https://badgen.net/github/checks/FastyBird/modbus-connector/main?cache=300&style=flat-square)](https://github.com/FastyBird/modbus-connector/actions)
[![Licence](https://badgen.net/github/license/FastyBird/modbus-connector?cache=300&style=flat-square)](https://github.com/FastyBird/modbus-connector/blob/main/LICENSE.md)
[![Code coverage](https://badgen.net/coveralls/c/github/FastyBird/modbus-connector?cache=300&style=flat-square)](https://coveralls.io/r/FastyBird/modbus-connector)
[![Mutation testing](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FFastyBird%2Fmodbus-connector%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/FastyBird/modbus-connector/main)

![PHP](https://badgen.net/packagist/php/FastyBird/modbus-connector?cache=300&style=flat-square)
[![PHP latest stable](https://badgen.net/packagist/v/FastyBird/modbus-connector/latest?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/modbus-connector)
[![PHP downloads total](https://badgen.net/packagist/dt/FastyBird/modbus-connector?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/modbus-connector)
[![PHPStan](https://img.shields.io/badge/phpstan-enabled-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

***

## What is Modbus connector?

Modbus connector is extension for [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem
which is integrating [Modbus](https://www.modbus.org) devices.

Modbus Connector is a distributed extension that is developed in [PHP](https://www.php.net), built on the [Nette](https://nette.org) and [Symfony](https://symfony.com) frameworks,
and is licensed under [Apache2](http://www.apache.org/licenses/LICENSE-2.0).

### Features:

- Support for both [Modbus](https://en.wikipedia.org/wiki/Modbus) RTU and TCP/IP communication protocols
- Capability to handle a diverse range of data types
- Ability to read and write from various Modbus memory areas, including coils, discrete inputs, holding registers, and input registers
- Integration with the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) [devices module](https://github.com/FastyBird/devices-module) for easy management and monitoring of Modbus devices
- [{JSON:API}](https://jsonapi.org/) schemas for full API access, providing a standardized and consistent way for developers to access and manipulate Modbus device data
- Regular updates with new features and bug fixes, ensuring that the Modbus Connector is always up-to-date and reliable.

## Requirements

Modbus connector is tested against PHP 8.1 and require installed [Process Control](https://www.php.net/manual/en/book.pcntl.php)
PHP extension.

## Installation

### Manual installation

The best way to install **fastybird/modbus-connector** is using [Composer](http://getcomposer.org/):

```sh
composer require fastybird/modbus-connector
```

### Marketplace installation [WIP]

You could install this connector in your [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
application under marketplace section.

## Documentation

Learn how to connect Modbus devices and manage them with [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) system
in [documentation](https://github.com/FastyBird/modbus-connector/wiki).

## Feedback

Use the [issue tracker](https://github.com/FastyBird/fastybird/issues) for bugs
or [mail](mailto:code@fastybird.com) or [Tweet](https://twitter.com/fastybird) us for any idea that can improve the
project.

Thank you for testing, reporting and contributing.

## Changelog

For release info check [release page](https://github.com/FastyBird/fastybird/releases).

## Contribute

The sources of this package are contained in the [FastyBird monorepo](https://github.com/FastyBird/fastybird). We welcome contributions for this package on [FastyBird/fastybird](https://github.com/FastyBird/).

## Maintainers

<table>
	<tbody>
		<tr>
			<td align="center">
				<a href="https://github.com/akadlec">
					<img alt="akadlec" width="80" height="80" src="https://avatars3.githubusercontent.com/u/1866672?s=460&amp;v=4" />
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
