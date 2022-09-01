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

namespace FastyBird\ModbusConnector\Helpers;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata;
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

	/** @var DevicesModuleModels\States\DevicePropertyStateManager */
	private DevicesModuleModels\States\DevicePropertyStateManager $devicePropertyStateManager;

	/** @var DevicesModuleModels\States\ChannelPropertyStateManager */
	private DevicesModuleModels\States\ChannelPropertyStateManager $channelPropertyStateManager;

	/**
	 * @param DevicesModuleModels\States\DevicePropertyStateManager $devicePropertyStateManager
	 * @param DevicesModuleModels\States\ChannelPropertyStateManager $channelPropertyStateManager
	 */
	public function __construct(
		DevicesModuleModels\States\DevicePropertyStateManager $devicePropertyStateManager,
		DevicesModuleModels\States\ChannelPropertyStateManager $channelPropertyStateManager
	) {
		$this->devicePropertyStateManager = $devicePropertyStateManager;
		$this->channelPropertyStateManager = $channelPropertyStateManager;
	}

	/**
	 * @param Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity|Metadata\Entities\Modules\DevicesModule\IChannelDynamicPropertyEntity $property
	 * @param Utils\ArrayHash $data
	 *
	 * @return void
	 */
	public function setValue(
		Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity|Metadata\Entities\Modules\DevicesModule\IChannelDynamicPropertyEntity $property,
		Utils\ArrayHash $data
	): void {
		if ($property instanceof Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity) {
			$this->devicePropertyStateManager->setValue($property, $data);
		} else {
			$this->channelPropertyStateManager->setValue($property, $data);
		}
	}

}
