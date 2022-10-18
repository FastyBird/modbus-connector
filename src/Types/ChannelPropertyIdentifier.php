<?php declare(strict_types = 1);

/**
 * ChannelPropertyIdentifier.php
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
use FastyBird\Library\Metadata\Types as MetadataTypes;
use function strval;

/**
 * Device property identifier types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ChannelPropertyIdentifier extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const IDENTIFIER_ADDRESS = MetadataTypes\ChannelPropertyIdentifier::IDENTIFIER_ADDRESS;

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
