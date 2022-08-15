<?php declare(strict_types = 1);

/**
 * IClient.php
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

namespace FastyBird\ModbusConnector\Clients;

use DateTimeInterface;
use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Entities\Modules\DevicesModule\IPropertyEntity;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ModbusConnector;
use FastyBird\ModbusConnector\API;
use FastyBird\ModbusConnector\Clients;
use FastyBird\ModbusConnector\Exceptions;
use FastyBird\ModbusConnector\Helpers;
use FastyBird\ModbusConnector\Types;
use Nette\Utils;
use Psr\Log;
use React\EventLoop;

/**
 * Modbus RTU devices client interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class RtuClient extends Client
{

	private const MODBUS_ADU = 'C1station/C1function/C*data/';
	private const MODBUS_ERROR = 'C1station/C1error/C1exception/';

	private const WRITE_DEBOUNCE_DELAY = 500; // in ms
	private const WRITE_PENDING_DELAY = 2000; // in ms
	private const WRITE_MAX_ATTEMPTS = 5;

	private const READ_DELAY = 10; // in s
	private const READ_MAX_ATTEMPTS = 5;

	private const LOST_DELAY = 5; // in s - Waiting delay before another communication with device after device was lost

	private const HANDLER_START_DELAY = 2;
	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	/** @var string[] */
	private array $processedDevices = [];

	/** @var Array<string, DateTimeInterface> */
	private array $lostDevices = [];

	/** @var Array<string, DateTimeInterface|int> */
	private array $processedWrittenProperties = [];

	/** @var Array<string, DateTimeInterface|int> */
	private array $processedReadProperties = [];

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $handlerTimer;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Interfaces\ISerial|null */
	private ?Clients\Interfaces\ISerial $interface;

	/** @var Helpers\ConnectorHelper */
	private Helpers\ConnectorHelper $connectorHelper;

	/** @var Helpers\DeviceHelper */
	private Helpers\DeviceHelper $deviceHelper;

	/** @var Helpers\PropertyHelper */
	private Helpers\PropertyHelper $propertyStateHelper;

	/** @var API\Transformer */
	private ModbusConnector\API\Transformer $transformer;

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

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Helpers\ConnectorHelper $connectorHelper
	 * @param Helpers\DeviceHelper $deviceHelper
	 * @param Helpers\PropertyHelper $propertyStateHelper
	 * @param API\Transformer $transformer
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Helpers\ConnectorHelper $connectorHelper,
		Helpers\DeviceHelper $deviceHelper,
		Helpers\PropertyHelper $propertyStateHelper,
		API\Transformer $transformer,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;
		$this->connectorHelper = $connectorHelper;
		$this->deviceHelper = $deviceHelper;
		$this->propertyStateHelper = $propertyStateHelper;
		$this->transformer = $transformer;

		$this->devicesRepository = $devicesRepository;
		$this->devicePropertiesRepository = $devicePropertiesRepository;
		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;
		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->dateTimeFactory = $dateTimeFactory;
		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConnected(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$configuration = new Clients\Interfaces\Configuration(
			Types\BaudRateType::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifierType::get(
					Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BAUD_RATE
				)
			)),
			Types\ByteSizeType::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifierType::get(
					Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BYTE_SIZE
				)
			)),
			Types\StopBitsType::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifierType::get(
					Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_STOP_BITS
				)
			)),
			Types\ParityType::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifierType::get(
					Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_PARITY
				)
			)),
			false,
			false
		);

		$useDio = false;

		foreach (get_loaded_extensions() as $extension) {
			if (Utils\Strings::contains('dio', Utils\Strings::lower($extension))) {
				$useDio = true;

				break;
			}
		}

		if ($useDio) {
			$this->interface = new Clients\Interfaces\SerialDio(
				(string) $this->connectorHelper->getConfiguration(
					$this->connector->getId(),
					Types\ConnectorPropertyIdentifierType::get(
						Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_INTERFACE
					)
				),
				$configuration
			);

		} else {
			$this->interface = new Clients\Interfaces\SerialFile(
				(string) $this->connectorHelper->getConfiguration(
					$this->connector->getId(),
					Types\ConnectorPropertyIdentifierType::get(
						Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_INTERFACE
					)
				),
				$configuration
			);
		}

		$this->interface->open();

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->handlerTimer = $this->eventLoop->addPeriodicTimer(
					self::HANDLER_PROCESSING_INTERVAL,
					function (): void {
						$this->handleCommunication();
					}
				);
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);
		}

		$this->interface?->close();
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	/**
	 * @return void
	 */
	private function handleCommunication(): void
	{
		foreach ($this->processedWrittenProperties as $index => $processedProperty) {
			if (
				$processedProperty instanceof DateTimeInterface
				&& ((float) $this->dateTimeFactory->getNow()->format('Uv') - (float) $processedProperty->format('Uv')) >= self::WRITE_DEBOUNCE_DELAY
			) {
				unset($this->processedWrittenProperties[$index]);
			}
		}

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			if (
				!in_array($device->getId()->toString(), $this->processedDevices, true)
				&& !$this->deviceConnectionStateManager->getState($device)->equalsValue(MetadataTypes\ConnectionStateType::STATE_STOPPED)
			) {
				$deviceAddress = $this->deviceHelper->getConfiguration(
					$device->getId(),
					Types\DevicePropertyIdentifierType::get(
						Types\DevicePropertyIdentifierType::IDENTIFIER_ADDRESS
					)
				);

				if (!is_int($deviceAddress)) {
					$this->deviceConnectionStateManager->setState(
						$device,
						MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_STOPPED)
					);

					continue;
				}

				// Check if device is lost or not
				if (array_key_exists($device->getId()->toString(), $this->lostDevices)) {
					if ($this->deviceConnectionStateManager->getState($device)->equalsValue(MetadataTypes\ConnectionStateType::STATE_LOST)) {
						$this->logger->debug('Device is still lost', [
							'source'    => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
							'type'      => 'rtu-client',
							'device'    => [
								'id' => $device->getId()->toString(),
							],
						]);

					} else {
						$this->logger->debug('Device is lost', [
							'source'    => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
							'type'      => 'rtu-client',
							'device'    => [
								'id' => $device->getId()->toString(),
							],
						]);

						$this->deviceConnectionStateManager->setState(
							$device,
							MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_LOST)
						);
					}

					if ($this->dateTimeFactory->getNow()->getTimestamp() - $this->lostDevices[$device->getId()->toString()]->getTimestamp() < self::LOST_DELAY) {
						continue;
					}
				}

				// Check device state...
				if (
					!$this->deviceConnectionStateManager->getState($device)->equalsValue(Metadata\Types\ConnectionStateType::STATE_CONNECTED)
				) {
					// ... and if it is not ready, set it to ready
					$this->deviceConnectionStateManager->setState(
						$device,
						Metadata\Types\ConnectionStateType::get(Metadata\Types\ConnectionStateType::STATE_CONNECTED)
					);
				}

				$this->processedDevices[] = $device->getId()->toString();

				if ($this->processDevice($device)) {
					return;
				}
			}
		}

		$this->processedDevices = [];
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return bool
	 */
	private function processDevice(MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): bool
	{
		$station = (int) $this->deviceHelper->getConfiguration(
			$device->getId(),
			Types\DevicePropertyIdentifierType::get(
				Types\DevicePropertyIdentifierType::IDENTIFIER_ADDRESS
			)
		);

		foreach ($this->devicePropertiesRepository->findAllByDevice($device->getId(), MetadataEntities\Modules\DevicesModule\DeviceDynamicPropertyEntity::class) as $property) {
			$logContext = [
				'source'    => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
				'type'      => 'rtu-client',
				'device'    => [
					'id' => $device->getId()->toString(),
				],
				'property'  => [
					'id' => $property->getId()->toString(),
				],
			];

			/**
			 * Device property writing
			 */

			try {
				$result = $this->writeProperty($station, $device, $property);

				if ($result) {
					return true;
				}
			} catch (Exceptions\InvalidArgumentException $ex) {
				$this->logger->warning('Device property value could not be written', array_merge($logContext, [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]));
			} catch (Exceptions\NotSupportedException $ex) {
				$this->logger->warning('Device property value is not supported for now', array_merge($logContext, [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]));
			} catch (Exceptions\ModbusRtuException $ex) {
				$this->logger->error('Modbus communication with device failed', array_merge($logContext, [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]));

				// Something wrong during communication
				return true;
			} catch (Exceptions\NotReachableException $ex) {
				$this->logger->error('Maximum device property write attempts reached', array_merge($logContext, [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]));

				// Device is probably offline
				return true;
			}

			/**
			 * Device property reading
			 */

			try {
				$result = $this->readProperty($station, $device, $property);

				if ($result) {
					return true;
				}
			} catch (Exceptions\InvalidArgumentException $ex) {
				$this->logger->warning('Device property value could not be read', array_merge($logContext, [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]));
			} catch (Exceptions\NotSupportedException $ex) {
				$this->logger->warning('Device property data type is not supported for now', array_merge($logContext, [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]));
			} catch (Exceptions\ModbusRtuException $ex) {
				$this->logger->error('Modbus communication with device failed', array_merge($logContext, [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]));

				// Something wrong during communication
				return true;
			} catch (Exceptions\NotReachableException $ex) {
				$this->logger->error('Maximum device property read attempts reached', array_merge($logContext, [
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]));

				// Device is probably offline
				return true;
			}
		}

		foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
			foreach ($this->channelPropertiesRepository->findAllByChannel($channel->getId(), MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity::class) as $property) {
				$logContext = [
					'source'    => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
					'type'      => 'rtu-client',
					'device'    => [
						'id' => $device->getId()->toString(),
					],
					'channel'   => [
						'id' => $channel->getId()->toString(),
					],
					'property'  => [
						'id' => $property->getId()->toString(),
					],
				];

				/**
				 * Channel property writing
				 */

				try {
					$result = $this->writeProperty($station, $device, $property);

					if ($result) {
						return true;
					}
				} catch (Exceptions\InvalidArgumentException $ex) {
					$this->logger->warning('Channel property value could not be written', array_merge($logContext, [
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]));
				} catch (Exceptions\NotSupportedException $ex) {
					$this->logger->warning('Channel property value is not supported for now', array_merge($logContext, [
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]));
				} catch (Exceptions\ModbusRtuException $ex) {
					$this->logger->error('Modbus communication with device failed', array_merge($logContext, [
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]));

					// Something wrong during communication
					return true;
				} catch (Exceptions\NotReachableException $ex) {
					$this->logger->error('Maximum channel property write attempts reached', array_merge($logContext, [
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]));

					// Device is probably offline
					return true;
				}

				/**
				 * Channel property reading
				 */

				try {
					$result = $this->readProperty($station, $device, $property);

					if ($result) {
						return true;
					}
				} catch (Exceptions\InvalidArgumentException $ex) {
					$this->logger->warning('Channel property value could not be read', array_merge($logContext, [
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]));
				} catch (Exceptions\NotSupportedException $ex) {
					$this->logger->warning('Channel property data type is not supported for now', array_merge($logContext, [
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]));
				} catch (Exceptions\ModbusRtuException $ex) {
					$this->logger->error('Modbus communication with device failed', array_merge($logContext, [
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]));

					// Something wrong during communication
					return true;
				} catch (Exceptions\NotReachableException $ex) {
					$this->logger->error('Maximum channel property read attempts reached', array_merge($logContext, [
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]));

					// Device is probably offline
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param int $station
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 * @param IPropertyEntity $property
	 *
	 * @return bool
	 *
	 * @throws Exceptions\InvalidArgumentException
	 * @throws Exceptions\ModbusRtuException
	 * @throws Exceptions\NotReachableException
	 * @throws Exceptions\NotSupportedException
	 */
	private function writeProperty(
		int $station,
		MetadataEntities\Modules\DevicesModule\IDeviceEntity $device,
		MetadataEntities\Modules\DevicesModule\IPropertyEntity $property
	): bool {
		$now = $this->dateTimeFactory->getNow();

		$propertyUuid = $property->getId()->toString();

		if (
			(
				// Only dynamic properties could be processed
				$property instanceof MetadataEntities\Modules\DevicesModule\IDeviceDynamicPropertyEntity
				|| $property instanceof MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity
			)
			// Property identifier have to have valid identifier format
			&& preg_match(ModbusConnector\Constants::PROPERTY_REGISTER, $property->getIdentifier(), $propertyMatches) === 1
			// Property have to be writable
			&& $property->isSettable()
			&& $property->getExpectedValue() !== null
			&& $property->isPending()
		) {
			if (
				!array_key_exists('name', $propertyMatches)
				|| !array_key_exists('address', $propertyMatches)
			) {
				unset($this->processedWrittenProperties[$propertyUuid]);

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'expectedValue' => null,
						'pending'       => false,
					])
				);

				throw new Exceptions\InvalidArgumentException('Property identifier has invalid format');
			}

			if (!in_array($property->getDataType()->getValue(), [
				MetadataTypes\DataTypeType::DATA_TYPE_CHAR,
				MetadataTypes\DataTypeType::DATA_TYPE_SHORT,
				MetadataTypes\DataTypeType::DATA_TYPE_INT,
				MetadataTypes\DataTypeType::DATA_TYPE_UCHAR,
				MetadataTypes\DataTypeType::DATA_TYPE_USHORT,
				MetadataTypes\DataTypeType::DATA_TYPE_UINT,
				MetadataTypes\DataTypeType::DATA_TYPE_FLOAT,
				MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN,
				MetadataTypes\DataTypeType::DATA_TYPE_ENUM,
				MetadataTypes\DataTypeType::DATA_TYPE_SWITCH,
			])) {
				unset($this->processedWrittenProperties[$propertyUuid]);

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'expectedValue' => null,
						'pending'       => false,
					])
				);

				throw new Exceptions\InvalidArgumentException(
					sprintf(
						'Trying to write property with unsupported data type: %s for channel property',
						strval($property->getDataType()->getValue())
					)
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
						'pending'       => false,
					])
				);

				throw new Exceptions\NotReachableException('Maximum writing attempts reached');
			}

			if (
				array_key_exists($propertyUuid, $this->processedWrittenProperties)
				&& $this->processedWrittenProperties[$propertyUuid] instanceof DateTimeInterface
				&& (float) $now->format('Uv') - (float) $this->processedWrittenProperties[$propertyUuid]->format('Uv') < self::WRITE_DEBOUNCE_DELAY
			) {
				return false;
			}

			$pending = is_string($property->getPending()) ? Utils\DateTime::createFromFormat(DateTimeInterface::ATOM, $property->getPending()) : true;

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
					$property->getExpectedValue()
				);

				if ($valueToWrite === null) {
					return false;
				}

				try {
					if ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN)) {
						if (in_array($valueToWrite, [0, 1], true) || is_bool($valueToWrite)) {
							$result = $this->writeSingleCoil(
								$station,
								(int) $propertyMatches['address'],
								is_bool($valueToWrite) ? ($valueToWrite ? 1 : 0) : $valueToWrite
							);

						} else {
							unset($this->processedWrittenProperties[$propertyUuid]);

							$this->propertyStateHelper->setValue(
								$property,
								Utils\ArrayHash::from([
									'expectedValue' => null,
									'pending'       => false,
								])
							);

							throw new Exceptions\InvalidStateException('Value for boolean property have to be 1 or 0');
						}
					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT)) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending'       => false,
							])
						);

						throw new Exceptions\NotSupportedException('Float value is not supported for now');

					} elseif (
						$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_INT)
						|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UINT)
					) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending'       => false,
							])
						);

						throw new Exceptions\NotSupportedException('Long integer value is not supported for now');

					} elseif (
						$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SHORT)
						|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_USHORT)
					) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending'       => false,
							])
						);

						throw new Exceptions\NotSupportedException('Short integer value is not supported for now');

					} elseif (
						$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_CHAR)
						|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR)
					) {
						$result = $this->writeSingleRegister(
							$station,
							(int) $propertyMatches['address'],
							(int) $valueToWrite
						);

					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_STRING)) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending'       => false,
							])
						);

						throw new Exceptions\NotSupportedException('String value is not supported for now');

					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
						// TODO: Fix handling for different value data types

						if (is_int($valueToWrite)) {
							$result = $this->writeSingleRegister(
								$station,
								(int) $propertyMatches['address'],
								$valueToWrite
							);

						} else {
							unset($this->processedWrittenProperties[$propertyUuid]);

							$this->propertyStateHelper->setValue(
								$property,
								Utils\ArrayHash::from([
									'expectedValue' => null,
									'pending'       => false,
								])
							);

							throw new Exceptions\InvalidStateException('Value for switch property have to be number');
						}
					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)) {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending'       => false,
							])
						);

						throw new Exceptions\NotSupportedException('Enum value is not supported for now');

					} else {
						unset($this->processedWrittenProperties[$propertyUuid]);

						$this->propertyStateHelper->setValue(
							$property,
							Utils\ArrayHash::from([
								'expectedValue' => null,
								'pending'       => false,
							])
						);

						throw new Exceptions\InvalidStateException('Unsupported value data type');
					}
				} catch (Exceptions\ModbusRtuException $ex) {
					unset($this->processedWrittenProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'expectedValue' => null,
							'pending'       => false,
						])
					);

					throw $ex;
				}

				// Register writing failed
				if ($result === false) {
					// Increment failed attempts counter
					if (!array_key_exists($propertyUuid, $this->processedWrittenProperties)) {
						$this->processedWrittenProperties[$propertyUuid] = 1;
					} else {
						$this->processedWrittenProperties[$propertyUuid] = is_int($this->processedWrittenProperties[$propertyUuid]) ? $this->processedWrittenProperties[$propertyUuid] + 1 : 1;
					}
				} else {
					$this->processedWrittenProperties[$propertyUuid] = $now;

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'pending' => $this->dateTimeFactory->getNow()->format(DateTimeInterface::ATOM),
						])
					);
				}

				return $result !== false;
			}
		}

		return false;
	}

	/**
	 * @param int $station
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 * @param IPropertyEntity $property
	 *
	 * @return bool
	 *
	 * @throws Exceptions\InvalidArgumentException
	 * @throws Exceptions\ModbusRtuException
	 * @throws Exceptions\NotReachableException
	 * @throws Exceptions\NotSupportedException
	 */
	private function readProperty(
		int $station,
		MetadataEntities\Modules\DevicesModule\IDeviceEntity $device,
		MetadataEntities\Modules\DevicesModule\IPropertyEntity $property
	): bool
	{
		$now = $this->dateTimeFactory->getNow();

		$propertyUuid = $property->getId()->toString();

		if (
			(
				// Only dynamic properties could be processed
				$property instanceof MetadataEntities\Modules\DevicesModule\IDeviceDynamicPropertyEntity
				|| $property instanceof MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity
			)
			// Property identifier have to have valid identifier format
			&& preg_match(ModbusConnector\Constants::PROPERTY_REGISTER, $property->getIdentifier(), $propertyMatches) === 1
			// Property have to be readable
			&& $property->isQueryable()
		) {
			if (
				!array_key_exists('name', $propertyMatches)
				|| !array_key_exists('address', $propertyMatches)
			) {
				unset($this->processedReadProperties[$propertyUuid]);

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'valid' => false,
					])
				);

				throw new Exceptions\InvalidArgumentException('Property identifier has invalid format');
			}

			if (!in_array($property->getDataType()->getValue(), [
				MetadataTypes\DataTypeType::DATA_TYPE_CHAR,
				MetadataTypes\DataTypeType::DATA_TYPE_SHORT,
				MetadataTypes\DataTypeType::DATA_TYPE_INT,
				MetadataTypes\DataTypeType::DATA_TYPE_UCHAR,
				MetadataTypes\DataTypeType::DATA_TYPE_USHORT,
				MetadataTypes\DataTypeType::DATA_TYPE_UINT,
				MetadataTypes\DataTypeType::DATA_TYPE_FLOAT,
				MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN,
				MetadataTypes\DataTypeType::DATA_TYPE_ENUM,
				MetadataTypes\DataTypeType::DATA_TYPE_SWITCH,
			])) {
				unset($this->processedReadProperties[$propertyUuid]);

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'valid' => false,
					])
				);

				throw new Exceptions\InvalidArgumentException(
					sprintf(
						'Trying to write property with unsupported data type: %s for channel property',
						strval($property->getDataType()->getValue())
					)
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
					])
				);

				throw new Exceptions\NotReachableException('Maximum writing attempts reached');
			}

			if (
				array_key_exists($propertyUuid, $this->processedReadProperties)
				&& $this->processedReadProperties[$propertyUuid] instanceof DateTimeInterface
				&& $now->getTimestamp() - $this->processedReadProperties[$propertyUuid]->getTimestamp() < self::READ_DELAY
			) {
				return false;
			}

			try {
				if ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN)) {
					if ($property->isSettable()) {
						$result = $this->readCoils(
							$station,
							(int) $propertyMatches['address'],
							1
						);
					} else {
						$result = $this->readDiscreteInputs(
							$station,
							(int) $propertyMatches['address'],
							1
						);
					}

					if (
						!is_array($result)
						|| !array_key_exists('registers', $result)
						|| !is_array($result['registers'])
					) {
						$value = false;
					} else {
						// Extract value from response
						$value = intval($result['registers'][0]);
					}
				} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT)) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						])
					);

					throw new Exceptions\NotSupportedException('Float data type is not supported for now');

				} elseif (
					$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_INT)
					|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UINT)
				) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						])
					);

					throw new Exceptions\NotSupportedException('Long integer data type is not supported for now');

				} elseif (
					$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SHORT)
					|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_USHORT)
				) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						])
					);

					throw new Exceptions\NotSupportedException('Short integer data type is not supported for now');

				} elseif (
					$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_CHAR)
					|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR)
				) {
					if ($property->isSettable()) {
						$result = $this->readHoldingRegisters(
							$station,
							(int) $propertyMatches['address'],
							1
						);
					} else {
						$result = $this->readInputRegisters(
							$station,
							(int) $propertyMatches['address'],
							1
						);
					}

					if (
						!is_array($result)
						|| !array_key_exists('registers', $result)
						|| !is_array($result['registers'])
					) {
						$value = false;
					} else {
						// Extract value from response
						$value = intval($result['registers'][0]);
					}
				} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_STRING)) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						])
					);

					throw new Exceptions\NotSupportedException('String data type is not supported for now');

				} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
					$result = $this->readHoldingRegisters(
						$station,
						(int) $propertyMatches['address'],
						1
					);

					if (
						!is_array($result)
						|| !array_key_exists('registers', $result)
						|| !is_array($result['registers'])
					) {
						$value = false;
					} else {
						// Extract value from response
						$value = intval($result['registers'][0]);
					}
				} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)) {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						])
					);

					throw new Exceptions\NotSupportedException('Enum data type is not supported for now');

				} else {
					unset($this->processedReadProperties[$propertyUuid]);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							'valid' => false,
						])
					);

					throw new Exceptions\InvalidStateException('Unsupported data type');
				}
			} catch (Exceptions\ModbusRtuException $ex) {
				unset($this->processedReadProperties[$propertyUuid]);

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'valid' => false,
					])
				);

				throw $ex;
			}

			// Register reading failed
			if ($value === false) {
				// Increment failed attempts counter
				if (!array_key_exists($propertyUuid, $this->processedReadProperties)) {
					$this->processedReadProperties[$propertyUuid] = 1;
				} else {
					$this->processedReadProperties[$propertyUuid] = is_int($this->processedReadProperties[$propertyUuid]) ? $this->processedReadProperties[$propertyUuid] + 1 : 1;
				}

				// Mark value as invalid
				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'valid' => false,
					])
				);

			} else {
				$this->processedReadProperties[$propertyUuid] = $now;

				$this->propertyStateHelper->setValue(
					$property,
					Utils\ArrayHash::from([
						'actualValue' => $this->transformer->transformValueFromDevice(
							$property->getDataType(),
							$property->getFormat(),
							$value
						),
						'valid'       => true,
					])
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
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|mixed[]>|string|false
	 * [
	 *    'station'  => $station,
	 *    'function' => 0x01,
	 *    'count'    => $count,
	 *    'status'   => [],
	 * ]
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function readCoils(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x01, $startingAddress, $quantity);

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
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|mixed[]>|string|false
	 * [
	 *    'station'  => $station,
	 *    'function' => 0x02,
	 *    'count'    => $count,
	 *    'status'   => [],
	 * ]
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function readDiscreteInputs(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x02, $startingAddress, $quantity);

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
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|mixed[]>|string|false
	 * [
	 *    'station'   => $station,
	 *    'function'  => 0x01,
	 *    'count'     => $count,
	 *    'registers' => [],
	 * ]
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function readHoldingRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x03, $startingAddress, $quantity);

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

			$registersUnpacked = unpack('n*', substr($response, 3, -2));

			if ($registersUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['registers' => array_values($registersUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x04) Read Input Registers
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Input Registers
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|mixed[]>|string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function readInputRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x04, $startingAddress, $quantity);

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

			$registersUnpacked = unpack('n*', substr($response, 3, -2));

			if ($registersUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['registers' => array_values($registersUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x05) Write Single Coil
	 *
	 * @param int $station Station Address (C1)
	 * @param int $outputAddress Output Address (n1)
	 * @param int $value Output Value (n1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|float>|string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function writeSingleCoil(
		int $station,
		int $outputAddress,
		int $value,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x05, $outputAddress, $value);

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
			$response = unpack('C1station/C1function/n1address/n1value', $response);
		}

		return $response;
	}

	/**
	 * (0x06) Write Single Register
	 *
	 * @param int $station Station Address (C1)
	 * @param int $registerAddress Register Address (n1)
	 * @param int $value Register Value (n1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|float>|string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function writeSingleRegister(
		int $station,
		int $registerAddress,
		int $value,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x06, $registerAddress, $value);

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
			$response = unpack('C1station/C1function/n1address/n1value', $response);
		}

		return $response;
	}

	/**
	 * (0x0F) Write Multiple Coils
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Outputs (n1)
	 *
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function writeMultipleCoils(
		int $station,
		int $startingAddress,
		int $quantity
	): string|false {
		if (func_num_args() !== (3 + $quantity)) {
			throw new Exceptions\ModbusRtuException('Incorrect number of arguments', -4);
		}

		$request = pack('C2n2', $station, 0x0F, $startingAddress, $quantity);
		$request .= pack('C1C*', $quantity, ...array_slice(func_get_args(), 3));

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		return $this->sendRequest($request);
	}

	/**
	 * (0x10) Write Multiple registers
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Registers (n1)
	 *
	 * Registers Value (n*)
	 *
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function writeMultipleRegisters(
		int $station,
		int $startingAddress,
		int $quantity
	): string|false {
		if (func_num_args() !== (3 + $quantity)) {
			throw new Exceptions\ModbusRtuException('Incorrect number of arguments', -4);
		}

		$request = pack('C2n2', $station, 0x10, $startingAddress, $quantity);
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
	 * @param bool $raw
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function readExceptionStatus(
		int $station,
		bool $raw = false
	): string|array|false {
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
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function diagnostics(
		int $station,
		int $subFunction
	): string|false {
		if (func_num_args() < 3) {
			throw new Exceptions\ModbusRtuException('Incorrect number of arguments', -4);
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
	 * @param bool $raw
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function getCommEventCounter(
		int $station,
		bool $raw = false
	): string|array|false {
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
	 * @param bool $raw
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function getCommEventLog(
		int $station,
		bool $raw = false
	): string|array|false {
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
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function reportServerId(
		int $station = 0x00
	): string|false {
		$request = pack('C2', $station, 0x11);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		return $this->sendRequest($request);
	}

	/**
	 * @param string $request
	 *
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function sendRequest(
		string $request
	): string|false {
		if ($this->interface === null) {
			throw new Exceptions\RuntimeException('Connection is not established');
		}

		$this->interface->send($request);

		usleep((int) (0.1 * 1000000));

		$response = $this->interface->read();

		if ($response === false) {
			return false;
		}

		if (strlen($response) < 4) {
			throw new Exceptions\ModbusRtuException('Response length too short', -1, $request, $response);
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
			if ($aduResponse['error'] === ($aduRequest['function'] + 0x80)) {
				throw new Exceptions\ModbusRtuException(null, $aduResponse['exception'], $request, $response);
			} else {
				throw new Exceptions\ModbusRtuException('Illegal error code', -3, $request, $response);
			}
		}

		if (substr($response, -2) !== $this->crc16(substr($response, 0, -2))) {
			throw new Exceptions\ModbusRtuException('Error check fails', -2, $request, $response);
		}

		return $response;
	}

	/**
	 * @param string $data
	 *
	 * @return string|false
	 */
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
				$crc = ($crc >> 1) ^ (($crc & 0x0001) * 0xA001);
			}
		}

		return pack('v1', $crc);
	}

}
