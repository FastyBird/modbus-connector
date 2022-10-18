<?php declare(strict_types = 1);

/**
 * ModbusDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           30.01.22
 */

namespace FastyBird\Connector\Modbus\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\Library\Metadata\Types as MetadataTypes;

/**
 * @ORM\Entity
 */
class ModbusDevice extends DevicesModuleEntities\Devices\Device
{

	public const DEVICE_TYPE = 'modbus';

	public function getType(): string
	{
		return self::DEVICE_TYPE;
	}

	public function getDiscriminatorName(): string
	{
		return self::DEVICE_TYPE;
	}

	public function getSource(): MetadataTypes\ConnectorSource
	{
		return MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS);
	}

}
