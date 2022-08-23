<?php declare(strict_types = 1);

/**
 * ConnectorPropertyIdentifierType.php
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

namespace FastyBird\ModbusConnector\Types;

use Consistence;

/**
 * Connector property identifier types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ConnectorPropertyIdentifierType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const IDENTIFIER_CLIENT_MODE = 'mode';

	public const IDENTIFIER_RTU_INTERFACE = 'rtu-interface';
	public const IDENTIFIER_RTU_BYTE_SIZE = 'rtu-byte-size';
	public const IDENTIFIER_RTU_BAUD_RATE = 'rtu-baud-rate';
	public const IDENTIFIER_RTU_PARITY = 'rtu-parity';
	public const IDENTIFIER_RTU_STOP_BITS = 'rtu-stop-bits';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
