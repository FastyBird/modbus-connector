<?php declare(strict_types = 1);

/**
 * InvalidState.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Exceptions
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Exceptions;

use RuntimeException;

class InvalidState extends RuntimeException implements Exception
{

}
