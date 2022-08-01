<?php declare(strict_types = 1);

/**
 * ModbusDeviceSchema.php
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

namespace FastyBird\ModbusConnector\Schemas;

use FastyBird\DevicesModule\Schemas as DevicesModuleSchemas;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ModbusConnector\Entities;

/**
 * Modbus connector entity schema
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleSchemas\Devices\DeviceSchema<Entities\IModbusDeviceEntity>
 */
final class ModbusDeviceSchema extends DevicesModuleSchemas\Devices\DeviceSchema
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_MODBUS . '/device/' . Entities\ModbusDeviceEntity::DEVICE_TYPE;

	/**
	 * {@inheritDoc}
	 */
	public function getEntityClass(): string
	{
		return Entities\ModbusDeviceEntity::class;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
