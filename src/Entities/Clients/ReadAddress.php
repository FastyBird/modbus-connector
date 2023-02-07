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
		private readonly Entities\ModbusChannel $channel,
	)
	{
	}

	public function getAddress(): int
	{
		return $this->address;
	}

	public function getChannel(): Entities\ModbusChannel
	{
		return $this->channel;
	}

	abstract public function getSize(): int;

	public function toArray(): array
	{
		return [
			'address' => $this->getAddress(),
			'channel' => $this->getChannel()->getIdentifier(),
		];
	}

}
