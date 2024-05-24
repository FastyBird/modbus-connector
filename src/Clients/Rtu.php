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
use FastyBird\Library\Tools\Exceptions as ToolsExceptions;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Types as DevicesTypes;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use InvalidArgumentException;
use Nette;
use Random\RandomException;
use React\EventLoop;
use TypeError;
use ValueError;
use function array_key_exists;
use function array_merge;
use function assert;
use function in_array;
use function is_int;
use function is_numeric;
use function React\Async\async;
use function React\Async\await;

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

	private const LOST_DELAY = 60.0; // in s - Waiting delay before another communication with device after device was lost

	private const HANDLER_START_DELAY = 2.0;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	private bool $closed = true;

	/** @var array<string> */
	private array $processedDevices = [];

	/** @var array<string, DateTimeInterface|int> */
	private array $processedReadRegisters = [];

	/** @var array<string, DateTimeInterface> */
	private array $lostDevices = [];

	private EventLoop\TimerInterface|null $handlerTimer;

	public function __construct(
		protected readonly API\Transformer $transformer,
		protected readonly Helpers\MessageBuilder $messageBuilder,
		protected readonly Queue\Queue $queue,
		protected readonly Helpers\Device $deviceHelper,
		protected readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly Documents\Connectors\Connector $connector,
		private readonly API\ConnectionManager $connectionManager,
		private readonly Helpers\Channel $channelHelper,
		private readonly Modbus\Logger $logger,
		private readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		private readonly DevicesModels\States\Async\ChannelPropertiesManager $channelPropertiesStatesManager,
		private readonly DevicesUtilities\DeviceConnection $deviceConnectionManager,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 * @throws MetadataExceptions\Mapping
	 * @throws RandomException
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function connect(): void
	{
		$findDevicesQuery = new Queries\Configuration\FindDevices();
		$findDevicesQuery->forConnector($this->connector);

		$devices = $this->devicesConfigurationRepository->findAllBy(
			$findDevicesQuery,
			Documents\Devices\Device::class,
		);

		foreach ($devices as $device) {
			$station = $this->deviceHelper->getAddress($device);

			if (!is_int($station)) {
				$this->queue->append(
					$this->messageBuilder->create(
						Queue\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $this->connector->getId(),
							'device' => $device->getId(),
							'state' => DevicesTypes\ConnectionState::ALERT,
						],
					),
				);
			}
		}

		$this->connectionManager->getRtuClient($this->connector)->open();

		$this->closed = false;

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			async(function (): void {
				$this->registerLoopHandler();
			}),
		);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function disconnect(): void
	{
		$this->closed = true;

		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);
		}

		$this->connectionManager->getRtuClient($this->connector)->close();
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 * @throws MetadataExceptions\Mapping
	 * @throws RandomException
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function handleCommunication(): void
	{
		$findDevicesQuery = new Queries\Configuration\FindDevices();
		$findDevicesQuery->forConnector($this->connector);

		$devices = $this->devicesConfigurationRepository->findAllBy(
			$findDevicesQuery,
			Documents\Devices\Device::class,
		);

		foreach ($devices as $device) {
			if (!in_array($device->getId()->toString(), $this->processedDevices, true)) {
				$this->processedDevices[] = $device->getId()->toString();

				// Check if device is lost or not
				if (array_key_exists($device->getId()->toString(), $this->lostDevices)) {
					if ($this->deviceConnectionManager->getLostAt($device) === null) {
						$this->queue->append(
							$this->messageBuilder->create(
								Queue\Messages\StoreDeviceConnectionState::class,
								[
									'connector' => $this->connector->getId(),
									'device' => $device->getId(),
									'state' => DevicesTypes\ConnectionState::LOST,
								],
							),
						);

						continue;
					} else {
						if (
							$this->dateTimeFactory->getNow()->getTimestamp()
								- $this->lostDevices[$device->getId()->toString()]->getTimestamp() < self::LOST_DELAY
						) {
							continue;
						} else {
							unset($this->lostDevices[$device->getId()->toString()]);
						}
					}
				}

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
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 * @throws MetadataExceptions\Mapping
	 * @throws RandomException
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function processDevice(Documents\Devices\Device $device): bool
	{
		$station = $this->deviceHelper->getAddress($device);
		assert(is_numeric($station));

		$coilsAddresses = $discreteInputsAddresses = $holdingAddresses = $inputsAddresses = [];

		$findChannelsQuery = new Queries\Configuration\FindChannels();
		$findChannelsQuery->forDevice($device);

		$channels = $this->channelsConfigurationRepository->findAllBy(
			$findChannelsQuery,
			Documents\Channels\Channel::class,
		);

		foreach ($channels as $channel) {
			$address = $this->channelHelper->getAddress($channel);

			if (!is_int($address)) {
				$findChannelPropertiesQuery = new DevicesQueries\Configuration\FindChannelDynamicProperties();
				$findChannelPropertiesQuery->forChannel($channel);

				$properties = $this->channelsPropertiesConfigurationRepository->findAllBy(
					$findChannelPropertiesQuery,
					DevicesDocuments\Channels\Properties\Dynamic::class,
				);

				foreach ($properties as $property) {
					await($this->channelPropertiesStatesManager->setValidState(
						$property,
						false,
						MetadataTypes\Sources\Connector::MODBUS,
					));
					await($this->channelPropertiesStatesManager->setPendingState(
						$property,
						false,
						MetadataTypes\Sources\Connector::MODBUS,
					));
				}

				$this->logger->warning(
					'Channel address is missing',
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
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

			$registerReadAddress = $this->createReadAddress($device, $channel);

			if ($registerReadAddress instanceof Messages\Pointer\ReadCoilAddress) {
				$coilsAddresses[] = $registerReadAddress;
			} elseif ($registerReadAddress instanceof Messages\Pointer\ReadDiscreteInputAddress) {
				$discreteInputsAddresses[] = $registerReadAddress;
			} elseif ($registerReadAddress instanceof Messages\Pointer\ReadHoldingRegisterAddress) {
				$holdingAddresses[] = $registerReadAddress;
			} elseif ($registerReadAddress instanceof Messages\Pointer\ReadInputRegisterAddress) {
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

		if (
			$this->deviceConnectionManager->getState($device) === DevicesTypes\ConnectionState::ALERT
		) {
			return false;
		}

		foreach ($requests as $request) {
			try {
				if ($request instanceof Messages\Request\ReadCoils) {
					$response = $this->connectionManager
						->getRtuClient($this->connector)
						->readCoils(
							$station,
							$request->getStartAddress(),
							$request->getQuantity(),
						);
				} elseif ($request instanceof Messages\Request\ReadDiscreteInputs) {
					$response = $this->connectionManager
						->getRtuClient($this->connector)
						->readDiscreteInputs(
							$station,
							$request->getStartAddress(),
							$request->getQuantity(),
						);
				} elseif ($request instanceof Messages\Request\ReadHoldingsRegisters) {
					$response = $this->connectionManager
						->getRtuClient($this->connector)
						->readHoldingRegisters(
							$station,
							$request->getStartAddress(),
							$request->getQuantity(),
						);
				} elseif ($request instanceof Messages\Request\ReadInputsRegisters) {
					$response = $this->connectionManager
						->getRtuClient($this->connector)
						->readInputRegisters(
							$station,
							$request->getStartAddress(),
							$request->getQuantity(),
						);
				} else {
					continue;
				}

				$now = $this->dateTimeFactory->getNow();

				if ($response instanceof API\Messages\Response\ReadDigitalInputs) {
					$this->processDigitalRegistersResponse($request, $response, $device);
				} else {
					$this->processAnalogRegistersResponse($request, $response, $device);
				}

				foreach ($response->getRegisters() as $address => $value) {
					if ($request instanceof Messages\Request\ReadHoldingsRegisters) {
						$channel = $this->deviceHelper->findChannelByType(
							$device,
							$address,
							Types\ChannelType::HOLDING_REGISTER,
						);

					} elseif ($request instanceof Messages\Request\ReadInputsRegisters) {
						$channel = $this->deviceHelper->findChannelByType(
							$device,
							$address,
							Types\ChannelType::INPUT_REGISTER,
						);

					} else {
						continue;
					}

					if ($channel !== null) {
						$this->processedReadRegisters[$channel->getId()->toString()] = $now;
					}
				}
			} catch (Exceptions\ModbusRtu $ex) {
				foreach ($request->getAddresses() as $requestAddress) {
					if ($request instanceof Messages\Request\ReadCoils) {
						$channel = $this->deviceHelper->findChannelByType(
							$device,
							$requestAddress->getAddress(),
							Types\ChannelType::COIL,
						);
					} elseif ($request instanceof Messages\Request\ReadDiscreteInputs) {
						$channel = $this->deviceHelper->findChannelByType(
							$device,
							$requestAddress->getAddress(),
							Types\ChannelType::DISCRETE_INPUT,
						);
					} elseif ($request instanceof Messages\Request\ReadHoldingsRegisters) {
						$channel = $this->deviceHelper->findChannelByType(
							$device,
							$requestAddress->getAddress(),
							Types\ChannelType::HOLDING_REGISTER,
						);
					} else {
						$channel = $this->deviceHelper->findChannelByType(
							$device,
							$requestAddress->getAddress(),
							Types\ChannelType::INPUT_REGISTER,
						);
					}

					if ($channel !== null) {
						$findChannelPropertyQuery = new Queries\Configuration\FindChannelDynamicProperties();
						$findChannelPropertyQuery->forChannel($channel);
						$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

						$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
							$findChannelPropertyQuery,
							DevicesDocuments\Channels\Properties\Dynamic::class,
						);

						if ($property instanceof DevicesDocuments\Channels\Properties\Dynamic) {
							await($this->channelPropertiesStatesManager->setValidState(
								$property,
								false,
								MetadataTypes\Sources\Connector::MODBUS,
							));
						}

						// Increment failed attempts counter
						if (!array_key_exists($channel->getId()->toString(), $this->processedReadRegisters)) {
							$this->processedReadRegisters[$channel->getId()->toString()] = 1;
						} else {
							$this->processedReadRegisters[$channel->getId()->toString()]
								= is_int($this->processedReadRegisters[$channel->getId()->toString()])
									? $this->processedReadRegisters[$channel->getId()->toString()] + 1
									: 1;
						}
					}
				}

				$this->logger->error(
					'Could not handle register reading',
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
						'type' => 'rtu-client',
						'exception' => ApplicationHelpers\Logger::buildException($ex),
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
						'device' => [
							'id' => $device->getId()->toString(),
						],
					],
				);

				// Something wrong during communication
				return true;
			}
		}

		// Check device state...
		if (
			$this->deviceConnectionManager->getState($device) !== DevicesTypes\ConnectionState::CONNECTED
		) {
			// ... and if it is not ready, set it to ready
			$this->queue->append(
				$this->messageBuilder->create(
					Queue\Messages\StoreDeviceConnectionState::class,
					[
						'connector' => $this->connector->getId(),
						'device' => $device->getId(),
						'state' => DevicesTypes\ConnectionState::CONNECTED,
					],
				),
			);
		}

		return true;
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Mapping
	 * @throws MetadataExceptions\MalformedInput
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function createReadAddress(
		Documents\Devices\Device $device,
		Documents\Channels\Channel $channel,
	): Messages\Pointer\ReadAddress|null
	{
		$now = $this->dateTimeFactory->getNow();

		$findChannelPropertyQuery = new Queries\Configuration\FindChannelDynamicProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

		$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
			$findChannelPropertyQuery,
			DevicesDocuments\Channels\Properties\Dynamic::class,
		);

		if ($property === null || !$property->isQueryable()) {
			return null;
		}

		$address = $this->channelHelper->getAddress($channel);

		if ($address === null) {
			return null;
		}

		if (
			isset($this->processedReadRegisters[$channel->getId()->toString()])
			&& is_int($this->processedReadRegisters[$channel->getId()->toString()])
			&& $this->processedReadRegisters[$channel->getId()->toString()] >= self::READ_MAX_ATTEMPTS
		) {
			unset($this->processedReadRegisters[$channel->getId()->toString()]);

			$this->lostDevices[$device->getId()->toString()] = $now;

			if ($this->deviceConnectionManager->getLostAt($device) === null) {
				$this->queue->append(
					$this->messageBuilder->create(
						Queue\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $this->connector->getId(),
							'device' => $device->getId(),
							'state' => DevicesTypes\ConnectionState::LOST,
						],
					),
				);

				$this->logger->warning(
					'Maximum channel property read attempts reached. Device is lost',
					[
						'source' => MetadataTypes\Sources\Connector::MODBUS->value,
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
					],
				);
			}

			return null;
		}

		if (
			array_key_exists($channel->getId()->toString(), $this->processedReadRegisters)
			&& $this->processedReadRegisters[$channel->getId()->toString()] instanceof DateTimeInterface
			&& (
				$now->getTimestamp() - $this->processedReadRegisters[$channel->getId()->toString()]->getTimestamp()
			) < $this->channelHelper->getReadingDelay($channel)
		) {
			return null;
		}

		$deviceExpectedDataType = $this->transformer->determineDeviceReadDataType(
			$property->getDataType(),
			$property->getFormat(),
		);

		if ($deviceExpectedDataType === MetadataTypes\DataType::BOOLEAN) {
			return $property->isSettable()
				? new Messages\Pointer\ReadCoilAddress($address, $channel, $deviceExpectedDataType)
				: new Messages\Pointer\ReadDiscreteInputAddress($address, $channel, $deviceExpectedDataType);
		} elseif (
			$deviceExpectedDataType === MetadataTypes\DataType::CHAR
			|| $deviceExpectedDataType === MetadataTypes\DataType::UCHAR
			|| $deviceExpectedDataType === MetadataTypes\DataType::SHORT
			|| $deviceExpectedDataType === MetadataTypes\DataType::USHORT
			|| $deviceExpectedDataType === MetadataTypes\DataType::INT
			|| $deviceExpectedDataType === MetadataTypes\DataType::UINT
			|| $deviceExpectedDataType === MetadataTypes\DataType::FLOAT
		) {
			return $property->isSettable()
				? new Messages\Pointer\ReadHoldingRegisterAddress($address, $channel, $deviceExpectedDataType)
				: new Messages\Pointer\ReadInputRegisterAddress($address, $channel, $deviceExpectedDataType);
		}

		$this->logger->warning(
			'Channel property data type is not supported for now',
			[
				'source' => MetadataTypes\Sources\Connector::MODBUS->value,
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
			],
		);

		return null;
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 * @throws MetadataExceptions\Mapping
	 * @throws RandomException
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function registerLoopHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			async(function (): void {
				if ($this->closed) {
					return;
				}

				$this->handleCommunication();
			}),
		);
	}

}
