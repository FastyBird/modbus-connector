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
use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Documents;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Queries;
use FastyBird\Connector\Modbus\Queue;
use FastyBird\Connector\Modbus\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Application\Helpers as ApplicationHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Library\Tools\Exceptions as ToolsExceptions;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\States as DevicesStates;
use FastyBird\Module\Devices\Types as DevicesTypes;
use Nette;
use Nette\Utils;
use RuntimeException;
use Throwable;
use TypeError;
use ValueError;
use function floatval;
use function in_array;
use function intval;
use function is_bool;
use function is_int;
use function is_numeric;
use function React\Async\async;
use function React\Async\await;
use function sprintf;

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
		private readonly Helpers\MessageBuilder $messageBuilder,
		private readonly Helpers\Connector $connectorHelper,
		private readonly Helpers\Device $deviceHelper,
		private readonly Helpers\Channel $channelHelper,
		private readonly Modbus\Logger $logger,
		private readonly DevicesModels\Configuration\Connectors\Repository $connectorsConfigurationRepository,
		private readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly DevicesModels\States\Async\ChannelPropertiesManager $channelPropertiesStatesManager,
		private readonly DateTimeFactory\Clock $clock,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 * @throws Throwable
	 */
	public function consume(Queue\Messages\Message $message): bool
	{
		if (!$message instanceof Queue\Messages\WriteChannelPropertyState) {
			return false;
		}

		$findConnectorQuery = new Queries\Configuration\FindConnectors();
		$findConnectorQuery->byId($message->getConnector());

		$connector = $this->connectorsConfigurationRepository->findOneBy(
			$findConnectorQuery,
			Documents\Connectors\Connector::class,
		);

		if ($connector === null) {
			$this->logger->error(
				'Connector could not be loaded',
				[
					'source' => MetadataTypes\Sources\Connector::MODBUS->value,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $message->getDevice()->toString(),
					],
					'channel' => [
						'id' => $message->getChannel()->toString(),
					],
					'property' => [
						'id' => $message->getProperty()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		$findDeviceQuery = new Queries\Configuration\FindDevices();
		$findDeviceQuery->forConnector($connector);
		$findDeviceQuery->byId($message->getDevice());

		$device = $this->devicesConfigurationRepository->findOneBy(
			$findDeviceQuery,
			Documents\Devices\Device::class,
		);

		if ($device === null) {
			$this->logger->error(
				'Device could not be loaded',
				[
					'source' => MetadataTypes\Sources\Connector::MODBUS->value,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $message->getDevice()->toString(),
					],
					'channel' => [
						'id' => $message->getChannel()->toString(),
					],
					'property' => [
						'id' => $message->getProperty()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		$findChannelQuery = new Queries\Configuration\FindChannels();
		$findChannelQuery->forDevice($device);
		$findChannelQuery->byId($message->getChannel());

		$channel = $this->channelsConfigurationRepository->findOneBy(
			$findChannelQuery,
			Documents\Channels\Channel::class,
		);

		if ($channel === null) {
			$this->logger->error(
				'Channel could not be loaded',
				[
					'source' => MetadataTypes\Sources\Connector::MODBUS->value,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $message->getChannel()->toString(),
					],
					'property' => [
						'id' => $message->getProperty()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelDynamicProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byId($message->getProperty());

		$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
			$findChannelPropertyQuery,
			DevicesDocuments\Channels\Properties\Dynamic::class,
		);

		if ($property === null) {
			$this->logger->error(
				'Channel property could not be loaded',
				[
					'source' => MetadataTypes\Sources\Connector::MODBUS->value,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $channel->getId()->toString(),
					],
					'property' => [
						'id' => $message->getProperty()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		if (!$property->isSettable()) {
			$this->resetExpected($property);

			$this->logger->warning(
				'Channel property is not writable',
				[
					'source' => MetadataTypes\Sources\Connector::MODBUS->value,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $channel->getId()->toString(),
					],
					'property' => [
						'id' => $property->getId()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		$state = $message->getState();

		if ($state === null) {
			return true;
		}

		$expectedValue = MetadataUtilities\Value::flattenValue($state->getExpectedValue());

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
					'source' => MetadataTypes\Sources\Connector::MODBUS->value,
					'type' => 'write-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $channel->getId()->toString(),
					],
					'property' => [
						'id' => $property->getId()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		$now = $this->clock->getNow();
		$pending = $state->getPending();

		if (
			$pending === false
			|| (
				$pending instanceof DateTimeInterface
				&& (float) $now->format('Uv') - (float) $pending->format('Uv') <= Modbus\Constants::WRITE_DEBOUNCE_DELAY
			)
		) {
			return true;
		}

		await($this->channelPropertiesStatesManager->setPendingState(
			$property,
			true,
			MetadataTypes\Sources\Connector::MODBUS,
		));

		$mode = $this->connectorHelper->getClientMode($connector);

		if ($mode === Types\ClientMode::RTU) {
			$station = $this->deviceHelper->getAddress($device);

			if (!is_numeric($station)) {
				$this->queue->append(
					$this->messageBuilder->create(
						Queue\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $connector->getId(),
							'device' => $device->getId(),
							'state' => DevicesTypes\ConnectionState::ALERT->value,
						],
					),
				);

				$this->resetExpected($property);

				$this->logger->error(
					'Device address is not configured',
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $message->getConnector()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $message->toArray(),
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
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $message->getConnector()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $message->toArray(),
					],
				);

				return true;
			}

			$deviceExpectedDataType = $this->transformer->determineDeviceWriteDataType(
				$property->getDataType(),
				$property->getFormat(),
			);

			if (
				!in_array(
					$deviceExpectedDataType,
					[
						MetadataTypes\DataType::CHAR,
						MetadataTypes\DataType::UCHAR,
						MetadataTypes\DataType::SHORT,
						MetadataTypes\DataType::USHORT,
						MetadataTypes\DataType::INT,
						MetadataTypes\DataType::UINT,
						MetadataTypes\DataType::FLOAT,
						MetadataTypes\DataType::BOOLEAN,
					],
					true,
				)
			) {
				$this->resetExpected($property);

				$this->logger->error(
					sprintf(
						'Trying to write property with unsupported data type: %s for channel property',
						$deviceExpectedDataType->value,
					),
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $message->getConnector()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $message->toArray(),
					],
				);

				return true;
			}

			try {
				if ($valueToWrite->getDataType() === MetadataTypes\DataType::BOOLEAN) {
					if (in_array($valueToWrite->getValue(), [0, 1], true) || is_bool($valueToWrite->getValue())) {
						$this->connectionManager->getRtuClient($connector)->writeSingleCoil(
							$station,
							$address,
							is_bool($valueToWrite->getValue())
									? $valueToWrite->getValue()
									: $valueToWrite->getValue() === 1,
						);

						await($this->channelPropertiesStatesManager->set(
							$property,
							Utils\ArrayHash::from([
								DevicesStates\Property::ACTUAL_VALUE_FIELD => $state->getExpectedValue(),
							]),
							MetadataTypes\Sources\Connector::MODBUS,
						));
					} else {
						$this->resetExpected($property);

						$this->logger->error(
							'Value for boolean property have to be 1/0 or true/false',
							[
								'source' => MetadataTypes\Sources\Connector::MODBUS->value,
								'type' => 'write-channel-property-state-message-consumer',
								'connector' => [
									'id' => $message->getConnector()->toString(),
								],
								'device' => [
									'id' => $device->getId()->toString(),
								],
								'channel' => [
									'id' => $channel->getId()->toString(),
								],
								'property' => [
									'id' => $property->getId()->toString(),
								],
								'data' => $message->toArray(),
							],
						);

						return true;
					}
				} elseif (
					$valueToWrite->getDataType() === MetadataTypes\DataType::SHORT
					|| $valueToWrite->getDataType() === MetadataTypes\DataType::USHORT
					|| $valueToWrite->getDataType() === MetadataTypes\DataType::CHAR
					|| $valueToWrite->getDataType() === MetadataTypes\DataType::UCHAR
					|| $valueToWrite->getDataType() === MetadataTypes\DataType::INT
					|| $valueToWrite->getDataType() === MetadataTypes\DataType::UINT
					|| $valueToWrite->getDataType() === MetadataTypes\DataType::FLOAT
				) {
					if (
						$deviceExpectedDataType === MetadataTypes\DataType::CHAR
						|| $deviceExpectedDataType === MetadataTypes\DataType::SHORT
					) {
						$bytes = $this->transformer->packSignedInt(
							intval($valueToWrite->getValue()),
							2,
							$this->deviceHelper->getByteOrder($device),
						);

					} elseif (
						$deviceExpectedDataType === MetadataTypes\DataType::UCHAR
						|| $deviceExpectedDataType === MetadataTypes\DataType::USHORT
					) {
						$bytes = $this->transformer->packUnsignedInt(
							intval($valueToWrite->getValue()),
							2,
							$this->deviceHelper->getByteOrder($device),
						);

					} elseif ($deviceExpectedDataType === MetadataTypes\DataType::INT) {
						$bytes = $this->transformer->packSignedInt(
							intval($valueToWrite->getValue()),
							4,
							$this->deviceHelper->getByteOrder($device),
						);

					} elseif ($deviceExpectedDataType === MetadataTypes\DataType::UINT) {
						$bytes = $this->transformer->packUnsignedInt(
							intval($valueToWrite->getValue()),
							4,
							$this->deviceHelper->getByteOrder($device),
						);

					} elseif ($deviceExpectedDataType === MetadataTypes\DataType::FLOAT) {
						$bytes = $this->transformer->packFloat(
							floatval($valueToWrite->getValue()),
							$this->deviceHelper->getByteOrder($device),
						);

					} else {
						$this->resetExpected($property);

						$this->logger->error(
							'Provided data type is not supported',
							[
								'source' => MetadataTypes\Sources\Connector::MODBUS->value,
								'type' => 'write-channel-property-state-message-consumer',
								'connector' => [
									'id' => $message->getConnector()->toString(),
								],
								'device' => [
									'id' => $device->getId()->toString(),
								],
								'channel' => [
									'id' => $channel->getId()->toString(),
								],
								'property' => [
									'id' => $property->getId()->toString(),
								],
								'data' => $message->toArray(),
							],
						);

						return true;
					}

					if ($bytes === null) {
						$this->resetExpected($property);

						$this->logger->error(
							'Data could not be converted for write',
							[
								'source' => MetadataTypes\Sources\Connector::MODBUS->value,
								'type' => 'write-channel-property-state-message-consumer',
								'connector' => [
									'id' => $message->getConnector()->toString(),
								],
								'device' => [
									'id' => $device->getId()->toString(),
								],
								'channel' => [
									'id' => $channel->getId()->toString(),
								],
								'property' => [
									'id' => $property->getId()->toString(),
								],
								'data' => $message->toArray(),
							],
						);

						return true;
					}

					$this->connectionManager
						->getRtuClient($connector)
						->writeSingleHolding($station, $address, $bytes);

					await($this->channelPropertiesStatesManager->set(
						$property,
						Utils\ArrayHash::from([
							DevicesStates\Property::ACTUAL_VALUE_FIELD => $state->getExpectedValue(),
						]),
						MetadataTypes\Sources\Connector::MODBUS,
					));
				} else {
					$this->resetExpected($property);

					$this->logger->error(
						sprintf(
							'Unsupported value data type: %s',
							$valueToWrite->getDataType()->value,
						),
						[
							'source' => MetadataTypes\Sources\Connector::MODBUS->value,
							'type' => 'write-channel-property-state-message-consumer',
							'connector' => [
								'id' => $message->getConnector()->toString(),
							],
							'device' => [
								'id' => $device->getId()->toString(),
							],
							'channel' => [
								'id' => $channel->getId()->toString(),
							],
							'property' => [
								'id' => $property->getId()->toString(),
							],
							'data' => $message->toArray(),
						],
					);

					return true;
				}
			} catch (Exceptions\ModbusRtu $ex) {
				$this->resetExpected($property);

				$this->logger->error(
					'Could not write state to device',
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'write-channel-property-state-message-consumer',
						'exception' => ApplicationHelpers\Logger::buildException($ex),
						'connector' => [
							'id' => $message->getConnector()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $message->toArray(),
					],
				);

				return true;
			}
		} else {
			$ipAddress = $this->deviceHelper->getIpAddress($device);

			if ($ipAddress === null) {
				$this->queue->append(
					$this->messageBuilder->create(
						Queue\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $connector->getId(),
							'device' => $device->getId(),
							'state' => DevicesTypes\ConnectionState::ALERT->value,
						],
					),
				);

				$this->resetExpected($property);

				$this->logger->error(
					'Device ip address is not configured',
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $message->getConnector()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $message->toArray(),
					],
				);

				return true;
			}

			$port = $this->deviceHelper->getPort($device);

			$deviceAddress = sprintf('%s:%s', $ipAddress, $port);

			$unitId = $this->deviceHelper->getUnitId($device);

			$address = $this->channelHelper->getAddress($channel);

			if (!is_int($address)) {
				$this->resetExpected($property);

				$this->logger->error(
					'Channel address is not configured',
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $message->getConnector()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $message->toArray(),
					],
				);

				return true;
			}

			$deviceExpectedDataType = $this->transformer->determineDeviceWriteDataType(
				$property->getDataType(),
				$property->getFormat(),
			);

			if (
				!in_array(
					$deviceExpectedDataType,
					[
						MetadataTypes\DataType::CHAR,
						MetadataTypes\DataType::UCHAR,
						MetadataTypes\DataType::SHORT,
						MetadataTypes\DataType::USHORT,
						MetadataTypes\DataType::INT,
						MetadataTypes\DataType::UINT,
						MetadataTypes\DataType::FLOAT,
						MetadataTypes\DataType::BOOLEAN,
						MetadataTypes\DataType::STRING,
					],
					true,
				)
			) {
				$this->resetExpected($property);

				$this->logger->error(
					sprintf(
						'Trying to write property with unsupported data type: %s for channel property',
						$deviceExpectedDataType->value,
					),
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $message->getConnector()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $message->toArray(),
					],
				);

				return true;
			}

			if ($valueToWrite->getDataType() === MetadataTypes\DataType::BOOLEAN) {
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
							'source' => MetadataTypes\Sources\Connector::MODBUS->value,
							'type' => 'write-channel-property-state-message-consumer',
							'connector' => [
								'id' => $message->getConnector()->toString(),
							],
							'device' => [
								'id' => $device->getId()->toString(),
							],
							'channel' => [
								'id' => $channel->getId()->toString(),
							],
							'property' => [
								'id' => $property->getId()->toString(),
							],
							'data' => $message->toArray(),
						],
					);

					return true;
				}
			} elseif (
				$valueToWrite->getDataType() === MetadataTypes\DataType::SHORT
				|| $valueToWrite->getDataType() === MetadataTypes\DataType::USHORT
				|| $valueToWrite->getDataType() === MetadataTypes\DataType::CHAR
				|| $valueToWrite->getDataType() === MetadataTypes\DataType::UCHAR
				|| $valueToWrite->getDataType() === MetadataTypes\DataType::INT
				|| $valueToWrite->getDataType() === MetadataTypes\DataType::UINT
				|| $valueToWrite->getDataType() === MetadataTypes\DataType::FLOAT
			) {
				if (
					$deviceExpectedDataType === MetadataTypes\DataType::CHAR
					|| $deviceExpectedDataType === MetadataTypes\DataType::SHORT
				) {
					$bytes = $this->transformer->packSignedInt(
						intval($valueToWrite->getValue()),
						2,
						$this->deviceHelper->getByteOrder($device),
					);

				} elseif (
					$deviceExpectedDataType === MetadataTypes\DataType::UCHAR
					|| $deviceExpectedDataType === MetadataTypes\DataType::USHORT
				) {
					$bytes = $this->transformer->packUnsignedInt(
						intval($valueToWrite->getValue()),
						2,
						$this->deviceHelper->getByteOrder($device),
					);

				} elseif ($deviceExpectedDataType === MetadataTypes\DataType::INT) {
					$bytes = $this->transformer->packSignedInt(
						intval($valueToWrite->getValue()),
						4,
						$this->deviceHelper->getByteOrder($device),
					);

				} elseif ($deviceExpectedDataType === MetadataTypes\DataType::UINT) {
					$bytes = $this->transformer->packUnsignedInt(
						intval($valueToWrite->getValue()),
						4,
						$this->deviceHelper->getByteOrder($device),
					);

				} elseif ($deviceExpectedDataType === MetadataTypes\DataType::FLOAT) {
					$bytes = $this->transformer->packFloat(
						floatval($valueToWrite->getValue()),
						$this->deviceHelper->getByteOrder($device),
					);

				} else {
					$this->resetExpected($property);

					$this->logger->error(
						'Provided data type is not supported',
						[
							'source' => MetadataTypes\Sources\Connector::MODBUS->value,
							'type' => 'write-channel-property-state-message-consumer',
							'connector' => [
								'id' => $message->getConnector()->toString(),
							],
							'device' => [
								'id' => $device->getId()->toString(),
							],
							'channel' => [
								'id' => $channel->getId()->toString(),
							],
							'property' => [
								'id' => $property->getId()->toString(),
							],
							'data' => $message->toArray(),
						],
					);

					return true;
				}

				if ($bytes === null) {
					$this->resetExpected($property);

					$this->logger->error(
						'Data could not be converted for write',
						[
							'source' => MetadataTypes\Sources\Connector::MODBUS->value,
							'type' => 'write-channel-property-state-message-consumer',
							'connector' => [
								'id' => $message->getConnector()->toString(),
							],
							'device' => [
								'id' => $device->getId()->toString(),
							],
							'channel' => [
								'id' => $channel->getId()->toString(),
							],
							'property' => [
								'id' => $property->getId()->toString(),
							],
							'data' => $message->toArray(),
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
						$valueToWrite->getDataType()->value,
					),
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'write-channel-property-state-message-consumer',
						'connector' => [
							'id' => $message->getConnector()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
						'property' => [
							'id' => $property->getId()->toString(),
						],
						'data' => $message->toArray(),
					],
				);

				return true;
			}

			$promise->then(
				async(function () use ($property, $state): void {
					await($this->channelPropertiesStatesManager->set(
						$property,
						Utils\ArrayHash::from([
							DevicesStates\Property::ACTUAL_VALUE_FIELD => $state->getExpectedValue(),
						]),
						MetadataTypes\Sources\Connector::MODBUS,
					));
				}),
				function (Throwable $ex) use ($message, $device, $channel, $property): void {
					$this->resetExpected($property);

					$this->logger->error(
						'Could not write state to device',
						[
							'source' => MetadataTypes\Sources\Connector::MODBUS->value,
							'type' => 'write-channel-property-state-message-consumer',
							'exception' => ApplicationHelpers\Logger::buildException($ex),
							'connector' => [
								'id' => $message->getConnector()->toString(),
							],
							'device' => [
								'id' => $device->getId()->toString(),
							],
							'channel' => [
								'id' => $channel->getId()->toString(),
							],
							'property' => [
								'id' => $property->getId()->toString(),
							],
							'data' => $message->toArray(),
						],
					);
				},
			);
		}

		$this->logger->debug(
			'Consumed write device state message',
			[
				'source' => MetadataTypes\Sources\Connector::MODBUS->value,
				'type' => 'write-channel-property-state-message-consumer',
				'connector' => [
					'id' => $message->getConnector()->toString(),
				],
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'channel' => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
				'data' => $message->toArray(),
			],
		);

		return true;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function resetExpected(DevicesDocuments\Channels\Properties\Dynamic $property): void
	{
		await($this->channelPropertiesStatesManager->setPendingState(
			$property,
			false,
			MetadataTypes\Sources\Connector::MODBUS,
		));
	}

}
