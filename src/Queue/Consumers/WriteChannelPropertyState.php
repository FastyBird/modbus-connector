<?php declare(strict_types = 1);

/**
 * WriteChannelPropertyState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Queue
 * @since          1.0.0
 *
 * @date           18.07.23
 */

namespace FastyBird\Connector\Modbus\Queue\Consumers;

use DateTimeInterface;
use Exception;
use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Queue;
use FastyBird\Connector\Modbus\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\States as DevicesStates;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Nette\Utils;
use RuntimeException;
use Throwable;
use function floatval;
use function in_array;
use function intval;
use function is_bool;
use function is_int;
use function is_numeric;
use function sprintf;
use function strval;

/**
 * Write state to device message consumer
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class WriteChannelPropertyState implements Queue\Consumer
{

	use Nette\SmartObject;

	public function __construct(
		private readonly Queue\Queue $queue,
		private readonly API\ConnectionManager $connectionManager,
		private readonly API\Transformer $transformer,
		private readonly Helpers\Entity $entityHelper,
		private readonly Helpers\Connector $connectorHelper,
		private readonly Helpers\Device $deviceHelper,
		private readonly Helpers\Channel $channelHelper,
		private readonly Modbus\Logger $logger,
		private readonly DevicesModels\Configuration\Connectors\Repository $connectorsConfigurationRepository,
		private readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStatesManager,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exception
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\WriteChannelPropertyState) {
			return false;
		}

		$now = $this->dateTimeFactory->getNow();

		$findConnectorQuery = new DevicesQueries\Configuration\FindConnectors();
		$findConnectorQuery->byId($entity->getConnector());
		$findConnectorQuery->byType(Entities\ModbusConnector::TYPE);

		$connector = $this->connectorsConfigurationRepository->findOneBy($findConnectorQuery);

		if ($connector === null) {
			$this->logger->error(
				'Connector could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$findDeviceQuery = new DevicesQueries\Configuration\FindDevices();
		$findDeviceQuery->forConnector($connector);
		$findDeviceQuery->byId($entity->getDevice());
		$findDeviceQuery->byType(Entities\ModbusDevice::TYPE);

		$device = $this->devicesConfigurationRepository->findOneBy($findDeviceQuery);

		if ($device === null) {
			$this->logger->error(
				'Device could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$findChannelQuery = new DevicesQueries\Configuration\FindChannels();
		$findChannelQuery->forDevice($device);
		$findChannelQuery->byId($entity->getChannel());
		$findChannelQuery->byType(Entities\ModbusChannel::TYPE);

		$channel = $this->channelsConfigurationRepository->findOneBy($findChannelQuery);

		if ($channel === null) {
			$this->logger->error(
				'Channel could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelDynamicProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byId($entity->getProperty());

		$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
			$findChannelPropertyQuery,
			MetadataDocuments\DevicesModule\ChannelDynamicProperty::class,
		);

		if ($property === null) {
			$this->logger->error(
				'Channel property could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		if (!$property->isSettable()) {
			$this->resetExpected($property);

			$this->logger->error(
				'Channel property is not writable',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$state = $this->channelPropertiesStatesManager->getValue($property);

		if ($state === null) {
			return true;
		}

		$expectedValue = MetadataUtilities\ValueHelper::transformValueToDevice(
			$property->getDataType(),
			$property->getFormat(),
			$state->getExpectedValue(),
		);

		if ($expectedValue === null) {
			$this->resetExpected($property);

			return true;
		}

		$valueToWrite = $this->transformer->transformValueToDevice(
			$property->getDataType(),
			$property->getFormat(),
			$state->getExpectedValue(),
		);

		if ($valueToWrite === null) {
			$this->resetExpected($property);

			$this->logger->error(
				'Value to write into register is invalid',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$this->channelPropertiesStatesManager->setValue(
			$property,
			Utils\ArrayHash::from([
				DevicesStates\Property::PENDING_FIELD => $now->format(DateTimeInterface::ATOM),
			]),
		);

		$mode = $this->connectorHelper->getClientMode($connector);

		if ($mode->equalsValue(Types\ClientMode::RTU)) {
			$station = $this->deviceHelper->getAddress($device);

			if (!is_numeric($station)) {
				$this->queue->append(
					$this->entityHelper->create(
						Entities\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $connector->getId(),
							'device' => $device->getId(),
							'state' => MetadataTypes\ConnectionState::STATE_ALERT,
						],
					),
				);

				$this->resetExpected($property);

				$this->logger->error(
					'Device address is not configured',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $entity->getConnector()->toString(),
						],
						'device' => [
							'id' => $entity->getDevice()->toString(),
						],
						'channel' => [
							'id' => $entity->getChannel()->toString(),
						],
						'property' => [
							'id' => $entity->getProperty()->toString(),
						],
						'data' => $entity->toArray(),
					],
				);

				return true;
			}

			$address = $this->channelHelper->getAddress($channel);

			if (!is_int($address)) {
				$this->resetExpected($property);

				$this->logger->error(
					'Channel address is not configured',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $entity->getConnector()->toString(),
						],
						'device' => [
							'id' => $entity->getDevice()->toString(),
						],
						'channel' => [
							'id' => $entity->getChannel()->toString(),
						],
						'property' => [
							'id' => $entity->getProperty()->toString(),
						],
						'data' => $entity->toArray(),
					],
				);

				return true;
			}

			$deviceExpectedDataType = $this->transformer->determineDeviceWriteDataType(
				$property->getDataType(),
				$property->getFormat(),
			);

			if (!in_array($deviceExpectedDataType->getValue(), [
				MetadataTypes\DataType::DATA_TYPE_CHAR,
				MetadataTypes\DataType::DATA_TYPE_UCHAR,
				MetadataTypes\DataType::DATA_TYPE_SHORT,
				MetadataTypes\DataType::DATA_TYPE_USHORT,
				MetadataTypes\DataType::DATA_TYPE_INT,
				MetadataTypes\DataType::DATA_TYPE_UINT,
				MetadataTypes\DataType::DATA_TYPE_FLOAT,
				MetadataTypes\DataType::DATA_TYPE_BOOLEAN,
			], true)) {
				$this->resetExpected($property);

				$this->logger->error(
					sprintf(
						'Trying to write property with unsupported data type: %s for channel property',
						strval($deviceExpectedDataType->getValue()),
					),
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $entity->getConnector()->toString(),
						],
						'device' => [
							'id' => $entity->getDevice()->toString(),
						],
						'channel' => [
							'id' => $entity->getChannel()->toString(),
						],
						'property' => [
							'id' => $entity->getProperty()->toString(),
						],
						'data' => $entity->toArray(),
					],
				);

				return true;
			}

			try {
				if ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
					if (in_array($valueToWrite->getValue(), [0, 1], true) || is_bool($valueToWrite->getValue())) {
						$this->connectionManager->getRtuClient($connector)->writeSingleCoil(
							$station,
							$address,
							is_bool(
								$valueToWrite->getValue(),
							) ? $valueToWrite->getValue() : $valueToWrite->getValue() === 1,
						);

					} else {
						$this->resetExpected($property);

						$this->logger->error(
							'Value for boolean property have to be 1/0 or true/false',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
								'type' => 'write-channel-property-state-message-consumer',
								'connector' => [
									'id' => $entity->getConnector()->toString(),
								],
								'device' => [
									'id' => $entity->getDevice()->toString(),
								],
								'channel' => [
									'id' => $entity->getChannel()->toString(),
								],
								'property' => [
									'id' => $entity->getProperty()->toString(),
								],
								'data' => $entity->toArray(),
							],
						);

						return true;
					}
				} elseif (
					$valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
					|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
					|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
					|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
					|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
					|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
					|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)
				) {
					if (
						$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
						|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
					) {
						$bytes = $this->transformer->packSignedInt(
							intval($valueToWrite->getValue()),
							2,
							$this->deviceHelper->getByteOrder($device),
						);

					} elseif (
						$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
						|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
					) {
						$bytes = $this->transformer->packUnsignedInt(
							intval($valueToWrite->getValue()),
							2,
							$this->deviceHelper->getByteOrder($device),
						);

					} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)) {
						$bytes = $this->transformer->packSignedInt(
							intval($valueToWrite->getValue()),
							4,
							$this->deviceHelper->getByteOrder($device),
						);

					} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)) {
						$bytes = $this->transformer->packUnsignedInt(
							intval($valueToWrite->getValue()),
							4,
							$this->deviceHelper->getByteOrder($device),
						);

					} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
						$bytes = $this->transformer->packFloat(
							floatval($valueToWrite->getValue()),
							$this->deviceHelper->getByteOrder($device),
						);

					} else {
						$this->resetExpected($property);

						$this->logger->error(
							'Provided data type is not supported',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
								'type' => 'write-channel-property-state-message-consumer',
								'connector' => [
									'id' => $entity->getConnector()->toString(),
								],
								'device' => [
									'id' => $entity->getDevice()->toString(),
								],
								'channel' => [
									'id' => $entity->getChannel()->toString(),
								],
								'property' => [
									'id' => $entity->getProperty()->toString(),
								],
								'data' => $entity->toArray(),
							],
						);

						return true;
					}

					if ($bytes === null) {
						$this->resetExpected($property);

						$this->logger->error(
							'Data could not be converted for write',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
								'type' => 'write-channel-property-state-message-consumer',
								'connector' => [
									'id' => $entity->getConnector()->toString(),
								],
								'device' => [
									'id' => $entity->getDevice()->toString(),
								],
								'channel' => [
									'id' => $entity->getChannel()->toString(),
								],
								'property' => [
									'id' => $entity->getProperty()->toString(),
								],
								'data' => $entity->toArray(),
							],
						);

						return true;
					}

					$this->connectionManager
						->getRtuClient($connector)
						->writeSingleHolding($station, $address, $bytes);

					$state = $this->channelPropertiesStatesManager->getValue($property);

					if ($state?->getExpectedValue() !== null) {
						$this->channelPropertiesStatesManager->setValue(
							$property,
							Utils\ArrayHash::from([
								DevicesStates\Property::ACTUAL_VALUE_FIELD => $state->getExpectedValue(),
								DevicesStates\Property::VALID_FIELD => true,
							]),
						);
					}
				} else {
					$this->resetExpected($property);

					$this->logger->error(
						sprintf(
							'Unsupported value data type: %s',
							strval($valueToWrite->getDataType()->getValue()),
						),
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
							'type' => 'write-channel-property-state-message-consumer',
							'connector' => [
								'id' => $entity->getConnector()->toString(),
							],
							'device' => [
								'id' => $entity->getDevice()->toString(),
							],
							'channel' => [
								'id' => $entity->getChannel()->toString(),
							],
							'property' => [
								'id' => $entity->getProperty()->toString(),
							],
							'data' => $entity->toArray(),
						],
					);

					return true;
				}
			} catch (Exceptions\ModbusRtu $ex) {
				$this->resetExpected($property);

				$this->logger->error(
					'Could not write state to device',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'write-channel-property-state-message-consumer',
						'exception' => BootstrapHelpers\Logger::buildException($ex),
						'connector' => [
							'id' => $entity->getConnector()->toString(),
						],
						'device' => [
							'id' => $entity->getDevice()->toString(),
						],
						'channel' => [
							'id' => $entity->getChannel()->toString(),
						],
						'property' => [
							'id' => $entity->getProperty()->toString(),
						],
						'data' => $entity->toArray(),
					],
				);

				return true;
			}
		} elseif ($mode->equalsValue(Types\ClientMode::TCP)) {
			$ipAddress = $this->deviceHelper->getIpAddress($device);

			if ($ipAddress === null) {
				$this->queue->append(
					$this->entityHelper->create(
						Entities\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $connector->getId(),
							'device' => $device->getId(),
							'state' => MetadataTypes\ConnectionState::STATE_ALERT,
						],
					),
				);

				$this->resetExpected($property);

				$this->logger->error(
					'Device ip address is not configured',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $entity->getConnector()->toString(),
						],
						'device' => [
							'id' => $entity->getDevice()->toString(),
						],
						'channel' => [
							'id' => $entity->getChannel()->toString(),
						],
						'property' => [
							'id' => $entity->getProperty()->toString(),
						],
						'data' => $entity->toArray(),
					],
				);

				return true;
			}

			$port = $this->deviceHelper->getPort($device);

			$deviceAddress = $ipAddress . ':' . $port;

			$unitId = $this->deviceHelper->getUnitId($device);

			$address = $this->channelHelper->getAddress($channel);

			if (!is_int($address)) {
				$this->resetExpected($property);

				$this->logger->error(
					'Channel address is not configured',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $entity->getConnector()->toString(),
						],
						'device' => [
							'id' => $entity->getDevice()->toString(),
						],
						'channel' => [
							'id' => $entity->getChannel()->toString(),
						],
						'property' => [
							'id' => $entity->getProperty()->toString(),
						],
						'data' => $entity->toArray(),
					],
				);

				return true;
			}

			$deviceExpectedDataType = $this->transformer->determineDeviceWriteDataType(
				$property->getDataType(),
				$property->getFormat(),
			);

			if (!in_array($deviceExpectedDataType->getValue(), [
				MetadataTypes\DataType::DATA_TYPE_CHAR,
				MetadataTypes\DataType::DATA_TYPE_UCHAR,
				MetadataTypes\DataType::DATA_TYPE_SHORT,
				MetadataTypes\DataType::DATA_TYPE_USHORT,
				MetadataTypes\DataType::DATA_TYPE_INT,
				MetadataTypes\DataType::DATA_TYPE_UINT,
				MetadataTypes\DataType::DATA_TYPE_FLOAT,
				MetadataTypes\DataType::DATA_TYPE_BOOLEAN,
				MetadataTypes\DataType::DATA_TYPE_STRING,
			], true)) {
				$this->resetExpected($property);

				$this->logger->error(
					sprintf(
						'Trying to write property with unsupported data type: %s for channel property',
						strval($deviceExpectedDataType->getValue()),
					),
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $entity->getConnector()->toString(),
						],
						'device' => [
							'id' => $entity->getDevice()->toString(),
						],
						'channel' => [
							'id' => $entity->getChannel()->toString(),
						],
						'property' => [
							'id' => $entity->getProperty()->toString(),
						],
						'data' => $entity->toArray(),
					],
				);

				return true;
			}

			if ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
				if (in_array($valueToWrite->getValue(), [0, 1], true) || is_bool($valueToWrite->getValue())) {
					$promise = $this->connectionManager
						->getTcpClient()
						->writeSingleCoil(
							$deviceAddress,
							$unitId,
							$address,
							is_bool(
								$valueToWrite->getValue(),
							) ? $valueToWrite->getValue() : $valueToWrite->getValue() === 1,
						);

				} else {
					$this->resetExpected($property);

					$this->logger->error(
						'Value for boolean property have to be 1/0 or true/false',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
							'type' => 'write-channel-property-state-message-consumer',
							'connector' => [
								'id' => $entity->getConnector()->toString(),
							],
							'device' => [
								'id' => $entity->getDevice()->toString(),
							],
							'channel' => [
								'id' => $entity->getChannel()->toString(),
							],
							'property' => [
								'id' => $entity->getProperty()->toString(),
							],
							'data' => $entity->toArray(),
						],
					);

					return true;
				}
			} elseif (
				$valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
				|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
				|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
				|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
				|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
				|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
				|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)
			) {
				if (
					$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
					|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
				) {
					$bytes = $this->transformer->packSignedInt(
						intval($valueToWrite->getValue()),
						2,
						$this->deviceHelper->getByteOrder($device),
					);

				} elseif (
					$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
					|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
				) {
					$bytes = $this->transformer->packUnsignedInt(
						intval($valueToWrite->getValue()),
						2,
						$this->deviceHelper->getByteOrder($device),
					);

				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)) {
					$bytes = $this->transformer->packSignedInt(
						intval($valueToWrite->getValue()),
						4,
						$this->deviceHelper->getByteOrder($device),
					);

				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)) {
					$bytes = $this->transformer->packUnsignedInt(
						intval($valueToWrite->getValue()),
						4,
						$this->deviceHelper->getByteOrder($device),
					);

				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
					$bytes = $this->transformer->packFloat(
						floatval($valueToWrite->getValue()),
						$this->deviceHelper->getByteOrder($device),
					);

				} else {
					$this->resetExpected($property);

					$this->logger->error(
						'Provided data type is not supported',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
							'type' => 'write-channel-property-state-message-consumer',
							'connector' => [
								'id' => $entity->getConnector()->toString(),
							],
							'device' => [
								'id' => $entity->getDevice()->toString(),
							],
							'channel' => [
								'id' => $entity->getChannel()->toString(),
							],
							'property' => [
								'id' => $entity->getProperty()->toString(),
							],
							'data' => $entity->toArray(),
						],
					);

					return true;
				}

				if ($bytes === null) {
					$this->resetExpected($property);

					$this->logger->error(
						'Data could not be converted for write',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
							'type' => 'write-channel-property-state-message-consumer',
							'connector' => [
								'id' => $entity->getConnector()->toString(),
							],
							'device' => [
								'id' => $entity->getDevice()->toString(),
							],
							'channel' => [
								'id' => $entity->getChannel()->toString(),
							],
							'property' => [
								'id' => $entity->getProperty()->toString(),
							],
							'data' => $entity->toArray(),
						],
					);

					return true;
				}

				$promise = $this->connectionManager
					->getTcpClient()
					->writeSingleHolding(
						$deviceAddress,
						$unitId,
						$address,
						$bytes,
					);
			} else {
				$this->resetExpected($property);

				$this->logger->error(
					sprintf(
						'Unsupported value data type: %s',
						strval($valueToWrite->getDataType()->getValue()),
					),
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $entity->getConnector()->toString(),
						],
						'device' => [
							'id' => $entity->getDevice()->toString(),
						],
						'channel' => [
							'id' => $entity->getChannel()->toString(),
						],
						'property' => [
							'id' => $entity->getProperty()->toString(),
						],
						'data' => $entity->toArray(),
					],
				);

				return true;
			}

			$promise->then(
				function () use ($property): void {
					$state = $this->channelPropertiesStatesManager->getValue($property);

					if ($state?->getExpectedValue() !== null) {
						$this->channelPropertiesStatesManager->setValue(
							$property,
							Utils\ArrayHash::from([
								DevicesStates\Property::ACTUAL_VALUE_FIELD => $state->getExpectedValue(),
								DevicesStates\Property::VALID_FIELD => true,
							]),
						);
					}
				},
				function (Throwable $ex) use ($entity, $property): void {
					$this->resetExpected($property);

					$this->logger->error(
						'Could not write state to device',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
							'type' => 'write-channel-property-state-message-consumer',
							'exception' => BootstrapHelpers\Logger::buildException($ex),
							'connector' => [
								'id' => $entity->getConnector()->toString(),
							],
							'device' => [
								'id' => $entity->getDevice()->toString(),
							],
							'channel' => [
								'id' => $entity->getChannel()->toString(),
							],
							'property' => [
								'id' => $entity->getProperty()->toString(),
							],
							'data' => $entity->toArray(),
						],
					);
				},
			);

		} else {
			$this->resetExpected($property);

			$this->logger->error(
				'Client mode is not supported',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'mode' => $mode->getValue(),
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$this->logger->debug(
			'Consumed write device state message',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
				'type' => 'write-channel-property-state-message-consumer',
				'connector' => [
					'id' => $entity->getConnector()->toString(),
				],
				'device' => [
					'id' => $entity->getDevice()->toString(),
				],
				'channel' => [
					'id' => $entity->getChannel()->toString(),
				],
				'property' => [
					'id' => $entity->getProperty()->toString(),
				],
				'data' => $entity->toArray(),
			],
		);

		return true;
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function resetExpected(MetadataDocuments\DevicesModule\ChannelDynamicProperty $property): void
	{
		$this->channelPropertiesStatesManager->setValue(
			$property,
			Utils\ArrayHash::from([
				DevicesStates\Property::EXPECTED_VALUE_FIELD => null,
				DevicesStates\Property::PENDING_FIELD => false,
			]),
		);
	}

}
