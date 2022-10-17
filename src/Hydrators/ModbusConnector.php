<?php declare(strict_types = 1);

/**
 * Modbus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           07.12.21
 */

namespace FastyBird\Connector\Modbus\Hydrators;

use FastyBird\Connector\Modbus\Entities;
use FastyBird\DevicesModule\Hydrators as DevicesModuleHydrators;

/**
 * Modbus connector entity hydrator
 *
 * @phpstan-extends DevicesModuleHydrators\Connectors\Connector<Entities\ModbusConnector>
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ModbusConnector extends DevicesModuleHydrators\Connectors\Connector
{

	public function getEntityName(): string
	{
		return Entities\ModbusConnector::class;
	}

}
