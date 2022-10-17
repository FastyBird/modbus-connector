<?php declare(strict_types = 1);

/**
 * Property.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 * @since          0.34.0
 *
 * @date           01.08.22
 */

namespace FastyBird\Connector\Modbus\Helpers;

use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Exceptions as MetadataExceptions;
use Nette;
use Nette\Utils;

/**
 * Useful dynamic property state helpers
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Property
{

	use Nette\SmartObject;

	public function __construct(
		private readonly DevicesModuleModels\States\DevicePropertyStateManager $devicePropertyStateManager,
		private readonly DevicesModuleModels\States\ChannelPropertyStateManager $channelPropertyStateManager,
	)
	{
	}

	/**
	 * @throws DevicesModuleExceptions\InvalidState
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function setValue(
		MetadataEntities\DevicesModule\DeviceDynamicProperty|MetadataEntities\DevicesModule\ChannelDynamicProperty $property,
		Utils\ArrayHash $data,
	): void
	{
		if ($property instanceof MetadataEntities\DevicesModule\DeviceDynamicProperty) {
			$this->devicePropertyStateManager->setValue($property, $data);
		} else {
			$this->channelPropertyStateManager->setValue($property, $data);
		}
	}

}
