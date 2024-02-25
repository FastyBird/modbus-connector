<?php declare(strict_types = 1);

/**
 * Read.php
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

namespace FastyBird\Connector\Modbus\Clients\Messages\Request;

use FastyBird\Connector\Modbus\Clients;
use Nette;
use function array_map;

/**
 * Base read register request request
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Read implements Clients\Messages\Message
{

	use Nette\SmartObject;

	/**
	 * @param array<Clients\Messages\Pointer\ReadAddress> $addresses
	 */
	public function __construct(
		private readonly array $addresses,
		private readonly int $startAddress,
		private readonly int $quantity,
	)
	{
	}

	/**
	 * @return array<Clients\Messages\Pointer\ReadAddress>
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
				static fn (Clients\Messages\Pointer\ReadAddress $address): array => $address->toArray(),
				$this->getAddresses(),
			),
			'start_address' => $this->getStartAddress(),
			'quantity' => $this->getQuantity(),
		];
	}

}
