<?php declare(strict_types = 1);

/**
 * ReadHoldingsRegistersRequest.php
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
use FastyBird\Library\Metadata\Types as MetadataTypes;

/**
 * Read holdings registers request entity
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ReadHoldingsRegistersRequest extends ReadRequest
{

	/**
	 * @throws Exceptions\InvalidState
	 */
	public function getDataType(): MetadataTypes\DataType
	{
		$dataType = null;

		foreach ($this->getAddresses() as $address) {
			$property = $address->getChannel()->findProperty(Types\ChannelPropertyIdentifier::IDENTIFIER_VALUE);

			if ($property === null) {
				throw new Exceptions\InvalidState('Channel value property could not be loaded');
			}

			if ($dataType === null) {
				$dataType = $property->getDataType();
			} elseif (!$dataType->equals($property->getDataType())) {
				throw new Exceptions\InvalidState('Registers chunk data types are mixed');
			}
		}

		if ($dataType === null) {
			throw new Exceptions\InvalidState('Data type could not be determined from addresses');
		}

		return $dataType;
	}

}
