<?php declare(strict_types = 1);

/**
 * Tcp.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\Clients;

use DateTimeInterface;
use Exception;
use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Consumers;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Connector\Modbus\Writers;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\States as DevicesStates;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use InvalidArgumentException;
use Nette;
use Nette\Utils;
use Psr\Log;
use React\EventLoop;
use React\Promise;
use Throwable;
use function array_key_exists;
use function array_merge;
use function assert;
use function floatval;
use function in_array;
use function intval;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;
use function strval;

/**
 * Modbus TCP devices client interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Tcp implements Client
{

	use TReading;
	use Nette\SmartObject;

	private const LOST_DELAY = 5.0; // in s - Waiting delay before another communication with device after device was lost

	private const HANDLER_START_DELAY = 2.0;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	private bool $closed = true;

	/** @var array<string> */
	private array $processedDevices = [];

	/** @var array<string, DateTimeInterface> */
	private array $processedReadRegister = [];

	/** @var array<string, DateTimeInterface> */
	private array $lostDevices = [];

	private EventLoop\TimerInterface|null $handlerTimer;

	private API\Tcp|null $tcp = null;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly Entities\ModbusConnector $connector,
		private readonly API\TcpFactory $tcpFactory,
		private readonly API\Transformer $transformer,
		private readonly Helpers\Property $propertyStateHelper,
		private readonly Consumers\Messages $consumer,
		private readonly Writers\Writer $writer,
		private readonly DevicesUtilities\DeviceConnection $deviceConnectionManager,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStates,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function connect(): void
	{
		$this->tcp = $this->tcpFactory->create();

		$this->closed = false;

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->registerLoopHandler();
			},
		);

		$this->writer->connect($this->connector, $this);
	}

	public function disconnect(): void
	{
		$this->closed = true;

		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);
		}

		$this->writer->disconnect($this->connector, $this);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function writeChannelProperty(
		Entities\ModbusDevice $device,
		Entities\ModbusChannel $channel,
		DevicesEntities\Channels\Properties\Dynamic $property,
	): Promise\PromiseInterface
	{
		$state = $this->channelPropertiesStates->getValue($property);

		$ipAddress = $device->getIpAddress();

		if ($ipAddress === null) {
			$this->consumer->append(new Entities\Messages\DeviceState(
				$this->connector->getId(),
				$device->getIdentifier(),
				MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_STOPPED),
			));

			return Promise\reject(new Exceptions\InvalidState('Device ip address is not configured'));
		}

		$port = $device->getPort();

		$deviceAddress = $ipAddress . ':' . $port;

		$unitId = $device->getUnitId();

		$address = $channel->getAddress();

		if (!is_int($address)) {
			return Promise\reject(new Exceptions\InvalidState('Channel address is not configured'));
		}

		if (
			$state?->getExpectedValue() !== null
			&& $state->isPending() === true
		) {
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
				return Promise\reject(
					new Exceptions\NotSupported(
						sprintf(
							'Trying to write property with unsupported data type: %s for channel property',
							strval($deviceExpectedDataType->getValue()),
						),
					),
				);
			}

			$valueToWrite = $this->transformer->transformValueToDevice(
				$property->getDataType(),
				$property->getFormat(),
				$state->getExpectedValue(),
			);

			if ($valueToWrite === null) {
				return Promise\reject(new Exceptions\InvalidState('Value to write to register is invalid'));
			}

			$deferred = new Promise\Deferred();

			if ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
				if (in_array($valueToWrite->getValue(), [0, 1], true) || is_bool($valueToWrite->getValue())) {
					$promise = $this->tcp?->writeSingleCoil(
						$deviceAddress,
						$unitId,
						$address,
						is_bool(
							$valueToWrite->getValue(),
						) ? $valueToWrite->getValue() : $valueToWrite->getValue() === 1,
					);

				} else {
					return Promise\reject(
						new Exceptions\InvalidArgument(
							'Value for boolean property have to be 1/0 or true/false',
						),
					);
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
						$device->getByteOrder(),
					);

				} elseif (
					$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
					|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
				) {
					$bytes = $this->transformer->packUnsignedInt(
						intval($valueToWrite->getValue()),
						2,
						$device->getByteOrder(),
					);

				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)) {
					$bytes = $this->transformer->packSignedInt(
						intval($valueToWrite->getValue()),
						4,
						$device->getByteOrder(),
					);

				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)) {
					$bytes = $this->transformer->packUnsignedInt(
						intval($valueToWrite->getValue()),
						4,
						$device->getByteOrder(),
					);

				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
					$bytes = $this->transformer->packFloat(
						floatval($valueToWrite->getValue()),
						$device->getByteOrder(),
					);

				} else {
					return Promise\reject(new Exceptions\InvalidArgument('Provided data type is not supported'));
				}

				if ($bytes === null) {
					return Promise\reject(new Exceptions\InvalidState('Data could not be converted for write'));
				}

				$promise = $this->tcp?->writeSingleHolding(
					$deviceAddress,
					$unitId,
					$address,
					$bytes,
				);
			} else {
				return Promise\reject(
					new Exceptions\InvalidState(sprintf(
						'Unsupported value data type: %s',
						strval($valueToWrite->getDataType()->getValue()),
					)),
				);
			}

			$promise?->then(
				static function () use ($deferred): void {
					$deferred->resolve();
				},
				static function (Throwable $ex) use ($deferred): void {
					$deferred->reject($ex);
				},
			);

			return $deferred->promise();
		}

		return Promise\reject(new Exceptions\InvalidArgument('Provided property state is in invalid state'));
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exception
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function handleCommunication(): void
	{
		foreach ($this->connector->getDevices() as $device) {
			assert($device instanceof Entities\ModbusDevice);

			if (
				!in_array($device->getPlainId(), $this->processedDevices, true)
				&& !$this->deviceConnectionManager->getState($device)
					->equalsValue(MetadataTypes\ConnectionState::STATE_STOPPED)
			) {
				$deviceAddress = $device->getIpAddress();

				if (!is_string($deviceAddress)) {
					$this->consumer->append(new Entities\Messages\DeviceState(
						$this->connector->getId(),
						$device->getIdentifier(),
						MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_STOPPED),
					));

					continue;
				}

				// Check if device is lost or not
				if (array_key_exists($device->getPlainId(), $this->lostDevices)) {
					if (
						!$this->deviceConnectionManager->getState($device)
							->equalsValue(MetadataTypes\ConnectionState::STATE_LOST)
					) {
						$this->logger->debug(
							'Device is lost',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
								'type' => 'tcp-client',
								'group' => 'client',
								'connector' => [
									'id' => $this->connector->getPlainId(),
								],
								'device' => [
									'id' => $device->getPlainId(),
								],
							],
						);

						$this->consumer->append(new Entities\Messages\DeviceState(
							$this->connector->getId(),
							$device->getIdentifier(),
							MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_LOST),
						));
					}

					if (
						$this->dateTimeFactory->getNow()->getTimestamp() - $this->lostDevices[$device->getId()
							->toString()]->getTimestamp() < self::LOST_DELAY
					) {
						continue;
					}
				}

				// Check device state...
				if (
					!$this->deviceConnectionManager->getState($device)
						->equalsValue(Metadata\Types\ConnectionState::STATE_CONNECTED)
				) {
					// ... and if it is not ready, set it to ready
					$this->consumer->append(new Entities\Messages\DeviceState(
						$this->connector->getId(),
						$device->getIdentifier(),
						MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_CONNECTED),
					));
				}

				$this->processedDevices[] = $device->getPlainId();

				if ($this->processDevice($device)) {
					$this->registerLoopHandler();

					return;
				}
			}
		}

		$this->processedDevices = [];

		$this->registerLoopHandler();
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exception
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processDevice(Entities\ModbusDevice $device): bool
	{
		$ipAddress = $device->getIpAddress();
		assert(is_string($ipAddress));

		$port = $device->getPort();

		$deviceAddress = $ipAddress . ':' . $port;

		$unitId = $device->getUnitId();

		$coilsAddresses = $discreteInputsAddresses = $holdingAddresses = $inputsAddresses = [];

		foreach ($device->getChannels() as $channel) {
			$address = $channel->getAddress();

			if (!is_int($address)) {
				foreach ($channel->getProperties() as $property) {
					if (!$property instanceof DevicesEntities\Channels\Properties\Dynamic) {
						continue;
					}

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							DevicesStates\Property::VALID_KEY => false,
							DevicesStates\Property::EXPECTED_VALUE_KEY => null,
							DevicesStates\Property::PENDING_KEY => false,
						]),
					);
				}

				$this->logger->warning(
					'Channel address is missing',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'tcp-client',
						'group' => 'client',
						'connector' => [
							'id' => $this->connector->getPlainId(),
						],
						'device' => [
							'id' => $device->getPlainId(),
						],
						'channel' => [
							'id' => $channel->getPlainId(),
						],
					],
				);

				continue;
			}

			$registerReadAddress = $this->createReadAddress($device, $channel);

			if ($registerReadAddress instanceof Entities\Clients\ReadCoilAddress) {
				$coilsAddresses[] = $registerReadAddress;
			} elseif ($registerReadAddress instanceof Entities\Clients\ReadDiscreteInputAddress) {
				$discreteInputsAddresses[] = $registerReadAddress;
			} elseif ($registerReadAddress instanceof Entities\Clients\ReadHoldingRegisterAddress) {
				$holdingAddresses[] = $registerReadAddress;
			} elseif ($registerReadAddress instanceof Entities\Clients\ReadInputRegisterAddress) {
				$inputsAddresses[] = $registerReadAddress;
			}
		}

		if (
			$coilsAddresses === []
			&& $discreteInputsAddresses === []
			&& $holdingAddresses === []
			&& $inputsAddresses === []
		) {
			return false;
		}

		$requests = [];

		if ($coilsAddresses !== []) {
			$requests = array_merge(
				$requests,
				$this->split($coilsAddresses, Modbus\Constants::MAX_DISCRETE_REGISTERS_PER_MODBUS_REQUEST),
			);
		}

		if ($discreteInputsAddresses !== []) {
			$requests = array_merge(
				$requests,
				$this->split($discreteInputsAddresses, Modbus\Constants::MAX_DISCRETE_REGISTERS_PER_MODBUS_REQUEST),
			);
		}

		if ($holdingAddresses !== []) {
			$requests = array_merge(
				$requests,
				$this->split($holdingAddresses, Modbus\Constants::MAX_ANALOG_REGISTERS_PER_MODBUS_REQUEST),
			);
		}

		if ($inputsAddresses !== []) {
			$requests = array_merge(
				$requests,
				$this->split($inputsAddresses, Modbus\Constants::MAX_ANALOG_REGISTERS_PER_MODBUS_REQUEST),
			);
		}

		if ($requests === []) {
			return false;
		}

		$now = $this->dateTimeFactory->getNow();

		foreach ($requests as $request) {
			foreach ($request->getAddresses() as $requestAddress) {
				if ($request instanceof Entities\Clients\ReadCoilsRequest) {
					$channel = $device->findChannelByType(
						$requestAddress->getAddress(),
						Types\ChannelType::get(Types\ChannelType::COIL),
					);
				} elseif ($request instanceof Entities\Clients\ReadDiscreteInputsRequest) {
					$channel = $device->findChannelByType(
						$requestAddress->getAddress(),
						Types\ChannelType::get(Types\ChannelType::DISCRETE_INPUT),
					);
				} elseif ($request instanceof Entities\Clients\ReadHoldingsRegistersRequest) {
					$channel = $device->findChannelByType(
						$requestAddress->getAddress(),
						Types\ChannelType::get(Types\ChannelType::HOLDING_REGISTER),
					);
				} elseif ($request instanceof Entities\Clients\ReadInputsRegistersRequest) {
					$channel = $device->findChannelByType(
						$requestAddress->getAddress(),
						Types\ChannelType::get(Types\ChannelType::INPUT_REGISTER),
					);
				} else {
					continue;
				}

				if ($channel !== null) {
					$this->processedReadRegister[$channel->getIdentifier()] = $now;
				}
			}

			if ($request instanceof Entities\Clients\ReadCoilsRequest) {
				$promise = $this->tcp?->readCoils(
					$deviceAddress,
					$unitId,
					$request->getStartAddress(),
					$request->getQuantity(),
				);
			} elseif ($request instanceof Entities\Clients\ReadDiscreteInputsRequest) {
				$promise = $this->tcp?->readDiscreteInputs(
					$deviceAddress,
					$unitId,
					$request->getStartAddress(),
					$request->getQuantity(),
				);
			} elseif ($request instanceof Entities\Clients\ReadHoldingsRegistersRequest) {
				$promise = $this->tcp?->readHoldingRegisters(
					$deviceAddress,
					$unitId,
					$request->getStartAddress(),
					$request->getQuantity(),
				);
			} elseif ($request instanceof Entities\Clients\ReadInputsRegistersRequest) {
				$promise = $this->tcp?->readInputRegisters(
					$deviceAddress,
					$unitId,
					$request->getStartAddress(),
					$request->getQuantity(),
				);
			} else {
				continue;
			}

			$promise?->then(
				function (Entities\API\Entity $response) use ($request, $device): void {
					$now = $this->dateTimeFactory->getNow();

					if (
						!$response instanceof Entities\API\ReadDigitalInputs
						&& !$response instanceof Entities\API\ReadAnalogInputs
					) {
						return;
					}

					if ($response instanceof Entities\API\ReadDigitalInputs) {
						foreach ($response->getRegisters() as $address => $value) {
							if ($request instanceof Entities\Clients\ReadCoilsRequest) {
								$channel = $device->findChannelByType(
									$address,
									Types\ChannelType::get(Types\ChannelType::COIL),
								);
							} elseif ($request instanceof Entities\Clients\ReadDiscreteInputsRequest) {
								$channel = $device->findChannelByType(
									$address,
									Types\ChannelType::get(Types\ChannelType::DISCRETE_INPUT),
								);
							} else {
								continue;
							}

							if ($channel !== null) {
								$this->processedReadRegister[$channel->getIdentifier()] = $now;

								$property = $channel->findProperty(Types\ChannelPropertyIdentifier::IDENTIFIER_VALUE);

								if ($property instanceof DevicesEntities\Channels\Properties\Dynamic) {
									$this->propertyStateHelper->setValue(
										$property,
										Utils\ArrayHash::from([
											DevicesStates\Property::ACTUAL_VALUE_KEY => DevicesUtilities\ValueHelper::flattenValue(
												$this->transformer->transformValueFromDevice(
													$property->getDataType(),
													$property->getFormat(),
													$value,
												),
											),
											DevicesStates\Property::VALID_KEY => true,
										]),
									);
								}
							}
						}
					} else {
						$this->processAnalogRegistersResponse($request, $response, $device);
					}
				},
				function (Throwable $ex) use ($request, $device): void {
					$now = $this->dateTimeFactory->getNow();

					if ($ex instanceof Exceptions\ModbusTcp) {
						foreach ($request->getAddresses() as $requestAddress) {
							if ($request instanceof Entities\Clients\ReadCoilsRequest) {
								$channel = $device->findChannelByType(
									$requestAddress->getAddress(),
									Types\ChannelType::get(Types\ChannelType::COIL),
								);
							} elseif ($request instanceof Entities\Clients\ReadDiscreteInputsRequest) {
								$channel = $device->findChannelByType(
									$requestAddress->getAddress(),
									Types\ChannelType::get(Types\ChannelType::DISCRETE_INPUT),
								);
							} elseif ($request instanceof Entities\Clients\ReadHoldingsRegistersRequest) {
								$channel = $device->findChannelByType(
									$requestAddress->getAddress(),
									Types\ChannelType::get(Types\ChannelType::HOLDING_REGISTER),
								);
							} else {
								$channel = $device->findChannelByType(
									$requestAddress->getAddress(),
									Types\ChannelType::get(Types\ChannelType::INPUT_REGISTER),
								);
							}

							if ($channel !== null) {
								$this->processedReadRegister[$channel->getIdentifier()] = $now;

								$property = $channel->findProperty(Types\ChannelPropertyIdentifier::IDENTIFIER_VALUE);

								if ($property instanceof DevicesEntities\Channels\Properties\Dynamic) {
									$this->propertyStateHelper->setValue(
										$property,
										Utils\ArrayHash::from([
											DevicesStates\Property::VALID_KEY => false,
										]),
									);
								}

								$this->logger->error(
									'Could not handle register reading',
									[
										'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
										'type' => 'tcp-client',
										'group' => 'client',
										'exception' => [
											'message' => $ex->getMessage(),
											'code' => $ex->getCode(),
										],
										'connector' => [
											'id' => $this->connector->getPlainId(),
										],
										'device' => [
											'id' => $device->getPlainId(),
										],
										'channel' => [
											'id' => $channel->getPlainId(),
										],
									],
								);
							}
						}
					} else {
						$this->lostDevices[$device->getPlainId()] = $now;

						$this->logger->debug(
							'Device is lost',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
								'type' => 'tcp-client',
								'group' => 'client',
								'exception' => [
									'message' => $ex->getMessage(),
									'code' => $ex->getCode(),
								],
								'connector' => [
									'id' => $this->connector->getPlainId(),
								],
								'device' => [
									'id' => $device->getPlainId(),
								],
							],
						);

						$this->consumer->append(new Entities\Messages\DeviceState(
							$this->connector->getId(),
							$device->getIdentifier(),
							MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_LOST),
						));
					}
				},
			);
		}

		return true;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function createReadAddress(
		Entities\ModbusDevice $device,
		Entities\ModbusChannel $channel,
	): Entities\Clients\ReadAddress|null
	{
		$now = $this->dateTimeFactory->getNow();

		$property = $channel->findProperty(Types\ChannelPropertyIdentifier::IDENTIFIER_VALUE);

		if (
			!$property instanceof DevicesEntities\Channels\Properties\Dynamic
			|| !$property->isQueryable()
		) {
			return null;
		}

		$address = $channel->getAddress();

		if ($address === null) {
			return null;
		}

		if (
			array_key_exists($channel->getIdentifier(), $this->processedReadRegister)
			&& $now->getTimestamp() - $this->processedReadRegister[$channel->getIdentifier()]->getTimestamp() < $channel->getReadingDelay()
		) {
			return null;
		}

		$deviceExpectedDataType = $this->transformer->determineDeviceReadDataType(
			$property->getDataType(),
			$property->getFormat(),
		);

		if ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
			return $property->isSettable()
				? new Entities\Clients\ReadCoilAddress($address, $channel, $deviceExpectedDataType)
				: new Entities\Clients\ReadDiscreteInputAddress($address, $channel, $deviceExpectedDataType);
		} elseif (
			$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
			|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
			|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
			|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
			|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
			|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
			|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)
		) {
			return $property->isSettable()
				? new Entities\Clients\ReadHoldingRegisterAddress($address, $channel, $deviceExpectedDataType)
				: new Entities\Clients\ReadInputRegisterAddress($address, $channel, $deviceExpectedDataType);
		}

		$this->logger->warning(
			'Channel property data type is not supported for now',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
				'type' => 'tcp-client',
				'group' => 'client',
				'connector' => [
					'id' => $this->connector->getPlainId(),
				],
				'device' => [
					'id' => $device->getPlainId(),
				],
				'channel' => [
					'id' => $channel->getPlainId(),
				],
				'property' => [
					'id' => $property->getPlainId(),
				],
			],
		);

		return null;
	}

	private function registerLoopHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			function (): void {
				if ($this->closed) {
					return;
				}

				$this->handleCommunication();
			},
		);
	}

}
