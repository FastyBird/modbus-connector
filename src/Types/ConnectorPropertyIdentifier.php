<?php declare(strict_types = 1);

/**
 * ConnectorPropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          0.34.0
 *
 * @date           01.08.22
 */

namespace FastyBird\Connector\Modbus\Types;

use Consistence;
use function strval;

/**
 * Connector property identifier types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ConnectorPropertyIdentifier extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const IDENTIFIER_CLIENT_MODE = 'mode';

	public const IDENTIFIER_RTU_INTERFACE = 'rtu_interface';

	public const IDENTIFIER_RTU_BYTE_SIZE = 'rtu_byte_size';

	public const IDENTIFIER_RTU_BAUD_RATE = 'rtu_baud_rate';

	public const IDENTIFIER_RTU_PARITY = 'rtu_parity';

	public const IDENTIFIER_RTU_STOP_BITS = 'rtu_stop_bits';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
