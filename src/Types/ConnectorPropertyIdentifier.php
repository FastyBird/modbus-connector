<?php declare(strict_types = 1);

/**
 * ConnectorPropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           01.08.22
 */

namespace FastyBird\Connector\Modbus\Types;

/**
 * Connector property identifier types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ConnectorPropertyIdentifier: string
{

	case CLIENT_MODE = 'mode';

	case RTU_INTERFACE = 'rtu_interface';

	case RTU_BYTE_SIZE = 'rtu_byte_size';

	case RTU_BAUD_RATE = 'rtu_baud_rate';

	case RTU_PARITY = 'rtu_parity';

	case RTU_STOP_BITS = 'rtu_stop_bits';

}
