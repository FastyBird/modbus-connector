<?php declare(strict_types = 1);

/**
 * ReadAnalogInputs.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           03.02.23
 */

namespace FastyBird\Connector\Modbus\Entities\API;

use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Types;
use Nette;

/**
 * Analog registers reading response entity
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ReadAnalogInputs implements Entities\API\Entity
{

	use Nette\SmartObject;

	/**
	 * @param array<int> $registers
	 */
	public function __construct(
		private readonly int $station,
		private readonly Types\ModbusFunction $function,
		private readonly int $count,
		private readonly array $registers,
	)
	{
	}

	public function getStation(): int
	{
		return $this->station;
	}

	public function getFunction(): Types\ModbusFunction
	{
		return $this->function;
	}

	public function getCount(): int
	{
		return $this->count;
	}

	/**
	 * @return array<int>
	 */
	public function getRegisters(): array
	{
		return $this->registers;
	}

	public function toArray(): array
	{
		return [
			'station' => $this->getStation(),
			'function' => $this->getFunction()->getValue(),
			'count' => $this->getCount(),
			'registers' => $this->getRegisters(),
		];
	}

}
