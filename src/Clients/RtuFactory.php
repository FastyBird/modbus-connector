<?php declare(strict_types = 1);

/**
 * RtuFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Clients;

use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ModbusConnector\Types;

/**
 * Modbus RTU devices client factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface RtuFactory extends ClientFactory
{

	public const MODE = Types\ClientMode::MODE_RTU;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return Rtu
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): Rtu;

}
