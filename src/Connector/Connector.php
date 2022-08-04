<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Connector
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Connector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ModbusConnector\Clients;
use FastyBird\ModbusConnector\Helpers;
use Nette;

/**
 * Connector service container
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesModuleConnectors\IConnector
{

	use Nette\SmartObject;

	/** @var Clients\IClient */
	private Clients\IClient $client;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Helpers\PropertyHelper */
	private Helpers\PropertyHelper $propertyStateHelper;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelPropertiesRepository */
	private DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Clients\IClient $client
	 * @param Helpers\PropertyHelper $propertyStateHelper
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Clients\IClient $client,
		Helpers\PropertyHelper $propertyStateHelper,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	) {
		$this->connector = $connector;

		$this->client = $client;

		$this->propertyStateHelper = $propertyStateHelper;

		$this->devicesRepository = $devicesRepository;
		$this->devicePropertiesRepository = $devicePropertiesRepository;
		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;

		$this->deviceConnectionStateManager = $deviceConnectionStateManager;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): void
	{
		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			$this->deviceConnectionStateManager->setState(
				$device,
				MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_UNKNOWN)
			);

			/** @var MetadataEntities\Modules\DevicesModule\IDeviceDynamicPropertyEntity[] $properties */
			$properties = $this->devicePropertiesRepository->findAllByDevice(
				$device->getId(),
				MetadataEntities\Modules\DevicesModule\DeviceDynamicPropertyEntity::class
			);

			$this->propertyStateHelper->setValidState($properties, false);

			foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
				/** @var MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity[] $properties */
				$properties = $this->channelPropertiesRepository->findAllByChannel(
					$channel->getId(),
					MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity::class
				);

				$this->propertyStateHelper->setValidState($properties, false);
			}
		}

		$this->client->connect();
	}

	/**
	 * {@inheritDoc}
	 */
	public function terminate(): void
	{
		$this->client->disconnect();

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			$this->deviceConnectionStateManager->setState(
				$device,
				MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_DISCONNECTED)
			);

			/** @var MetadataEntities\Modules\DevicesModule\IDeviceDynamicPropertyEntity[] $properties */
			$properties = $this->devicePropertiesRepository->findAllByDevice(
				$device->getId(),
				MetadataEntities\Modules\DevicesModule\DeviceDynamicPropertyEntity::class
			);

			$this->propertyStateHelper->setValidState($properties, false);

			foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
				/** @var MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity[] $properties */
				$properties = $this->channelPropertiesRepository->findAllByChannel(
					$channel->getId(),
					MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity::class
				);

				$this->propertyStateHelper->setValidState($properties, false);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasUnfinishedTasks(): bool
	{
		return false;
	}

	/**
	 * @param MetadataEntities\Actions\IActionDeviceControlEntity $action
	 *
	 * @return void
	 */
	public function handleDeviceControlAction(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		if (!$action->getAction()->equalsValue(MetadataTypes\ControlActionType::ACTION_SET)) {
			return;
		}

		$this->client->writeDeviceControl($action);
	}

	/**
	 * @param MetadataEntities\Actions\IActionChannelControlEntity $action
	 *
	 * @return void
	 */
	public function handleChannelControlAction(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		if (!$action->getAction()->equalsValue(MetadataTypes\ControlActionType::ACTION_SET)) {
			return;
		}

		$this->client->writeChannelControl($action);
	}

}
