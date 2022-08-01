<?php declare(strict_types = 1);

/**
 * ModbusConnectorSchema.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 * @since          0.1.0
 *
 * @date           07.12.21
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
 * @phpstan-extends DevicesModuleSchemas\Connectors\ConnectorSchema<Entities\IModbusConnectorEntity>
 */
final class ModbusConnectorSchema extends DevicesModuleSchemas\Connectors\ConnectorSchema
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_MODBUS . '/connector/' . Entities\ModbusConnectorEntity::CONNECTOR_TYPE;

	/**
	 * {@inheritDoc}
	 */
	public function getEntityClass(): string
	{
		return Entities\ModbusConnectorEntity::class;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
