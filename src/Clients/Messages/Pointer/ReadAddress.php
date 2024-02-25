<?php declare(strict_types = 1);

/**
 * ReadAddress.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           05.02.23
 */

namespace FastyBird\Connector\Modbus\Clients\Messages\Pointer;

use FastyBird\Connector\Modbus\Clients;
use FastyBird\Connector\Modbus\Documents;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;

/**
 * Base read register address request
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class ReadAddress implements Clients\Messages\Message
{

	use Nette\SmartObject;

	public function __construct(
		private readonly int $address,
		private readonly Documents\Channels\Channel $channel,
		private readonly MetadataTypes\DataType $dataType,
	)
	{
	}

	public function getAddress(): int
	{
		return $this->address;
	}

	public function getChannel(): Documents\Channels\Channel
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
			$this->getDataType() === MetadataTypes\DataType::INT
			|| $this->getDataType() === MetadataTypes\DataType::UINT
			|| $this->getDataType() === MetadataTypes\DataType::FLOAT
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
			'data_type' => $this->getDataType()->value,
		];
	}

}
