<?php declare(strict_types = 1);

/**
 * ChannelPropertyIdentifierType.php
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
class ChannelPropertyIdentifierType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const IDENTIFIER_STATE = MetadataTypes\ChannelPropertyIdentifierType::IDENTIFIER_ADDRESS;

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
