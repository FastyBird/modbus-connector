<?php declare(strict_types = 1);

/**
 * Constants.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     common
 * @since          1.0.0
 *
 * @date           01.08.22
 */

namespace FastyBird\Connector\Modbus;

/**
 * Connector constants
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Constants
{

	public const DEFAULT_RTU_SERIAL_INTERFACE = '/dev/ttyAMA0';

	public const MAX_ANALOG_REGISTERS_PER_MODBUS_REQUEST = 124;

	public const MAX_DISCRETE_REGISTERS_PER_MODBUS_REQUEST = 2_048;

}
