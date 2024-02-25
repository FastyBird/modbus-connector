<?php declare(strict_types = 1);

/**
 * Device.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 * @since          1.0.0
 *
 * @date           22.01.22
 */

namespace FastyBird\Connector\Modbus\Schemas\Devices;

use FastyBird\Connector\Modbus\Entities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Schemas as DevicesSchemas;

/**
 * Modbus device entity schema
 *
 * @extends DevicesSchemas\Devices\Device<Entities\Devices\Device>
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Schemas
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Device extends DevicesSchemas\Devices\Device
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\Sources\Connector::MODBUS->value . '/device/' . Entities\Devices\Device::TYPE;

	public function getEntityClass(): string
	{
		return Entities\Devices\Device::class;
	}

	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
