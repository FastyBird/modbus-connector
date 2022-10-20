<?php declare(strict_types = 1);

/**
 * Property.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 * @since          0.34.0
 *
 * @date           01.08.22
 */

namespace FastyBird\Connector\Modbus\Helpers;

use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
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
		private readonly DevicesModels\States\DevicePropertyStateManager $devicePropertyStateManager,
		private readonly DevicesModels\States\ChannelPropertyStateManager $channelPropertyStateManager,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
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
