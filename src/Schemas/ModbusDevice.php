<?php declare(strict_types = 1);

/**
 * ModbusDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 * @since          0.1.0
 *
 * @date           22.01.22
 */

namespace FastyBird\Connector\Modbus\Schemas;

use FastyBird\Connector\Modbus\Entities;
use FastyBird\DevicesModule\Schemas as DevicesModuleSchemas;
use FastyBird\Library\Metadata\Types as MetadataTypes;

/**
 * Modbus connector entity schema
 *
 * @phpstan-extends DevicesModuleSchemas\Devices\Device<Entities\ModbusDevice>
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ModbusDevice extends DevicesModuleSchemas\Devices\Device
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS . '/device/' . Entities\ModbusDevice::DEVICE_TYPE;

	public function getEntityClass(): string
	{
		return Entities\ModbusDevice::class;
	}

	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
