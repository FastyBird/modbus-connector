<?php declare(strict_types = 1);

/**
 * Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\Clients;

use DateTimeInterface;
use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Clients;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use Nette;
use Nette\Utils;
use Psr\Log;
use React\EventLoop;
use Throwable;
use function array_chunk;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_reverse;
use function array_slice;
use function array_values;
use function current;
use function func_get_args;
use function func_num_args;
use function get_loaded_extensions;
use function in_array;
use function intval;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function pack;
use function sprintf;
use function strlen;
use function strval;
use function substr;
use function unpack;
use function usleep;

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

	use Nette\SmartObject;

	private const MODBUS_ADU = 'C1station/C1function/C*data/';

	private const MODBUS_ERROR = 'C1station/C1error/C1exception/';

	private const WRITE_DEBOUNCE_DELAY = 500; // in ms

	private const WRITE_PENDING_DELAY = 2_000; // in ms

	private const WRITE_MAX_ATTEMPTS = 5;

	private const READ_DELAY = 10; // in s

	private const READ_MAX_ATTEMPTS = 5;

	private const LOST_DELAY = 5; // in s - Waiting delay before another communication with device after device was lost

	private const HANDLER_START_DELAY = 2;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	private const FUNCTION_CODE_READ_COIL = 0x01;

	private const FUNCTION_CODE_READ_DISCRETE = 0x02;

	private const FUNCTION_CODE_READ_HOLDING = 0x03;

	private const FUNCTION_CODE_READ_INPUT = 0x04;

	private const FUNCTION_CODE_WRITE_SINGLE_COIL = 0x05;

	private const FUNCTION_CODE_WRITE_SINGLE_HOLDING = 0x06;

	private const FUNCTION_CODE_WRITE_MULTIPLE_COILS = 0x15;

	private const FUNCTION_CODE_WRITE_MULTIPLE_HOLDINGS = 0x16;

	private bool $closed = true;

	/** @var Array<string> */
	private array $processedDevices = [];

	/** @var Array<string, DateTimeInterface> */
	private array $lostDevices = [];

	/** @var Array<string, DateTimeInterface|int> */
	private array $processedWrittenProperties = [];

	/** @var Array<string, DateTimeInterface|int> */
	private array $processedReadProperties = [];

	private bool|null $machineUsingLittleEndian = null;

	private EventLoop\TimerInterface|null $handlerTimer;

	private Clients\Interfaces\Serial|null $interface;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly MetadataEntities\DevicesModule\Connector $connector,
		private readonly Helpers\Connector $connectorHelper,
		private readonly Helpers\Device $deviceHelper,
		private readonly Helpers\Channel $channelHelper,
		private readonly Helpers\Property $propertyStateHelper,
		private readonly API\Transformer $transformer,
		private readonly DevicesModels\DataStorage\DevicesRepository $devicesRepository,
		private readonly DevicesModels\DataStorage\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\DataStorage\ChannelPropertiesRepository $channelPropertiesRepository,
		private readonly DevicesModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function connect(): void
	{
		$configuration = new Clients\Interfaces\Configuration(
			Types\BaudRate::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(
					Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BAUD_RATE,
				),
			)),
			Types\ByteSize::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(
					Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BYTE_SIZE,
				),
			)),
			Types\StopBits::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(
					Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_STOP_BITS,
				),
			)),
			Types\Parity::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(
					Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_PARITY,
				),
			)),
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

		$this->interface = $useDio ? new Clients\Interfaces\SerialDio(
			(string) $this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(
					Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_INTERFACE,
				),
			),
			$configuration,
		) : new Clients\Interfaces\SerialFile(
			(string) $this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifier::get(
					Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_INTERFACE,
				),
			),
			$configuration,
		);

		$this->interface->open();

		$this->closed = false;

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->registerLoopHandler();
			},
		);
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
	}

	/**
	 * @throws MetadataExceptions\FileNotFound
	 * @throws Throwable
	 */
	private function handleCommunication(): void
	{
		foreach ($this->processedWrittenProperties as $index => $processedProperty) {
			if (
				$processedProperty instanceof DateTimeInterface
				&& ((float) $this->dateTimeFactory->getNow()->format('Uv') - (float) $processedProperty->format(
					'Uv',
				)) >= self::WRITE_DEBOUNCE_DELAY
			) {
				unset($this->processedWrittenProperties[$index]);
			}
		}

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			if (
				!in_array($device->getId()->toString(), $this->processedDevices, true)
				&& !$this->deviceConnectionStateManager->getState($device)
					->equalsValue(MetadataTypes\ConnectionState::STATE_STOPPED)
			) {
				$deviceAddress = $this->deviceHelper->getConfiguration(
					$device->getId(),
					Types\DevicePropertyIdentifier::get(
						Types\DevicePropertyIdentifier::IDENTIFIER_ADDRESS,
					),
				);

				if (!is_int($deviceAddress)) {
					$this->deviceConnectionStateManager->setState(
						$device,
						MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_STOPPED),
					);

					continue;
				}

				// Check if device is lost or not
				if (array_key_exists($device->getId()->toString(), $this->lostDevices)) {
					if ($this->deviceConnectionStateManager->getState($device)
						->equalsValue(MetadataTypes\ConnectionState::STATE_LOST)) {
						$this->logger->debug(
							'Device is still lost',
							[
								'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
								'type' => 'rtu-client',
								'connector' => [
									'id' => $this->connector->getId()->toString(),
								],
								'device' => [
									'id' => $device->getId()->toString(),
								],
							],
						);

					} else {
						$this->logger->debug(
							'Device is lost',
							[
								'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
								'type' => 'rtu-client',
								'connector' => [
									'id' => $this->connector->getId()->toString(),
								],
								'device' => [
									'id' => $device->getId()->toString(),
								],
							],
						);

						$this->deviceConnectionStateManager->setState(
							$device,
							MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_LOST),
						);
					}

					if ($this->dateTimeFactory->getNow()->getTimestamp() - $this->lostDevices[$device->getId()
						->toString()]->getTimestamp() < self::LOST_DELAY) {
						continue;
					}
				}

				// Check device state...
				if (
					!$this->deviceConnectionStateManager->getState($device)
						->equalsValue(Metadata\Types\ConnectionState::STATE_CONNECTED)
				) {
					// ... and if it is not ready, set it to ready
					$this->deviceConnectionStateManager->setState(
						$device,
						Metadata\Types\ConnectionState::get(Metadata\Types\ConnectionState::STATE_CONNECTED),
					);
				}

				$this->processedDevices[] = $device->getId()->toString();

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
	 * @throws MetadataExceptions\FileNotFound
	 * @throws Throwable
	 */
	private function processDevice(MetadataEntities\DevicesModule\Device $device): bool
	{
		$station = (int) $this->deviceHelper->getConfiguration(
			$device->getId(),
			Types\DevicePropertyIdentifier::get(
				Types\DevicePropertyIdentifier::IDENTIFIER_ADDRESS,
			),
		);

		foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
			$address = $this->channelHelper->getConfiguration(
				$channel->getId(),
				Types\DevicePropertyIdentifier::get(
					Types\ChannelPropertyIdentifier::IDENTIFIER_ADDRESS,
				),
			);

			if (!is_int($address)) {
				foreach ($this->channelPropertiesRepository->findAllByChannel(
					$channel->getId(),
					MetadataEntities\DevicesModule\ChannelDynamicProperty::class,
				) as $property) {
					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
							'expectedValue' => null,
							'pending' => false,
						]),
					);
				}

				$this->logger->warning(
					'Channel address is missing',
					[
						'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
						'type' => 'rtu-client',
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
						'channel' => [
							'id' => $channel->getId()->toString(),
						],
					],
				);

				continue;
			}

			foreach ($this->channelPropertiesRepository->findAllByChannel(
				$channel->getId(),
				MetadataEntities\DevicesModule\ChannelDynamicProperty::class,
			) as $property) {
				$logContext = [
					'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
					'type' => 'rtu-client',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
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
				];

				/**
				 * Channel property writing
				 */

				try {
					$result = $this->writeProperty($station, $address, $device, $property);

					if ($result) {
						return true;
					}
				} catch (Exceptions\InvalidArgument $ex) {
					$this->logger->warning(
						'Channel property value could not be written',
						array_merge($logContext, [
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						]),
					);
				} catch (Exceptions\NotSupported $ex) {
					$this->logger->warning(
						'Channel property value is not supported for now',
						array_merge($logContext, [
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						]),
					);
				} catch (Exceptions\ModbusRtu $ex) {
					$this->logger->error(
						'Modbus communication with device failed',
						array_merge($logContext, [
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						]),
					);

					// Something wrong during communication
					return true;
				} catch (Exceptions\NotReachable $ex) {
					$this->logger->error(
						'Maximum channel property write attempts reached',
						array_merge($logContext, [
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						]),
					);

					// Device is probably offline
					return true;
				}

				/**
				 * Channel property reading
				 */

				try {
					$result = $this->readProperty($station, $address, $device, $property);

					if ($result) {
						return true;
					}
				} catch (Exceptions\InvalidArgument $ex) {
					$this->logger->warning(
						'Channel property value could not be read',
						array_merge($logContext, [
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						]),
					);
				} catch (Exceptions\NotSupported $ex) {
					$this->logger->warning(
						'Channel property data type is not supported for now',
						array_merge($logContext, [
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						]),
					);
				} catch (Exceptions\ModbusRtu $ex) {
					$this->logger->error(
						'Modbus communication with device failed',
						array_merge($logContext, [
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						]),
					);

					// Something wrong during communication
					return true;
				} catch (Exceptions\NotReachable $ex) {
					$this->logger->error(
						'Maximum channel property read attempts reached',
						array_merge($logContext, [
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						]),
					);

					// Device is probably offline
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\NotReachable
	 * @throws Exceptions\NotSupported
	 * @throws Throwable
	 */
	private function writeProperty(
		int $station,
		int $address,
		MetadataEntities\DevicesModule\Device $device,
		MetadataEntities\DevicesModule\Property $property,
	): bool
	{
		$now = $this->dateTimeFactory->getNow();

		$propertyUuid = $property->getId()->toString();

		if (
			(
				// Only dynamic properties could be processed
				$property instanceof MetadataEntities\DevicesModule\DeviceDynamicProperty
				|| $property instanceof MetadataEntities\DevicesModule\ChannelDynamicProperty
			)
			// Property have to be writable
			&& $property->isSettable()
			&& $property->getExpectedValue() !== null
			&& $property->isPending() === true
		) {
			if (!in_array($property->getDataType()->getValue(), [
				MetadataTypes\DataType::DATA_TYPE_CHAR,
				MetadataTypes\DataType::DATA_TYPE_SHORT,
				MetadataTypes\DataType::DATA_TYPE_INT,
				MetadataTypes\DataType::DATA_TYPE_UCHAR,
				MetadataTypes\DataType::DATA_TYPE_USHORT,
				MetadataTypes\DataType::DATA_TYPE_UINT,
				MetadataTypes\DataType::DATA_TYPE_FLOAT,
				MetadataTypes\DataType::DATA_TYPE_BOOLEAN,
				MetadataTypes\DataType::DATA_TYPE_STRING,
				MetadataTypes\DataType::DATA_TYPE_ENUM,
				MetadataTypes\DataType::DATA_TYPE_SWITCH,
				MetadataTypes\DataType::DATA_TYPE_BUTTON,
			], true)) {
				unset($this->processedWrittenProperties[$propertyUuid]);

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'expectedValue' => null,
						'pending' => false,
					]),
				);

				throw new Exceptions\InvalidArgument(
					sprintf(
						'Trying to write property with unsupported data type: %s for channel property',
						strval($property->getDataType()->getValue()),
					),
				);
			}

			if (
				isset($this->processedWrittenProperties[$propertyUuid])
				&& is_int($this->processedWrittenProperties[$propertyUuid])
				&& $this->processedWrittenProperties[$propertyUuid] > self::WRITE_MAX_ATTEMPTS
			) {
				unset($this->processedWrittenProperties[$propertyUuid]);

				$this->lostDevices[$device->getId()->toString()] = $now;

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'expectedValue' => null,
						'pending' => false,
					]),
				);

				throw new Exceptions\NotReachable('Maximum writing attempts reached');
			}

			if (
				array_key_exists($propertyUuid, $this->processedWrittenProperties)
				&& $this->processedWrittenProperties[$propertyUuid] instanceof DateTimeInterface
				&& (float) $now->format('Uv') - (float) $this->processedWrittenProperties[$propertyUuid]->format(
					'Uv',
				) < self::WRITE_DEBOUNCE_DELAY
			) {
				return false;
			}

			$pending = is_string($property->getPending())
				? Utils\DateTime::createFromFormat(
					DateTimeInterface::ATOM,
					$property->getPending(),
				)
				: true;

			if (
				$pending === true
				|| (
					$pending !== false
					&& (float) $now->format('Uv') - (float) $pending->format('Uv') > self::WRITE_PENDING_DELAY
				)
			) {
				$valueToWrite = $this->transformer->transformValueToDevice(
					$property->getDataType(),
					$property->getFormat(),
					$property->getExpectedValue(),
				);

				if ($valueToWrite === null) {
					return false;
				}

				try {
					if ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
						if (in_array($valueToWrite->getValue(), [0, 1], true) || is_bool($valueToWrite->getValue())) {
							$result = $this->writeSingleCoil(
								$station,
								$address,
								is_bool(
									$valueToWrite->getValue(),
								) ? $valueToWrite->getValue() : $valueToWrite->getValue() === 1,
							);

						} else {
							unset($this->processedWrittenProperties[$propertyUuid]);

							$this->propertyStateHelper->setValue(
								$property,
								Utils\ArrayHash::from([
									'expectedValue' => null,
									'pending' => false,
								]),
							);

							throw new Exceptions\InvalidState(
								'Value for boolean property have to be 1/0 or true/false',
							);
						}
					} elseif ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending' => false,
							]),
						);

						throw new Exceptions\NotSupported('Float value is not supported for now');
					} elseif (
						$valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
						|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
					) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending' => false,
							]),
						);

						throw new Exceptions\NotSupported('Long integer value is not supported for now');
					} elseif (
						$valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
						|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
						|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
						|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
					) {
						$result = $this->writeSingleRegister(
							$station,
							$address,
							(int) $valueToWrite->getValue(),
							$property->getNumberOfDecimals(),
							(
								$valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
								|| $valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
							),
						);

					} elseif ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_STRING)) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending' => false,
							]),
						);

						throw new Exceptions\NotSupported('String value is not supported for now');
					} elseif ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending' => false,
							]),
						);

						throw new Exceptions\NotSupported('Simple switch value is not supported for now');
					} elseif ($valueToWrite->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending' => false,
							]),
						);

						throw new Exceptions\NotSupported('Simple button value is not supported for now');
					} else {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending' => false,
							]),
						);

						throw new Exceptions\InvalidState(sprintf(
							'Unsupported value data type: %s',
							strval($valueToWrite->getDataType()->getValue()),
						));
					}
				} catch (Exceptions\ModbusRtu $ex) {
					unset($this->processedWrittenProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'expectedValue' => null,
							'pending' => false,
						]),
					);

					throw $ex;
				}

				// Register writing failed
				if ($result === false) {
					// Increment failed attempts counter
					if (!array_key_exists($propertyUuid, $this->processedWrittenProperties)) {
						$this->processedWrittenProperties[$propertyUuid] = 1;
					} else {
						$this->processedWrittenProperties[$propertyUuid] = is_int(
							$this->processedWrittenProperties[$propertyUuid],
						)
							? $this->processedWrittenProperties[$propertyUuid] + 1
							: 1;
					}
				} else {
					$this->processedWrittenProperties[$propertyUuid] = $now;

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'pending' => $this->dateTimeFactory->getNow()->format(DateTimeInterface::ATOM),
						]),
					);
				}

				return $result !== false;
			}
		}

		return false;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\NotReachable
	 * @throws Exceptions\NotSupported
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function readProperty(
		int $station,
		int $address,
		MetadataEntities\DevicesModule\Device $device,
		MetadataEntities\DevicesModule\Property $property,
	): bool
	{
		$now = $this->dateTimeFactory->getNow();

		$propertyUuid = $property->getId()->toString();

		if (
			(
				// Only dynamic properties could be processed
				$property instanceof MetadataEntities\DevicesModule\DeviceDynamicProperty
				|| $property instanceof MetadataEntities\DevicesModule\ChannelDynamicProperty
			)
			// Property have to be readable
			&& $property->isQueryable()
		) {
			$deviceExpectedDataType = $this->transformer->determineDeviceReadDataType(
				$property->getDataType(),
				$property->getFormat(),
			);

			if (!in_array($deviceExpectedDataType->getValue(), [
				MetadataTypes\DataType::DATA_TYPE_CHAR,
				MetadataTypes\DataType::DATA_TYPE_SHORT,
				MetadataTypes\DataType::DATA_TYPE_INT,
				MetadataTypes\DataType::DATA_TYPE_UCHAR,
				MetadataTypes\DataType::DATA_TYPE_USHORT,
				MetadataTypes\DataType::DATA_TYPE_UINT,
				MetadataTypes\DataType::DATA_TYPE_FLOAT,
				MetadataTypes\DataType::DATA_TYPE_BOOLEAN,
				MetadataTypes\DataType::DATA_TYPE_STRING,
				MetadataTypes\DataType::DATA_TYPE_ENUM,
				MetadataTypes\DataType::DATA_TYPE_SWITCH,
				MetadataTypes\DataType::DATA_TYPE_BUTTON,
			], true)) {
				unset($this->processedReadProperties[$propertyUuid]);

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'valid' => false,
					]),
				);

				throw new Exceptions\InvalidArgument(
					sprintf(
						'Trying to write property with unsupported data type: %s for channel property',
						strval($property->getDataType()->getValue()),
					),
				);
			}

			if (
				isset($this->processedReadProperties[$propertyUuid])
				&& is_int($this->processedReadProperties[$propertyUuid])
				&& $this->processedReadProperties[$propertyUuid] > self::READ_MAX_ATTEMPTS
			) {
				unset($this->processedReadProperties[$propertyUuid]);

				$this->lostDevices[$device->getId()->toString()] = $now;

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'valid' => false,
					]),
				);

				throw new Exceptions\NotReachable('Maximum writing attempts reached');
			}

			if (
				array_key_exists($propertyUuid, $this->processedReadProperties)
				&& $this->processedReadProperties[$propertyUuid] instanceof DateTimeInterface
				&& $now->getTimestamp() - $this->processedReadProperties[$propertyUuid]->getTimestamp() < self::READ_DELAY
			) {
				return false;
			}

			try {
				if ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
					$result = $property->isSettable()
						? $this->readCoils($station, $address, 1)
						: $this->readDiscreteInputs($station, $address, 1);

					$value = !is_array($result) || !array_key_exists('registers', $result) || !is_array(
						$result['registers'],
					)
						? false
						: $result['registers'][0];

				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						]),
					);

					throw new Exceptions\NotSupported('Float data type is not supported for now');
				} elseif (
					$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
					|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
				) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						]),
					);

					throw new Exceptions\NotSupported('Long integer data type is not supported for now');
				} elseif (
					$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
					|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
					|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
					|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
				) {
					$result = $property->isSettable() ? $this->readHoldingRegisters(
						$station,
						$address,
						1,
						$property->getNumberOfDecimals(),
						(
								$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
								|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
							),
					) : $this->readInputRegisters(
						$station,
						$address,
						1,
						$property->getNumberOfDecimals(),
						(
								$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
								|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
							),
					);

					$value = !is_array($result) || !array_key_exists('registers', $result) || !is_array(
						$result['registers'],
					)
						? false
						: $result['registers'][0];

				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_STRING)) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						]),
					);

					throw new Exceptions\NotSupported('String data type is not supported for now');
				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_ENUM)) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						]),
					);

					throw new Exceptions\NotSupported('Enum data type is not supported for now');
				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						]),
					);

					throw new Exceptions\NotSupported('Simple switch data type is not supported for now');
				} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						]),
					);

					throw new Exceptions\NotSupported('Simple button data type is not supported for now');
				} else {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						]),
					);

					throw new Exceptions\InvalidState('Unsupported data type');
				}
			} catch (Exceptions\ModbusRtu $ex) {
				unset($this->processedReadProperties[$propertyUuid]);

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'valid' => false,
					]),
				);

				throw $ex;
			}

			// Register reading failed
			if ($value === false) {
				// Increment failed attempts counter
				if (!array_key_exists($propertyUuid, $this->processedReadProperties)) {
					$this->processedReadProperties[$propertyUuid] = 1;
				} else {
					$this->processedReadProperties[$propertyUuid] = is_int(
						$this->processedReadProperties[$propertyUuid],
					)
						? $this->processedReadProperties[$propertyUuid] + 1
						: 1;
				}

				// Mark value as invalid
				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'valid' => false,
					]),
				);

			} else {
				$this->processedReadProperties[$propertyUuid] = $now;

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'actualValue' => $this->transformer->transformValueFromDevice(
							$property->getDataType(),
							$property->getFormat(),
							$value,
						),
						'valid' => true,
					]),
				);
			}

			return $value !== false;
		}

		return false;
	}

	/**
	 * (0x01) Read Coils
	 *
	 * This function code is used to read from 1 to 2000 contiguous status of coils in a remote device.
	 * The Request PDU specifies the starting address, i.e. the address of the first coil specified,
	 * and the number of coils. In the PDU Coils are addressed starting at zero, therefore coils
	 * numbered 1-16 are addressed as 0-15.
	 *
	 * The coils in the response message are packed as one coil per a bit of the data field.
	 * Status is indicated as 1= ON and 0= OFF. The LSB of the first data byte contains the output
	 * addressed in the query. The other coils follow toward the high order end of this byte,
	 * and from low order to high order in subsequent bytes.
	 *
	 * If the returned output quantity is not a multiple of eight, the remaining bits in the final data byte
	 * will be padded with zeros (toward the high order end of the byte). The Byte Count field specifies
	 * the quantity of complete bytes of data.
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of coils (n1)
	 *
	 * @return Array<string, int|Array<int, int>>|string|false
	 * [
	 *    'station'  => $station,
	 *    'function' => 0x01,
	 *    'count'    => $count,
	 *    'status'   => [],
	 * ]
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function readCoils(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false,
	): string|array|false
	{
		$request = pack('C2n2', $station, self::FUNCTION_CODE_READ_COIL, $startingAddress, $quantity);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count', $response);

			if ($unpacked === false) {
				return false;
			}

			$statusUnpacked = unpack('C*', substr($response, 3, -2));

			if ($statusUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['status' => array_values($statusUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x02) Read Discrete Inputs
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Inputs (n1)
	 *
	 * @return Array<string, int|Array<int, int>>|string|false
	 * [
	 *    'station'  => $station,
	 *    'function' => 0x02,
	 *    'count'    => $count,
	 *    'status'   => [],
	 * ]
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function readDiscreteInputs(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false,
	): string|array|false
	{
		$request = pack('C2n2', $station, self::FUNCTION_CODE_READ_DISCRETE, $startingAddress, $quantity);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count', $response);

			if ($unpacked === false) {
				return false;
			}

			$statusUnpacked = unpack('C*', substr($response, 3, -2));

			if ($statusUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['status' => array_values($statusUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x03) Read Holding Registers
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Registers (n1)
	 *
	 * @return Array<string, int|Array<int, int|float|null>>|string|false
	 * [
	 *    'station'   => $station,
	 *    'function'  => 0x03,
	 *    'count'     => $count,
	 *    'registers' => [],
	 * ]
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function readHoldingRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
		int|null $numberOfDecimals = null,
		bool $signed = false,
		bool $raw = false,
	): string|array|false
	{
		$request = pack('C2n2', $station, self::FUNCTION_CODE_READ_HOLDING, $startingAddress, $quantity);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count', $response);

			if ($unpacked === false) {
				return false;
			}

			$registersUnpacked = unpack('C*', substr($response, 3, -2));

			if ($registersUnpacked === false) {
				return false;
			}

			$registersValuesChunks = array_chunk($registersUnpacked, 2);

			$response = $signed ? $unpacked + [
				'registers' => array_values(array_map(fn (array $valueChunk): int|null => $this->unpackSignedInt(
					$valueChunk,
					Types\ByteOrder::get(Types\ByteOrder::BYTE_ORDER_BIG),
				), $registersValuesChunks)),
			] : $unpacked + [
				'registers' => array_values(array_map(fn (array $valueChunk): int|null => $this->unpackUnsignedInt(
					$valueChunk,
					Types\ByteOrder::get(Types\ByteOrder::BYTE_ORDER_BIG),
				), $registersValuesChunks)),
			];
		}

		return $response;
	}

	/**
	 * (0x04) Read Input Registers
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Input Registers
	 *
	 * @return Array<string, int|Array<int, int|float|null>>|string|false
	 * [
	 *    'station'   => $station,
	 *    'function'  => 0x04,
	 *    'count'     => $count,
	 *    'registers' => [],
	 * ]
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function readInputRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
		int|null $numberOfDecimals = null,
		bool $signed = false,
		bool $raw = false,
	): string|array|false
	{
		$request = pack('C2n2', $station, self::FUNCTION_CODE_READ_INPUT, $startingAddress, $quantity);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count', $response);

			if ($unpacked === false) {
				return false;
			}

			$registersUnpacked = unpack('C*', substr($response, 3, -2));

			if ($registersUnpacked === false) {
				return false;
			}

			$registersValuesChunks = array_chunk($registersUnpacked, 2);

			$response = $signed ? $unpacked + [
				'registers' => array_values(array_map(fn (array $valueChunk): int|null => $this->unpackSignedInt(
					$valueChunk,
					Types\ByteOrder::get(Types\ByteOrder::BYTE_ORDER_BIG),
				), $registersValuesChunks)),
			] : $unpacked + [
				'registers' => array_values(array_map(fn (array $valueChunk): int|null => $this->unpackUnsignedInt(
					$valueChunk,
					Types\ByteOrder::get(Types\ByteOrder::BYTE_ORDER_BIG),
				), $registersValuesChunks)),
			];
		}

		return $response;
	}

	/**
	 * (0x05) Write Single Coil
	 *
	 * @param int $station Station Address (C1)
	 * @param int $coilAddress Coil Address (n1)
	 * @param bool $value Output Value (n1)
	 *
	 * @return Array<string, int|bool>|string|false
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function writeSingleCoil(
		int $station,
		int $coilAddress,
		bool $value,
		bool $raw = false,
	): string|array|false
	{
		// Pack header (transform to binary)
		$request = pack('C2n', $station, self::FUNCTION_CODE_WRITE_SINGLE_COIL, $coilAddress);
		// Pack value (transform to binary)
		$request .= pack('n', $value ? 0xFF00 : 0x0000);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/n1address', $response);

			if ($unpacked === false) {
				return false;
			}

			$valueUnpacked = unpack('n1', substr($response, 4, -2));

			if ($valueUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['value' => current($valueUnpacked) === 0xFF00];
		}

		return $response;
	}

	/**
	 * (0x06) Write Single Register
	 *
	 * @param int $station Station Address (C1)
	 * @param int $registerAddress Register Address (n1)
	 * @param int|float $value Register Value (n1)
	 *
	 * @return Array<string, int|float|null>|string|false
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function writeSingleRegister(
		int $station,
		int $registerAddress,
		int|float $value,
		int|null $numberOfDecimals = null,
		bool $signed = false,
		bool $raw = false,
	): string|array|false
	{
		// Pack header (transform to binary)
		$request = pack('C2n', $station, self::FUNCTION_CODE_WRITE_SINGLE_HOLDING, $registerAddress);
		// Pack value (transform to binary)
		// TODO: Add handling for 32 (C4) and 64 (C8) bytes
		$request .= pack('C2', ($value >> 8) & 0xFF, ($value >> 0) & 0xFF);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/n1address', $response);

			if ($unpacked === false) {
				return false;
			}

			$valueChunk = unpack('C*', substr($response, 4, -2));

			if ($valueChunk === false) {
				return false;
			}

			$response = $signed ? $unpacked + [
				'value' => $this->unpackSignedInt(
					$valueChunk,
					Types\ByteOrder::get(Types\ByteOrder::BYTE_ORDER_BIG),
				),
			] : $unpacked + [
				'value' => $this->unpackUnsignedInt(
					$valueChunk,
					Types\ByteOrder::get(Types\ByteOrder::BYTE_ORDER_BIG),
				),
			];
		}

		return $response;
	}

	/**
	 * (0x15) Write Multiple Coils
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Outputs (n1)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function writeMultipleCoils(
		int $station,
		int $startingAddress,
		int $quantity,
	): string|false
	{
		if (func_num_args() !== 3 + $quantity) {
			throw new Exceptions\ModbusRtu('Incorrect number of arguments', -4);
		}

		$request = pack('C2n2', $station, self::FUNCTION_CODE_WRITE_MULTIPLE_COILS, $startingAddress, $quantity);
		$request .= pack('C1C*', $quantity, ...array_slice(func_get_args(), 3));

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		return $this->sendRequest($request);
	}

	/**
	 * (0x16) Write Multiple registers
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Registers (n1)
	 *
	 * Registers Value (n*)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function writeMultipleRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
	): string|false
	{
		if (func_num_args() !== 3 + $quantity) {
			throw new Exceptions\ModbusRtu('Incorrect number of arguments', -4);
		}

		$request = pack('C2n2', $station, self::FUNCTION_CODE_WRITE_MULTIPLE_HOLDINGS, $startingAddress, $quantity);
		$request .= pack('C1n*', 2 * $quantity, ...array_slice(func_get_args(), 3));

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		return $this->sendRequest($request);
	}

	/**
	 * (0x07) Read Exception Status (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function readExceptionStatus(
		int $station,
		bool $raw = false,
	): string|array|false
	{
		$request = pack('C2', $station, 0x07);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$response = unpack('C1station/C1function/C1data', $response);
		}

		return $response;
	}

	/**
	 * (0x08) Diagnostics (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 * @param int $subFunction Sub-function (n1)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function diagnostics(int $station, int $subFunction): string|false
	{
		if (func_num_args() < 3) {
			throw new Exceptions\ModbusRtu('Incorrect number of arguments', -4);
		}

		$request = pack('C2n1', $station, 0x08, $subFunction);
		$request .= pack('n*', ...array_slice(func_get_args(), 2));

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		return $this->sendRequest($request);
	}

	/**
	 * (0x0B) Get Comm Event Counter (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function getCommEventCounter(
		int $station,
		bool $raw = false,
	): string|array|false
	{
		$request = pack('C2', $station, 0x0B);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$response = unpack('C1station/C1function/n1status/n1eventcount', $response);
		}

		return $response;
	}

	/**
	 * (0x0C) Get Comm Event Log (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function getCommEventLog(
		int $station,
		bool $raw = false,
	): string|array|false
	{
		$request = pack('C2', $station, 0x0C);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count/n1status/n1eventcount/n1messagecount', $response);

			if ($unpacked === false) {
				return false;
			}

			$eventsUnpacked = unpack('C*', substr($response, 9, -2));

			if ($eventsUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['events' => array_values($eventsUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x11) Report Server ID (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function reportServerId(int $station = 0x00): string|false
	{
		$request = pack('C2', $station, 0x11);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		return $this->sendRequest($request);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 * @throws Exceptions\Runtime
	 */
	private function sendRequest(string $request): string|false
	{
		if ($this->interface === null) {
			throw new Exceptions\Runtime('Connection is not established');
		}

		$this->interface->send($request);

		usleep((int) (0.1 * 1000_000));

		$response = $this->interface->read();

		if ($response === false) {
			return false;
		}

		if (strlen($response) < 4) {
			throw new Exceptions\ModbusRtu('Response length too short', -1, $request, $response);
		}

		$aduRequest = unpack(self::MODBUS_ADU, $request);

		if ($aduRequest === false) {
			return false;
		}

		$aduResponse = unpack(self::MODBUS_ERROR, $response);

		if ($aduResponse === false) {
			return false;
		}

		if ($aduRequest['function'] !== $aduResponse['error']) {
			// Error code = Function code + 0x80
			if ($aduResponse['error'] === $aduRequest['function'] + 0x80) {
				throw new Exceptions\ModbusRtu(null, $aduResponse['exception'], $request, $response);
			} else {
				throw new Exceptions\ModbusRtu('Illegal error code', -3, $request, $response);
			}
		}

		if (substr($response, -2) !== $this->crc16(substr($response, 0, -2))) {
			throw new Exceptions\ModbusRtu('Error check fails', -2, $request, $response);
		}

		return $response;
	}

	private function crc16(string $data): string|false
	{
		$crc = 0xFFFF;

		$bytes = unpack('C*', $data);

		if ($bytes === false) {
			return false;
		}

		foreach ($bytes as $byte) {
			$crc ^= $byte;

			for ($j = 8; $j; $j--) {
				$crc = ($crc >> 1) ^ ($crc & 0x0001) * 0xA001;
			}
		}

		return pack('v1', $crc);
	}

	/**
	 * @param Array<int> $bytes
	 *
	 * @throws Exceptions\InvalidState
	 */
	private function unpackSignedInt(array $bytes, Types\ByteOrder $byteOrder): int|null
	{
		if (
			(
				$this->isLittleEndian()
				&& $byteOrder->equalsValue(Types\ByteOrder::BYTE_ORDER_LITTLE)
			) || (
				!$this->isLittleEndian()
				&& $byteOrder->equalsValue(Types\ByteOrder::BYTE_ORDER_BIG)
			)
		) {
			// If machine is using same byte order as device
			$value = unpack('s', pack('C*', ...array_values($bytes)));

		} elseif (
			(
				!$this->isLittleEndian()
				&& $byteOrder->equalsValue(Types\ByteOrder::BYTE_ORDER_LITTLE)
			) || (
				$this->isLittleEndian()
				&& $byteOrder->equalsValue(Types\ByteOrder::BYTE_ORDER_BIG)
			)
		) {
			// If machine is using different byte order than device, do byte order swap
			$value = unpack('s', pack('C*', ...array_reverse(array_values($bytes))));

		} else {
			return null;
		}

		if ($value === false) {
			return null;
		}

		return intval(current($value));
	}

	/**
	 * @param Array<int> $bytes
	 */
	private function unpackUnsignedInt(array $bytes, Types\ByteOrder $byteOrder): int|null
	{
		if ($byteOrder->equalsValue(Types\ByteOrder::BYTE_ORDER_LITTLE)) {
			$bytes = array_reverse(array_values($bytes));
		}

		return array_reduce(
			$bytes,
			static fn ($out, $in): int => $out << 8 | $in,
		);
	}

	/**
	 * Detect machine byte order configuration
	 *
	 * @throws Exceptions\InvalidState
	 */
	private function isLittleEndian(): bool
	{
		if ($this->machineUsingLittleEndian !== null) {
			return $this->machineUsingLittleEndian;
		}

		$testUnpacked = unpack('S', '\x01\x00');

		if ($testUnpacked === false) {
			throw new Exceptions\InvalidState('Endian order could not be determined');
		}

		$this->machineUsingLittleEndian = current($testUnpacked) === 1;

		return $this->machineUsingLittleEndian;
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
