<?php declare(strict_types = 1);

/**
 * ReadRequest.php
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
use function array_map;

/**
 * Base read register request entity
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class ReadRequest implements Entities\API\Entity
{

	use Nette\SmartObject;

	/**
	 * @param array<ReadAddress> $addresses
	 */
	public function __construct(
		private readonly array $addresses,
		private readonly int $startAddress,
		private readonly int $quantity,
	)
	{
	}

	/**
	 * @return array<ReadAddress>
	 */
	public function getAddresses(): array
	{
		return $this->addresses;
	}

	public function getStartAddress(): int
	{
		return $this->startAddress;
	}

	public function getQuantity(): int
	{
		return $this->quantity;
	}

	public function toArray(): array
	{
		return [
			'addresses' => array_map(
				static fn (ReadAddress $address): array => $address->toArray(),
				$this->getAddresses(),
			),
			'start_address' => $this->getStartAddress(),
			'quantity' => $this->getQuantity(),
		];
	}

}
