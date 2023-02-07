<?php declare(strict_types = 1);

/**
 * ModbusChannel.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 * @since          1.0.0
 *
 * @date           24.01.23
 */

namespace FastyBird\Connector\Modbus\Schemas;

use FastyBird\Connector\Modbus\Entities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Schemas as DevicesSchemas;

/**
 * Modbus channel entity schema
 *
 * @extends DevicesSchemas\Channels\Channel<Entities\ModbusChannel>
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ModbusChannel extends DevicesSchemas\Channels\Channel
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS . '/channel/' . Entities\ModbusChannel::CHANNEL_TYPE;

	public function getEntityClass(): string
	{
		return Entities\ModbusChannel::class;
	}

	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
