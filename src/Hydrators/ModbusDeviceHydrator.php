<?php declare(strict_types = 1);

/**
 * ModbusDeviceHydrator.php
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

namespace FastyBird\ModbusConnector\Hydrators;

use FastyBird\DevicesModule\Hydrators as DevicesModuleHydrators;
use FastyBird\ModbusConnector\Entities;

/**
 * Modbus device entity hydrator
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleHydrators\Devices\DeviceHydrator<Entities\IModbusDeviceEntity>
 */
final class ModbusDeviceHydrator extends DevicesModuleHydrators\Devices\DeviceHydrator
{

	/**
	 * {@inheritDoc}
	 */
	public function getEntityName(): string
	{
		return Entities\ModbusDeviceEntity::class;
	}

}
