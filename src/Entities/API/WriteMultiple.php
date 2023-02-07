<?php declare(strict_types = 1);

/**
 * WriteMultiple.php
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
final class WriteMultiple implements Entities\API\Entity
{

	use Nette\SmartObject;

	public function __construct(
		private readonly int $station,
		private readonly Types\ModbusFunction $function,
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

	public function toArray(): array
	{
		return [
			'station' => $this->getStation(),
			'function' => $this->getFunction()->getValue(),
		];
	}

}
