<?php declare(strict_types = 1);

/**
 * PropertyHelper.php
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

use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\States as DevicesModuleStates;
use FastyBird\Metadata;
use Nette;
use Nette\Utils;
use Psr\Log;

/**
 * Useful dynamic property state helpers
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class PropertyHelper
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\States\DevicePropertiesRepository */
	private DevicesModuleModels\States\DevicePropertiesRepository $devicePropertyStateRepository;

	/** @var DevicesModuleModels\States\DevicePropertiesManager */
	private DevicesModuleModels\States\DevicePropertiesManager $devicePropertiesStatesManager;

	/** @var DevicesModuleModels\States\ChannelPropertiesRepository */
	private DevicesModuleModels\States\ChannelPropertiesRepository $channelPropertyStateRepository;

	/** @var DevicesModuleModels\States\ChannelPropertiesManager */
	private DevicesModuleModels\States\ChannelPropertiesManager $channelPropertiesStatesManager;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\States\DevicePropertiesRepository $devicePropertyStateRepository
	 * @param DevicesModuleModels\States\DevicePropertiesManager $devicePropertiesStatesManager
	 * @param DevicesModuleModels\States\ChannelPropertiesRepository $channelPropertyStateRepository
	 * @param DevicesModuleModels\States\ChannelPropertiesManager $channelPropertiesStatesManager
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\States\DevicePropertiesRepository $devicePropertyStateRepository,
		DevicesModuleModels\States\DevicePropertiesManager $devicePropertiesStatesManager,
		DevicesModuleModels\States\ChannelPropertiesRepository $channelPropertyStateRepository,
		DevicesModuleModels\States\ChannelPropertiesManager $channelPropertiesStatesManager,
		?Log\LoggerInterface $logger
	) {
		$this->channelsRepository = $channelsRepository;

		$this->devicePropertyStateRepository = $devicePropertyStateRepository;
		$this->devicePropertiesStatesManager = $devicePropertiesStatesManager;
		$this->channelPropertyStateRepository = $channelPropertyStateRepository;
		$this->channelPropertiesStatesManager = $channelPropertiesStatesManager;

		$this->logger = $logger ?? new Log\NullLogger();
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
		try {
			if ($property instanceof Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity) {
				$propertyState = $this->devicePropertyStateRepository->findOne($property);
			} else {
				$propertyState = $this->channelPropertyStateRepository->findOne($property);
			}
		} catch (DevicesModuleExceptions\NotImplementedException) {
			$this->logger->warning(
				'States repository is not configured. State could not be fetched',
				[
					'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
					'type'   => 'property-state-helper',
				]
			);

			return;
		}

		try {
			// In case synchronization failed...
			if ($propertyState === null) {
				// ...create state in storage
				if ($property instanceof Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity) {
					$propertyState = $this->devicePropertiesStatesManager->create(
						$property,
						$data
					);

					$this->logger->debug(
						'Device property state was created',
						[
							'source'   => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
							'type'     => 'property-state-helper',
							'device'   => [
								'id' => $property->getDevice()->toString(),
							],
							'property' => [
								'id'    => $property->getId()->toString(),
								'state' => $propertyState->toArray(),
							],
						]
					);
				} else {
					$propertyState = $this->channelPropertiesStatesManager->create(
						$property,
						$data
					);

					$channel = $this->channelsRepository->findById($property->getChannel());

					$this->logger->debug(
						'Channel property state was created',
						[
							'source'   => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
							'type'     => 'property-state-helper',
							'device'   => [
								'id' => $channel?->getDevice()->toString(),
							],
							'channel'  => [
								'id' => $property->getChannel()->toString(),
							],
							'property' => [
								'id'    => $property->getId()->toString(),
								'state' => $propertyState->toArray(),
							],
						]
					);
				}
			} else {
				if (
					$property instanceof Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity
					&& $propertyState instanceof DevicesModuleStates\IDeviceProperty
				) {
					$propertyState = $this->devicePropertiesStatesManager->update(
						$property,
						$propertyState,
						$data
					);

					$this->logger->debug(
						'Channel property state was updated',
						[
							'source'   => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
							'type'     => 'property-state-helper',
							'device'   => [
								'id' => $property->getDevice()->toString(),
							],
							'property' => [
								'id'    => $property->getId()->toString(),
								'state' => $propertyState->toArray(),
							],
						]
					);
				} elseif (
					$property instanceof Metadata\Entities\Modules\DevicesModule\IChannelDynamicPropertyEntity
					&& $propertyState instanceof DevicesModuleStates\IChannelProperty
				) {
					$propertyState = $this->channelPropertiesStatesManager->update(
						$property,
						$propertyState,
						$data
					);

					$channel = $this->channelsRepository->findById($property->getChannel());

					$this->logger->debug(
						'Channel property state was updated',
						[
							'source'   => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
							'type'     => 'property-state-helper',
							'device'   => [
								'id' => $channel?->getDevice()->toString(),
							],
							'channel'  => [
								'id' => $property->getChannel()->toString(),
							],
							'property' => [
								'id'    => $property->getId()->toString(),
								'state' => $propertyState->toArray(),
							],
						]
					);
				}
			}
		} catch (DevicesModuleExceptions\NotImplementedException) {
			$this->logger->warning(
				'States manager is not configured. State could not be saved',
				[
					'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
					'type'   => 'property-state-helper',
				]
			);
		}
	}

	/**
	 * @param Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity|Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity[]|Metadata\Entities\Modules\DevicesModule\IChannelDynamicPropertyEntity|Metadata\Entities\Modules\DevicesModule\IChannelDynamicPropertyEntity[] $property
	 * @param bool $state
	 *
	 * @return void
	 */
	public function setValidState(
		Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity|Metadata\Entities\Modules\DevicesModule\IChannelDynamicPropertyEntity|array $property,
		bool $state
	): void {
		if (is_array($property)) {
			foreach ($property as $item) {
				$this->setValue($item, Utils\ArrayHash::from([
					'valid' => $state,
				]));
			}
		} else {
			$this->setValue($property, Utils\ArrayHash::from([
				'valid' => $state,
			]));
		}
	}

}
