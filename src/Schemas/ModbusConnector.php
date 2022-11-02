<?php declare(strict_types = 1);

/**
 * Modbus.php
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

namespace FastyBird\Connector\Modbus\Schemas;

use FastyBird\Connector\Modbus\Entities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Schemas as DevicesSchemas;

/**
 * Modbus connector entity schema
 *
 * @extends DevicesSchemas\Connectors\Connector<Entities\ModbusConnector>
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ModbusConnector extends DevicesSchemas\Connectors\Connector
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS . '/connector/' . Entities\ModbusConnector::CONNECTOR_TYPE;

	public function getEntityClass(): string
	{
		return Entities\ModbusConnector::class;
	}

	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
