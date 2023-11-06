<?php declare(strict_types = 1);

/**
 * Rtu.php
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
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\States as DevicesStates;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Nette\Utils;
use Psr\Log;
use React\EventLoop;
use React\Promise;
use function array_key_exists;
use function array_merge;
use function assert;
use function floatval;
use function get_loaded_extensions;
use function in_array;
use function intval;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_object;
use function sprintf;
use function strval;

/**
 * Modbus RTU devices client interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Rtu implements Client
{

	use TReading;
	use Nette\SmartObject;

	private const READ_MAX_ATTEMPTS = 5;

	private const LOST_DELAY = 5.0; // in s - Waiting delay before another communication with device after device was lost

	private const HANDLER_START_DELAY = 2.0;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	private bool $closed = true;

	/** @var array<string> */
	private array $processedDevices = [];

	/** @var array<string, DateTimeInterface|int> */
	private array $processedReadRegister = [];

	/** @var array<string, DateTimeInterface> */
	private array $lostDevices = [];

	private EventLoop\TimerInterface|null $handlerTimer;

	private API\Interfaces\Serial|null $interface = null;

	private API\Rtu|null $rtu = null;

	public function __construct(
		private readonly Entities\ModbusConnector $connector,
		private readonly API\RtuFactory $rtuFactory,
		private readonly API\Transformer $transformer,
		private readonly Helpers\Property $propertyStateHelper,
		private readonly Consumers\Messages $consumer,
		private readonly Writers\Writer $writer,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Entities\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelPropertiesRepository,
		private readonly DevicesUtilities\DeviceConnection $deviceConnectionManager,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStates,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
		private readonly Log\LoggerInterface $logger = new Log\NullLogger(),
	)
	{
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function connect(): void
	{
		$configuration = new API\Interfaces\Configuration(
			$this->connector->getBaudRate(),
			$this->connector->getByteSize(),
			$this->connector->getStopBits(),
			$this->connector->getParity(),
			false,
			false,
		);

		$useDio = false;

		foreach (get_loaded_extensions() as $extension) {
			if (Utils\Strings::contains('dio', Utils\Strings::lower($extension))) {
				$useDio = true;

				break;
			}
		}

		$this->interface = $useDio
			? new API\Interfaces\SerialDio($this->connector->getRtuInterface(), $configuration)
			: new API\Interfaces\SerialFile($this->connector->getRtuInterface(), $configuration);

		$this->interface->open();

		$this->rtu = $this->rtuFactory->create($this->interface);

		$this->closed = false;

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->registerLoopHandler();
			},
		);

		$this->writer->connect($this->connector, $this);
	}

	/**
	 * @throws Exceptions\InvalidState
	 */
	public function disconnect(): void
	{
		$this->closed = true;

		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);
		}

		$this->interface?->close();

		$this->writer->disconnect($this->connector, $this);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exception
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\NotReachable
	 * @throws Exceptions\NotSupported
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function writeChannelProperty(
		Entities\ModbusDevice $device,
		Entities\ModbusChannel $channel,
		DevicesEntities\Channels\Properties\Dynamic|MetadataEntities\DevicesModule\ChannelDynamicProperty $property,
	): Promise\PromiseInterface
	{
		$state = $this->channelPropertiesStates->getValue($property);

		$station = $device->getAddress();

		if (!is_numeric($station)) {
			$this->consumer->append(new Entities\Messages\DeviceState(
				$this->connector->getId(),
				$device->getIdentifier(),
				MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_STOPPED),
			));

			return Promise\reject(new Exceptions\InvalidState('Device address is not configured'));
		}

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

			try {
				if ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
					if (in_array($valueToWrite->getValue(), [0, 1], true) || is_bool($valueToWrite->getValue())) {
						$this->rtu?->writeSingleCoil(
							$station,
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

					$this->rtu?->writeSingleHolding($station, $address, $bytes);
				} else {
					return Promise\reject(
						new Exceptions\InvalidState(sprintf(
							'Unsupported value data type: %s',
							strval($valueToWrite->getDataType()->getValue()),
						)),
					);
				}
			} catch (Exceptions\ModbusRtu $ex) {
				return Promise\reject($ex);
			}

			// Register writing failed
			return Promise\resolve();
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
		$findDevicesQuery = new DevicesQueries\FindDevices();
		$findDevicesQuery->forConnector($this->connector);

		foreach ($this->devicesRepository->findAllBy($findDevicesQuery, Entities\ModbusDevice::class) as $device) {
			assert($device instanceof Entities\ModbusDevice);

			if (
				!in_array($device->getPlainId(), $this->processedDevices, true)
				&& !$this->deviceConnectionManager->getState($device)
					->equalsValue(MetadataTypes\ConnectionState::STATE_STOPPED)
			) {
				$deviceAddress = $device->getAddress();

				if (!is_int($deviceAddress)) {
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
						$this->logger->warning(
							'Device is lost',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
								'type' => 'rtu-client',
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
						// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
						$this->dateTimeFactory->getNow()->getTimestamp() - $this->lostDevices[$device->getPlainId()]->getTimestamp() < self::LOST_DELAY
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
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processDevice(Entities\ModbusDevice $device): bool
	{
		$station = $device->getAddress();
		assert(is_numeric($station));

		$coilsAddresses = $discreteInputsAddresses = $holdingAddresses = $inputsAddresses = [];

		$findChannelsQuery = new DevicesQueries\FindChannels();
		$findChannelsQuery->forDevice($device);

		foreach ($this->channelsRepository->findAllBy($findChannelsQuery, Entities\ModbusChannel::class) as $channel) {
			assert($channel instanceof Entities\ModbusChannel);

			$address = $channel->getAddress();

			if (!is_int($address)) {
				$findChannelPropertiesQuery = new DevicesQueries\FindChannelProperties();
				$findChannelPropertiesQuery->forChannel($channel);

				foreach ($this->channelPropertiesRepository->findAllBy($findChannelPropertiesQuery) as $property) {
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
						'type' => 'rtu-client',
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

		foreach ($requests as $request) {
			try {
				if ($request instanceof Entities\Clients\ReadCoilsRequest) {
					$response = $this->rtu?->readCoils(
						$station,
						$request->getStartAddress(),
						$request->getQuantity(),
					);
				} elseif ($request instanceof Entities\Clients\ReadDiscreteInputsRequest) {
					$response = $this->rtu?->readDiscreteInputs(
						$station,
						$request->getStartAddress(),
						$request->getQuantity(),
					);
				} elseif ($request instanceof Entities\Clients\ReadHoldingsRegistersRequest) {
					$response = $this->rtu?->readHoldingRegisters(
						$station,
						$request->getStartAddress(),
						$request->getQuantity(),
					);
				} elseif ($request instanceof Entities\Clients\ReadInputsRegistersRequest) {
					$response = $this->rtu?->readInputRegisters(
						$station,
						$request->getStartAddress(),
						$request->getQuantity(),
					);
				} else {
					continue;
				}

				if (is_object($response)) {
					$now = $this->dateTimeFactory->getNow();

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

								$findChannelPropertyQuery = new DevicesQueries\FindChannelProperties();
								$findChannelPropertyQuery->forChannel($channel);
								$findChannelPropertyQuery->byIdentifier(
									Types\ChannelPropertyIdentifier::IDENTIFIER_VALUE,
								);

								$property = $this->channelPropertiesRepository->findOneBy($findChannelPropertyQuery);

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

						foreach ($response->getRegisters() as $address => $value) {
							if ($request instanceof Entities\Clients\ReadHoldingsRegistersRequest) {
								$channel = $device->findChannelByType(
									$address,
									Types\ChannelType::get(Types\ChannelType::HOLDING_REGISTER),
								);
							} elseif ($request instanceof Entities\Clients\ReadInputsRegistersRequest) {
								$channel = $device->findChannelByType(
									$address,
									Types\ChannelType::get(Types\ChannelType::INPUT_REGISTER),
								);
							} else {
								continue;
							}

							if ($channel !== null) {
								$this->processedReadRegister[$channel->getIdentifier()] = $now;
							}
						}
					}
				}
			} catch (Exceptions\ModbusRtu $ex) {
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
						$findChannelPropertyQuery = new DevicesQueries\FindChannelProperties();
						$findChannelPropertyQuery->forChannel($channel);
						$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::IDENTIFIER_VALUE);

						$property = $this->channelPropertiesRepository->findOneBy($findChannelPropertyQuery);

						if ($property instanceof DevicesEntities\Channels\Properties\Dynamic) {
							$this->propertyStateHelper->setValue(
								$property,
								Utils\ArrayHash::from([
									DevicesStates\Property::VALID_KEY => false,
								]),
							);
						}

						// Increment failed attempts counter
						if (!array_key_exists($channel->getIdentifier(), $this->processedReadRegister)) {
							$this->processedReadRegister[$channel->getIdentifier()] = 1;
						} else {
							$this->processedReadRegister[$channel->getIdentifier()] = is_int(
								$this->processedReadRegister[$channel->getIdentifier()],
							)
								? $this->processedReadRegister[$channel->getIdentifier()] + 1
								: 1;
						}
					}
				}

				$this->logger->error(
					'Could not handle register reading',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
						'type' => 'tcp-client',
						'exception' => BootstrapHelpers\Logger::buildException($ex),
						'connector' => [
							'id' => $this->connector->getPlainId(),
						],
						'device' => [
							'id' => $device->getPlainId(),
						],
					],
				);

				// Something wrong during communication
				return true;
			}
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

		$findChannelPropertyQuery = new DevicesQueries\FindChannelProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::IDENTIFIER_VALUE);

		$property = $this->channelPropertiesRepository->findOneBy($findChannelPropertyQuery);

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
			isset($this->processedReadRegister[$channel->getIdentifier()])
			&& is_int($this->processedReadRegister[$channel->getIdentifier()])
			&& $this->processedReadRegister[$channel->getIdentifier()] >= self::READ_MAX_ATTEMPTS
		) {
			unset($this->processedReadRegister[$channel->getIdentifier()]);

			$this->lostDevices[$device->getPlainId()] = $now;

			$this->consumer->append(new Entities\Messages\DeviceState(
				$this->connector->getId(),
				$device->getIdentifier(),
				MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_LOST),
			));

			$this->logger->warning(
				'Maximum channel property read attempts reached',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'rtu-client',
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

		if (
			array_key_exists($channel->getIdentifier(), $this->processedReadRegister)
			&& $this->processedReadRegister[$channel->getIdentifier()] instanceof DateTimeInterface
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
				'type' => 'rtu-client',
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
