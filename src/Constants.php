<?php declare(strict_types = 1);

/**
 * Constants.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     common
 * @since          0.34.0
 *
 * @date           01.08.22
 */

namespace FastyBird\ModbusConnector;

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

	public const DEFAULT_RTU_BAUD_RATE = Types\BaudRateType::BAUD_RATE_9600;
	public const DEFAULT_RTU_SERIAL_INTERFACE = '/dev/ttyAMA0';

	public const PROPERTY_REGISTER = '/^(?P<name>[a-zA-Z-]+)_(?P<address>[0-9]+)$/';

}