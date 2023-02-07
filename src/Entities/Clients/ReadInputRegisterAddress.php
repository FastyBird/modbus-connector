<?php declare(strict_types = 1);

/**
 * ReadInputRegisterAddress.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           05.02.23
 */

namespace FastyBird\Connector\Modbus\Entities\Clients;

use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Types\DataType;

/**
 * Read coil register address entity
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ReadInputRegisterAddress extends ReadAddress
{

	/**
	 * @throws Exceptions\InvalidState
	 */
	public function getSize(): int
	{
		$property = $this->getChannel()->findProperty(Types\ChannelPropertyIdentifier::IDENTIFIER_VALUE);

		if ($property === null) {
			throw new Exceptions\InvalidState('Channel value property could not be loaded');
		}

		if (
			$property->getDataType()->equalsValue(DataType::DATA_TYPE_INT)
			|| $property->getDataType()->equalsValue(DataType::DATA_TYPE_UINT)
			|| $property->getDataType()->equalsValue(DataType::DATA_TYPE_FLOAT)
		) {
			return 2;
		}

		return 1;
	}

}
