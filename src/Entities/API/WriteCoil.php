<?php declare(strict_types = 1);

/**
 * WriteCoil.php
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
 * Write single coil register response entity
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class WriteCoil implements Entities\API\Entity
{

	use Nette\SmartObject;

	public function __construct(
		private readonly int $station,
		private readonly Types\ModbusFunction $function,
		private readonly bool $value,
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

	public function getValue(): bool
	{
		return $this->value;
	}

	public function toArray(): array
	{
		return [
			'station' => $this->getStation(),
			'function' => $this->getFunction()->getValue(),
			'value' => $this->getValue(),
		];
	}

}
