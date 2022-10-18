<?php declare(strict_types = 1);

/**
 * ModbusDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           30.01.22
 */

namespace FastyBird\Connector\Modbus\Hydrators;

use FastyBird\Connector\Modbus\Entities;
use FastyBird\Module\Devices\Hydrators as DevicesHydrators;

/**
 * Modbus device entity hydrator
 *
 * @phpstan-extends DevicesHydrators\Devices\Device<Entities\ModbusDevice>
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ModbusDevice extends DevicesHydrators\Devices\Device
{

	public function getEntityName(): string
	{
		return Entities\ModbusDevice::class;
	}

}
