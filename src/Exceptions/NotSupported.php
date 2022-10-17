<?php declare(strict_types = 1);

/**
 * NotSupported.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Exceptions
 * @since          0.34.0
 *
 * @date           02.08.22
 */

namespace FastyBird\Connector\Modbus\Exceptions;

use InvalidArgumentException as PHPInvalidArgumentException;

class NotSupported extends PHPInvalidArgumentException implements Exception
{

}
