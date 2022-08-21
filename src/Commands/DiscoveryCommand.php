<?php declare(strict_types = 1);

/**
 * DiscoveryCommand.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Commands
 * @since          0.34.0
 *
 * @date           21.08.22
 */

namespace FastyBird\ModbusConnector\Commands;

use DateTimeInterface;
use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\ModbusConnector\Clients;
use FastyBird\ModbusConnector\Entities;
use FastyBird\ModbusConnector\Helpers;
use FastyBird\ModbusConnector\Types;
use Psr\Log;
use Ramsey\Uuid;
use React\EventLoop;
use ReflectionClass;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;

/**
 * Connector devices discovery command
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DiscoveryCommand extends Console\Command\Command
{

	private const DISCOVERY_WAITING_INTERVAL = 5.0;

	/** @var DateTimeInterface|null */
	private ?DateTimeInterface $executedTime = null;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $progressBarTimer;

	/** @var Clients\ClientFactory[] */
	private array $clientsFactories;

	/** @var Helpers\ConnectorHelper */
	private Helpers\ConnectorHelper $connectorHelper;

	/** @var Helpers\DeviceHelper */
	private Helpers\DeviceHelper $deviceHelper;

	/** @var DevicesModuleModels\DataStorage\IConnectorsRepository */
	private DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository;

	/** @var DevicesModuleModels\Devices\IDevicesRepository */
	private DevicesModuleModels\Devices\IDevicesRepository $devicesRepository;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/**
	 * @param Clients\ClientFactory[] $clientsFactories
	 * @param Helpers\ConnectorHelper $connectorHelper
	 * @param Helpers\DeviceHelper $deviceHelper
	 * @param DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository
	 * @param DevicesModuleModels\Devices\IDevicesRepository $devicesRepository
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 * @param string|null $name
	 */
	public function __construct(
		array $clientsFactories,
		Helpers\ConnectorHelper $connectorHelper,
		Helpers\DeviceHelper $deviceHelper,
		DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsRepository,
		DevicesModuleModels\Devices\IDevicesRepository $devicesRepository,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null,
		?string $name = null
	) {
		$this->clientsFactories = $clientsFactories;

		$this->connectorHelper = $connectorHelper;
		$this->deviceHelper = $deviceHelper;

		$this->connectorsRepository = $connectorsRepository;
		$this->devicesRepository = $devicesRepository;

		$this->dateTimeFactory = $dateTimeFactory;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function configure(): void
	{
		$this
			->setName('fb:modbus-connector:discover')
			->setDescription('Modbus connector devices discovery')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption('connector', 'c', Input\InputOption::VALUE_OPTIONAL, 'Run devices module connector', true),
					new Input\InputOption('no-confirm', null, Input\InputOption::VALUE_NONE, 'Do not ask for any confirmation'),
				])
			);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title('FB modbus connector - discovery');

		$io->note('This action will run connector devices discovery.');

		if (!$input->getOption('no-confirm')) {
			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to continue?',
				false
			);

			$continue = $io->askQuestion($question);

			if (!$continue) {
				return Console\Command\Command::SUCCESS;
			}
		}

		if (
			$input->hasOption('connector')
			&& is_string($input->getOption('connector'))
			&& $input->getOption('connector') !== ''
		) {
			$connectorId = $input->getOption('connector');

			if (Uuid\Uuid::isValid($connectorId)) {
				$connector = $this->connectorsRepository->findById(Uuid\Uuid::fromString($connectorId));
			} else {
				$connector = $this->connectorsRepository->findByIdentifier($connectorId);
			}

			if ($connector === null) {
				$io->warning('Connector was not found in system');

				return Console\Command\Command::FAILURE;
			}
		} else {
			$connectors = [];

			foreach ($this->connectorsRepository as $connector) {
				if ($connector->getType() !== Entities\ModbusConnectorEntity::CONNECTOR_TYPE) {
					continue;
				}

				$connectors[$connector->getIdentifier()] = $connector->getIdentifier() . $connector->getName() ? ' [' . $connector->getName() . ']' : '';
			}

			if (count($connectors) === 0) {
				$io->warning('No connectors registered in system');

				return Console\Command\Command::FAILURE;
			}

			if (count($connectors) === 1) {
				$connectorIdentifier = array_key_first($connectors);

				$connector = $this->connectorsRepository->findByIdentifier($connectorIdentifier);

				if ($connector === null) {
					$io->warning('Connector was not found in system');

					return Console\Command\Command::FAILURE;
				}

				if (!$input->getOption('no-confirm')) {
					$question = new Console\Question\ConfirmationQuestion(
						sprintf('Would you like to discover devices with "%s" connector', $connector->getName() ?? $connector->getIdentifier()),
						false
					);

					if (!$io->askQuestion($question)) {
						return Console\Command\Command::SUCCESS;
					}
				}
			} else {
				$question = new Console\Question\ChoiceQuestion(
					'Please select connector to execute',
					array_values($connectors)
				);

				$question->setErrorMessage('Selected connector: %s is not valid.');

				$connectorIdentifier = array_search($io->askQuestion($question), $connectors);

				if ($connectorIdentifier === false) {
					$io->error('Something went wrong, connector could not be loaded');

					$this->logger->alert('Connector identifier was not able to get from answer', [
						'source' => Metadata\Constants::MODULE_DEVICES_SOURCE,
						'type'   => 'discovery-cmd',
					]);

					return Console\Command\Command::FAILURE;
				}

				$connector = $this->connectorsRepository->findByIdentifier($connectorIdentifier);
			}

			if ($connector === null) {
				$io->error('Something went wrong, connector could not be loaded');

				$this->logger->alert('Connector was not found', [
					'source' => Metadata\Constants::MODULE_DEVICES_SOURCE,
					'type'   => 'discovery-cmd',
				]);

				return Console\Command\Command::FAILURE;
			}
		}

		if (!$connector->isEnabled()) {
			$io->warning('Connector is disabled. Disabled connector could not be executed');

			return Console\Command\Command::SUCCESS;
		}

		$mode = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE)
		);

		if ($mode === null) {
			$io->error('Connector client mode is not configured');

			return Console\Command\Command::FAILURE;
		}

		foreach ($this->clientsFactories as $clientFactory) {
			$rc = new ReflectionClass($clientFactory);

			$constants = $rc->getConstants();

			if (
				array_key_exists(Clients\ClientFactory::MODE_CONSTANT_NAME, $constants)
				&& $constants[Clients\ClientFactory::MODE_CONSTANT_NAME] === $mode
				&& method_exists($clientFactory, 'create')
			) {
				/** @var Clients\IClient $client */
				$client = $clientFactory->create($connector);

				$progressBar = new Console\Helper\ProgressBar(
					$output,
					intval(self::DISCOVERY_WAITING_INTERVAL * 60)
				);

				$progressBar->setFormat('[%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %');

				try {
					$this->eventLoop->addSignal(SIGINT, function (int $signal) use ($client, $io): void {
						$this->logger->info('Stopping Modbus connector discovery...', [
							'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
							'type'   => 'discovery-cmd',
						]);

						$io->info('Stopping Modbus connector discovery...');

						$client->disconnect();

						if ($this->progressBarTimer !== null) {
							$this->eventLoop->cancelTimer($this->progressBarTimer);
						}

						$this->eventLoop->stop();
					});

					$this->eventLoop->futureTick(function () use ($client, $io, $progressBar): void {
						$this->logger->info('Starting Modbus connector discovery...', [
							'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
							'type'   => 'discovery-cmd',
						]);

						$io->info('Starting Modbus connector discovery...');

						$progressBar->start();

						$this->executedTime = $this->dateTimeFactory->getNow();

						$client->discover();
					});

					$this->progressBarTimer = $this->eventLoop->addPeriodicTimer(
						0.1,
						function () use ($progressBar): void {
							$progressBar->advance();
						}
					);

					$this->eventLoop->addTimer(
						self::DISCOVERY_WAITING_INTERVAL,
						function () use ($client): void {
							$client->disconnect();

							if ($this->progressBarTimer !== null) {
								$this->eventLoop->cancelTimer($this->progressBarTimer);
							}

							$this->eventLoop->stop();
						}
					);

					$this->eventLoop->run();

					$progressBar->finish();

					$io->newLine();

					$findDevicesQuery = new DevicesModuleQueries\FindDevicesQuery();
					$findDevicesQuery->byConnectorId($connector->getId());

					$devices = $this->devicesRepository->findAllBy($findDevicesQuery);

					$table = new Console\Helper\Table($output);
					$table->setHeaders([
						'#',
						'ID',
						'Name',
						'Address',
					]);

					$foundDevices = 0;

					foreach ($devices as $device) {
						$createdAt = $device->getCreatedAt();

						if (
							$createdAt !== null
							&& $this->executedTime !== null
							&& $createdAt->getTimestamp() > $this->executedTime->getTimestamp()
						) {
							$foundDevices++;

							$address = $this->deviceHelper->getConfiguration(
								$device->getId(),
								Types\DevicePropertyIdentifierType::get(Types\DevicePropertyIdentifierType::IDENTIFIER_ADDRESS)
							);

							$table->addRow([
								$foundDevices,
								$device->getPlainId(),
									$device->getName() ?? $device->getIdentifier(),
								is_string($address) ? $address : 'N/A',
							]);
						}
					}

					if ($foundDevices > 0) {
						$io->newLine();

						$io->info(sprintf('Found %d new devices', $foundDevices));

						$table->render();

						$io->newLine();

					} else {
						$io->info('No devices were found');
					}

					$io->success('Devices discovery was successfully finished');

					return Console\Command\Command::SUCCESS;

				} catch (DevicesModuleExceptions\TerminateException $ex) {
					$this->logger->error('An error occurred', [
						'source'    => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
						'type'      => 'discovery-cmd',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]);

					$io->error('Something went wrong, discovery could not be finished. Error was logged.');

					if ($client->isConnected()) {
						$client->disconnect();
					}

					if ($this->progressBarTimer !== null) {
						$this->eventLoop->cancelTimer($this->progressBarTimer);
					}

					$this->eventLoop->stop();

					return Console\Command\Command::FAILURE;

				} catch (Throwable $ex) {
					$this->logger->error('An unhandled error occurred', [
						'source'    => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
						'type'      => 'discovery-cmd',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]);

					$io->error('Something went wrong, discovery could not be finished. Error was logged.');if ($client->isConnected()) {
						$client->disconnect();
					}

					if ($this->progressBarTimer !== null) {
						$this->eventLoop->cancelTimer($this->progressBarTimer);
					}

					$this->eventLoop->stop();

					return Console\Command\Command::FAILURE;
				}
			}
		}

		$io->error('Connector client is not configured');

		return Console\Command\Command::FAILURE;
	}

}
