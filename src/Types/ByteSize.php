<?php declare(strict_types = 1);

/**
 * ByteSize.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\Types;

/**
 * Communication data bits types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ByteSize: int
{

	case SIZE_4 = 4; // win

	case SIZE_5 = 5;

	case SIZE_6 = 6;

	case SIZE_7 = 7;

	case SIZE_8 = 8;

}
