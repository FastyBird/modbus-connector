<?php declare(strict_types = 1);

/**
 * DevicePropertyIdentifierType.php
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
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * Device property identifier types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DevicePropertyIdentifierType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const IDENTIFIER_STATE = MetadataTypes\DevicePropertyNameType::NAME_STATE;
	public const IDENTIFIER_ADDRESS = MetadataTypes\DevicePropertyNameType::NAME_ADDRESS;
	public const IDENTIFIER_BYTE_ORDER = 'byte-order';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
