<?php declare(strict_types = 1);

/**
 * ModbusConnector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           07.12.21
 */

namespace FastyBird\ModbusConnector\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * @ORM\Entity
 */
class ModbusConnector extends DevicesModuleEntities\Connectors\Connector
{

	public const CONNECTOR_TYPE = 'modbus';

	public function getType(): string
	{
		return self::CONNECTOR_TYPE;
	}

	public function getDiscriminatorName(): string
	{
		return self::CONNECTOR_TYPE;
	}

	public function getSource(): MetadataTypes\ModuleSource|MetadataTypes\PluginSource|MetadataTypes\ConnectorSource
	{
		return MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS);
	}

}
