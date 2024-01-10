# PHP DIO extension installation

It is better to use [Direct IO](https://www.php.net/manual/en/book.dio.php) extension which is better for communication with serial hardware.

These instructions are for Linux users.

## PEAR extension installation

[PEAR](https://pear.php.net) is a framework and distribution system for reusable PHP components.

To install it on your device run the following command:

```shell
apt-get install php-pear php-dev
```

## DIO extension installation

With PEAR installed you could now install PECL extensions on you device.

[PECL](https://pecl.php.net) is a repository for PHP Extensions, providing a directory of all known extensions and hosting facilities for downloading and development of PHP extensions.

```shell
pecl install dio-0.2.1
```

> **NOTE:**
DIO extension is not released as stable, so you have to specify package version

## Activate DIO extension

After installation, you will have to add this extension into your `php.ini` file.

If you do not know where `php.ini` file is located, you could use this command:

```shell
php -i |grep php\.ini
```

and this command will output something like this:

```shell
Configuration File (php.ini) Path => /etc/php/8.2/cli
Loaded Configuration File => /etc/php/8.2/cli/php.ini
```

and now you could edit this `php.ini` file and add this line at the bottom of the file:

```shell
extension=dio;
```