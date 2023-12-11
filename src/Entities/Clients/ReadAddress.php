<?php declare(strict_types = 1);

/**
 * ReadAddress.php
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

use FastyBird\Connector\Modbus\Entities;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;

/**
 * Base read register address entity
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class ReadAddress implements Entities\API\Entity
{

	use Nette\SmartObject;

	public function __construct(
		private readonly int $address,
		private readonly MetadataDocuments\DevicesModule\Channel $channel,
		private readonly MetadataTypes\DataType $dataType,
	)
	{
	}

	public function getAddress(): int
	{
		return $this->address;
	}

	public function getChannel(): MetadataDocuments\DevicesModule\Channel
	{
		return $this->channel;
	}

	public function getDataType(): MetadataTypes\DataType
	{
		return $this->dataType;
	}

	public function getSize(): int
	{
		if (
			$this->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
			|| $this->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
			|| $this->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)
		) {
			return 2;
		}

		return 1;
	}

	public function toArray(): array
	{
		return [
			'address' => $this->getAddress(),
			'channel' => $this->getChannel()->getIdentifier(),
			'data_type' => $this->getDataType()->getValue(),
		];
	}

}
