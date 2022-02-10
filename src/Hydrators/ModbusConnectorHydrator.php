<?php declare(strict_types = 1);

/**
 * ModbusConnectorHydrator.php
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

namespace FastyBird\ModbusConnector\Hydrators;

use FastyBird\DevicesModule\Hydrators as DevicesModuleHydrators;
use FastyBird\ModbusConnector\Entities;

/**
 * Modbus connector entity hydrator
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleHydrators\Connectors\ConnectorHydrator<Entities\IModbusConnector>
 */
final class ModbusConnectorHydrator extends DevicesModuleHydrators\Connectors\ConnectorHydrator
{

	/**
	 * {@inheritDoc}
	 */
	public function getEntityName(): string
	{
		return Entities\ModbusConnector::class;
	}

}
