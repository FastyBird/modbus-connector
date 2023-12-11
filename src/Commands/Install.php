<?php declare(strict_types = 1);

/**
 * Install.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Commands
 * @since          1.0.0
 *
 * @date           11.12.23
 */

namespace FastyBird\Connector\Modbus\Commands;

use Doctrine\DBAL;
use Doctrine\Persistence;
use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Queries;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\ValueObjects as MetadataValueObjects;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Nette\Localization;
use Nette\Utils;
use RuntimeException;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;
use function array_combine;
use function array_key_exists;
use function array_map;
use function array_search;
use function array_values;
use function assert;
use function count;
use function intval;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function range;
use function sprintf;
use function strval;
use function trim;
use function usort;

/**
 * Connector install command
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Install extends Console\Command\Command
{

	public const NAME = 'fb:modbus-connector:install';
	// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
	private const MATCH_IP_ADDRESS_PORT = '/^(?P<address>((?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])[.]){3}(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5]))(:(?P<port>[0-9]{1,5}))?$/';

	public function __construct(
		private readonly Modbus\Logger $logger,
		private readonly DevicesModels\Entities\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Entities\Connectors\ConnectorsManager $connectorsManager,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesRepository $connectorsPropertiesRepository,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesManager $connectorsPropertiesManager,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Entities\Devices\DevicesManager $devicesManager,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesRepository $devicesPropertiesRepository,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesManager $devicesPropertiesManager,
		private readonly DevicesModels\Entities\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Entities\Channels\ChannelsManager $channelsManager,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly Persistence\ManagerRegistry $managerRegistry,
		private readonly Localization\Translator $translator,
		string|null $name = null,
	)
	{
		parent::__construct($name);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 */
	protected function configure(): void
	{
		$this
			->setName(self::NAME)
			->setDescription('Modbus connector installer');
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title($this->translator->translate('//modbus-connector.cmd.install.title'));

		$io->note($this->translator->translate('//modbus-connector.cmd.install.subtitle'));

		$this->askInstallAction($io);

		return Console\Command\Command::SUCCESS;
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function createConnector(Style\SymfonyStyle $io): void
	{
		$mode = $this->askConnectorMode($io);

		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.connector.identifier'),
		);

		$question->setValidator(function ($answer) {
			if ($answer !== null) {
				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($answer);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\ModbusConnector::class,
				);

				if ($connector !== null) {
					throw new Exceptions\Runtime(
						$this->translator->translate(
							'//modbus-connector.cmd.install.messages.identifier.connector.used',
						),
					);
				}
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'modbus-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\ModbusConnector::class,
				);

				if ($connector === null) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error(
				$this->translator->translate('//modbus-connector.cmd.install.messages.identifier.connector.missing'),
			);

			return;
		}

		$name = $this->askConnectorName($io);

		$interface = $baudRate = $byteSize = $dataParity = $stopBits = null;

		if ($mode->equalsValue(Types\ClientMode::RTU)) {
			$interface = $this->askConnectorInterface($io);
			$baudRate = $this->askConnectorBaudRate($io);
			$byteSize = $this->askConnectorByteSize($io);
			$dataParity = $this->askConnectorDataParity($io);
			$stopBits = $this->askConnectorStopBits($io);
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->create(Utils\ArrayHash::from([
				'entity' => Entities\ModbusConnector::class,
				'identifier' => $identifier,
				'name' => $name,
			]));
			assert($connector instanceof Entities\ModbusConnector);

			$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::CLIENT_MODE,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
				'value' => $mode->getValue(),
				'connector' => $connector,
			]));

			if ($mode->equalsValue(Types\ClientMode::RTU)) {
				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::RTU_INTERFACE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $interface,
					'connector' => $connector,
				]));

				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::RTU_BAUD_RATE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UINT),
					'value' => $baudRate?->getValue(),
					'connector' => $connector,
				]));

				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::RTU_BYTE_SIZE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $byteSize?->getValue(),
					'connector' => $connector,
				]));

				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::RTU_PARITY,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $dataParity?->getValue(),
					'connector' => $connector,
				]));

				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::RTU_STOP_BITS,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $stopBits?->getValue(),
					'connector' => $connector,
				]));
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//modbus-connector.cmd.install.messages.create.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//modbus-connector.cmd.install.messages.create.connector.error'));

			return;
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.create.devices'),
			true,
		);

		$createRegisters = (bool) $io->askQuestion($question);

		if ($createRegisters) {
			$this->createDevice($io, $connector);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function editConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->warning($this->translator->translate('//modbus-connector.cmd.base.messages.noConnectors'));

			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.create.connector'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createConnector($io);
			}

			return;
		}

		$findConnectorPropertyQuery = new DevicesQueries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::CLIENT_MODE);

		$modeProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

		if ($modeProperty === null) {
			$changeMode = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.changeMode'),
				false,
			);

			$changeMode = (bool) $io->askQuestion($question);
		}

		$mode = null;

		if ($changeMode) {
			$mode = $this->askConnectorMode($io, $connector);
		}

		$name = $this->askConnectorName($io, $connector);

		$enabled = $connector->isEnabled();

		if ($connector->isEnabled()) {
			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.disable.connector'),
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = false;
			}
		} else {
			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.enable.connector'),
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = true;
			}
		}

		$interface = $baudRate = $byteSize = $dataParity = $stopBits = null;

		if (
			$modeProperty?->getValue() === Types\ClientMode::RTU
			|| $mode?->getValue() === Types\ClientMode::RTU
		) {
			$interface = $this->askConnectorInterface($io, $connector);
			$baudRate = $this->askConnectorBaudRate($io, $connector);
			$byteSize = $this->askConnectorByteSize($io, $connector);
			$dataParity = $this->askConnectorDataParity($io, $connector);
			$stopBits = $this->askConnectorStopBits($io, $connector);
		}

		$findConnectorPropertyQuery = new DevicesQueries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::RTU_INTERFACE);

		$interfaceProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

		$findConnectorPropertyQuery = new DevicesQueries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::RTU_BAUD_RATE);

		$baudRateProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

		$findConnectorPropertyQuery = new DevicesQueries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::RTU_BYTE_SIZE);

		$byteSizeProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

		$findConnectorPropertyQuery = new DevicesQueries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::RTU_PARITY);

		$dataParityProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

		$findConnectorPropertyQuery = new DevicesQueries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::RTU_STOP_BITS);

		$stopBitsProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->update($connector, Utils\ArrayHash::from([
				'name' => $name === '' ? null : $name,
				'enabled' => $enabled,
			]));
			assert($connector instanceof Entities\ModbusConnector);

			if ($modeProperty === null) {
				if ($mode === null) {
					$mode = $this->askConnectorMode($io, $connector);
				}

				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::CLIENT_MODE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_ENUM),
					'value' => $mode->getValue(),
					'format' => [
						Types\ClientMode::RTU,
						Types\ClientMode::TCP,
					],
					'connector' => $connector,
				]));
			} elseif ($mode !== null) {
				$this->connectorsPropertiesManager->update($modeProperty, Utils\ArrayHash::from([
					'value' => $mode->getValue(),
				]));
			}

			if (
				$modeProperty?->getValue() === Types\ClientMode::RTU
				|| $mode?->getValue() === Types\ClientMode::RTU
			) {
				if ($interfaceProperty === null) {
					$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::RTU_INTERFACE,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
						'value' => $interface,
						'connector' => $connector,
					]));
				} elseif ($interfaceProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->connectorsPropertiesManager->update($interfaceProperty, Utils\ArrayHash::from([
						'value' => $interface,
					]));
				}

				if ($baudRateProperty === null) {
					$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::RTU_BAUD_RATE,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UINT),
						'value' => $baudRate?->getValue(),
						'connector' => $connector,
					]));
				} elseif ($baudRateProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->connectorsPropertiesManager->update($baudRateProperty, Utils\ArrayHash::from([
						'value' => $baudRate?->getValue(),
					]));
				}

				if ($byteSizeProperty === null) {
					$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::RTU_BYTE_SIZE,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
						'value' => $byteSize?->getValue(),
						'connector' => $connector,
					]));
				} elseif ($byteSizeProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->connectorsPropertiesManager->update($byteSizeProperty, Utils\ArrayHash::from([
						'value' => $byteSize?->getValue(),
					]));
				}

				if ($dataParityProperty === null) {
					$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::RTU_PARITY,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
						'value' => $dataParity?->getValue(),
						'connector' => $connector,
					]));
				} elseif ($dataParityProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->connectorsPropertiesManager->update($dataParityProperty, Utils\ArrayHash::from([
						'value' => $dataParity?->getValue(),
					]));
				}

				if ($stopBitsProperty === null) {
					$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::RTU_STOP_BITS,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
						'value' => $stopBits?->getValue(),
						'connector' => $connector,
					]));
				} elseif ($stopBitsProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->connectorsPropertiesManager->update($stopBitsProperty, Utils\ArrayHash::from([
						'value' => $stopBits?->getValue(),
					]));
				}
			} else {
				if ($interfaceProperty !== null) {
					$this->connectorsPropertiesManager->delete($interfaceProperty);
				}

				if ($baudRateProperty !== null) {
					$this->connectorsPropertiesManager->delete($baudRateProperty);
				}

				if ($byteSizeProperty !== null) {
					$this->connectorsPropertiesManager->delete($byteSizeProperty);
				}

				if ($dataParityProperty !== null) {
					$this->connectorsPropertiesManager->delete($dataParityProperty);
				}

				if ($stopBitsProperty !== null) {
					$this->connectorsPropertiesManager->delete($stopBitsProperty);
				}
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//modbus-connector.cmd.install.messages.update.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//modbus-connector.cmd.install.messages.update.connector.error'));

			return;
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.manage.devices'),
			false,
		);

		$manage = (bool) $io->askQuestion($question);

		if (!$manage) {
			return;
		}

		$this->askManageConnectorAction($io, $connector);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 */
	private function deleteConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->info($this->translator->translate('//modbus-connector.cmd.base.messages.noConnectors'));

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//modbus-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$this->connectorsManager->delete($connector);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//modbus-connector.cmd.install.messages.remove.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//modbus-connector.cmd.install.messages.remove.connector.error'));
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function manageConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->info($this->translator->translate('//modbus-connector.cmd.base.messages.noConnectors'));

			return;
		}

		$this->askManageConnectorAction($io, $connector);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function listConnectors(Style\SymfonyStyle $io): void
	{
		$findConnectorsQuery = new Queries\Entities\FindConnectors();

		$connectors = $this->connectorsRepository->findAllBy($findConnectorsQuery, Entities\ModbusConnector::class);
		usort(
			$connectors,
			static function (Entities\ModbusConnector $a, Entities\ModbusConnector $b): int {
				if ($a->getIdentifier() === $b->getIdentifier()) {
					return $a->getName() <=> $b->getName();
				}

				return $a->getIdentifier() <=> $b->getIdentifier();
			},
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			$this->translator->translate('//modbus-connector.cmd.install.data.name'),
			$this->translator->translate('//modbus-connector.cmd.install.data.devicesCnt'),
		]);

		foreach ($connectors as $index => $connector) {
			$findDevicesQuery = new Queries\Entities\FindDevices();
			$findDevicesQuery->forConnector($connector);

			$devices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\ModbusDevice::class);

			$table->addRow([
				$index + 1,
				$connector->getName() ?? $connector->getIdentifier(),
				count($devices),
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function createDevice(Style\SymfonyStyle $io, Entities\ModbusConnector $connector): void
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.device.identifier'),
		);

		$question->setValidator(function (string|null $answer) {
			if ($answer !== '' && $answer !== null) {
				$findDeviceQuery = new Queries\Entities\FindDevices();
				$findDeviceQuery->byIdentifier($answer);

				$device = $this->devicesRepository->findOneBy($findDeviceQuery, Entities\ModbusDevice::class);

				if ($device !== null) {
					throw new Exceptions\Runtime(
						$this->translator->translate(
							'//modbus-connector.cmd.install.messages.identifier.device.used',
						),
					);
				}
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'modbus-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				$findDeviceQuery = new Queries\Entities\FindDevices();
				$findDeviceQuery->byIdentifier($identifier);

				$device = $this->devicesRepository->findOneBy($findDeviceQuery, Entities\ModbusDevice::class);

				if ($device === null) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error(
				$this->translator->translate('//modbus-connector.cmd.install.messages.identifier.device.missing'),
			);

			return;
		}

		$name = $this->askDeviceName($io);

		$address = $ipAddress = $port = $unitId = null;

		if ($connector->getClientMode()->equalsValue(Types\ClientMode::RTU)) {
			$address = $this->askDeviceAddress($io, $connector);
		}

		if ($connector->getClientMode()->equalsValue(Types\ClientMode::TCP)) {
			$ipAddress = $this->askDeviceIpAddress($io);

			if (
				preg_match(self::MATCH_IP_ADDRESS_PORT, $ipAddress, $matches) === 1
				&& array_key_exists('address', $matches)
				&& array_key_exists('port', $matches)
			) {
				$ipAddress = $matches['address'];
				$port = intval($matches['port']);
			} else {
				$port = $this->askDeviceIpAddressPort($io);
			}

			$unitId = $this->askDeviceUnitId($io, $connector);
		}

		$byteOrder = $this->askDeviceByteOrder($io);

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$device = $this->devicesManager->create(Utils\ArrayHash::from([
				'entity' => Entities\ModbusDevice::class,
				'connector' => $connector,
				'identifier' => $identifier,
				'name' => $name,
			]));
			assert($device instanceof Entities\ModbusDevice);

			if ($connector->getClientMode()->equalsValue(Types\ClientMode::RTU)) {
				$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Devices\Properties\Variable::class,
					'identifier' => Types\DevicePropertyIdentifier::ADDRESS,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $address,
					'device' => $device,
				]));
			}

			if ($connector->getClientMode()->equalsValue(Types\ClientMode::TCP)) {
				$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Devices\Properties\Variable::class,
					'identifier' => Types\DevicePropertyIdentifier::IP_ADDRESS,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $ipAddress,
					'device' => $device,
				]));

				$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Devices\Properties\Variable::class,
					'identifier' => Types\DevicePropertyIdentifier::PORT,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UINT),
					'value' => $port,
					'device' => $device,
				]));

				$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Devices\Properties\Variable::class,
					'identifier' => Types\DevicePropertyIdentifier::UNIT_ID,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $unitId,
					'device' => $device,
				]));
			}

			$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Devices\Properties\Variable::class,
				'identifier' => Types\DevicePropertyIdentifier::BYTE_ORDER,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
				'value' => $byteOrder->getValue(),
				'device' => $device,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//modbus-connector.cmd.install.messages.create.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				$this->translator->translate('//modbus-connector.cmd.install.messages.create.device.error'),
			);

			return;
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.create.registers'),
			true,
		);

		$createRegisters = (bool) $io->askQuestion($question);

		if ($createRegisters) {
			$this->createRegister($io, $device);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function editDevice(Style\SymfonyStyle $io, Entities\ModbusConnector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->warning($this->translator->translate('//modbus-connector.cmd.install.messages.noDevices'));

			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.create.device'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createDevice($io, $connector);
			}

			return;
		}

		$name = $this->askDeviceName($io, $device);

		$address = $ipAddress = $port = $unitId = null;

		$findDevicePropertyQuery = new DevicesQueries\Entities\FindDeviceProperties();
		$findDevicePropertyQuery->forDevice($device);
		$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::ADDRESS);

		$addressProperty = $this->devicesPropertiesRepository->findOneBy($findDevicePropertyQuery);

		$findDevicePropertyQuery = new DevicesQueries\Entities\FindDeviceProperties();
		$findDevicePropertyQuery->forDevice($device);
		$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::IP_ADDRESS);

		$ipAddressProperty = $this->devicesPropertiesRepository->findOneBy($findDevicePropertyQuery);

		$findDevicePropertyQuery = new DevicesQueries\Entities\FindDeviceProperties();
		$findDevicePropertyQuery->forDevice($device);
		$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::PORT);

		$portProperty = $this->devicesPropertiesRepository->findOneBy($findDevicePropertyQuery);

		$findDevicePropertyQuery = new DevicesQueries\Entities\FindDeviceProperties();
		$findDevicePropertyQuery->forDevice($device);
		$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::UNIT_ID);

		$unitIdProperty = $this->devicesPropertiesRepository->findOneBy($findDevicePropertyQuery);

		$findDevicePropertyQuery = new DevicesQueries\Entities\FindDeviceProperties();
		$findDevicePropertyQuery->forDevice($device);
		$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::BYTE_ORDER);

		$byteOrderProperty = $this->devicesPropertiesRepository->findOneBy($findDevicePropertyQuery);

		if ($connector->getClientMode()->equalsValue(Types\ClientMode::RTU)) {
			$address = $this->askDeviceAddress($io, $connector, $device);
		}

		if ($connector->getClientMode()->equalsValue(Types\ClientMode::TCP)) {
			$ipAddress = $this->askDeviceIpAddress($io, $device);

			if (
				preg_match(self::MATCH_IP_ADDRESS_PORT, $ipAddress, $matches) === 1
				&& array_key_exists('address', $matches)
				&& array_key_exists('port', $matches)
			) {
				$ipAddress = $matches['address'];
				$port = intval($matches['port']);
			} else {
				$port = $this->askDeviceIpAddressPort($io, $device);
			}

			$unitId = $this->askDeviceUnitId($io, $connector, $device);
		}

		$byteOrder = $this->askDeviceByteOrder($io, $device);

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$device = $this->devicesManager->update($device, Utils\ArrayHash::from([
				'name' => $name,
			]));
			assert($device instanceof Entities\ModbusDevice);

			if ($connector->getClientMode()->equalsValue(Types\ClientMode::RTU)) {
				if ($addressProperty === null) {
					$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Devices\Properties\Variable::class,
						'identifier' => Types\DevicePropertyIdentifier::ADDRESS,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
						'value' => $address,
						'device' => $device,
					]));
				} elseif ($addressProperty instanceof DevicesEntities\Devices\Properties\Variable) {
					$this->devicesPropertiesManager->update($addressProperty, Utils\ArrayHash::from([
						'value' => $address,
					]));
				}
			} elseif ($addressProperty !== null) {
				$this->devicesPropertiesManager->delete($addressProperty);
			}

			if ($connector->getClientMode()->equalsValue(Types\ClientMode::TCP)) {
				if ($ipAddressProperty === null) {
					$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Devices\Properties\Variable::class,
						'identifier' => Types\DevicePropertyIdentifier::IP_ADDRESS,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
						'value' => $ipAddress,
						'device' => $device,
					]));
				} elseif ($ipAddressProperty instanceof DevicesEntities\Devices\Properties\Variable) {
					$this->devicesPropertiesManager->update($ipAddressProperty, Utils\ArrayHash::from([
						'value' => $ipAddress,
					]));
				}

				if ($portProperty === null) {
					$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Devices\Properties\Variable::class,
						'identifier' => Types\DevicePropertyIdentifier::PORT,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
						'value' => $port,
						'device' => $device,
					]));
				} elseif ($portProperty instanceof DevicesEntities\Devices\Properties\Variable) {
					$this->devicesPropertiesManager->update($portProperty, Utils\ArrayHash::from([
						'value' => $port,
					]));
				}

				if ($unitIdProperty === null) {
					$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Devices\Properties\Variable::class,
						'identifier' => Types\DevicePropertyIdentifier::UNIT_ID,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
						'value' => $unitId,
						'device' => $device,
					]));
				} elseif ($unitIdProperty instanceof DevicesEntities\Devices\Properties\Variable) {
					$this->devicesPropertiesManager->update($unitIdProperty, Utils\ArrayHash::from([
						'value' => $unitId,
					]));
				}
			} else {
				if ($ipAddressProperty !== null) {
					$this->devicesPropertiesManager->delete($ipAddressProperty);
				}

				if ($portProperty !== null) {
					$this->devicesPropertiesManager->delete($portProperty);
				}

				if ($unitIdProperty !== null) {
					$this->devicesPropertiesManager->delete($unitIdProperty);
				}
			}

			if ($byteOrderProperty === null) {
				$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Devices\Properties\Variable::class,
					'identifier' => Types\DevicePropertyIdentifier::BYTE_ORDER,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $byteOrder->getValue(),
					'device' => $device,
				]));
			} elseif ($byteOrderProperty instanceof DevicesEntities\Devices\Properties\Variable) {
				$this->devicesPropertiesManager->update($byteOrderProperty, Utils\ArrayHash::from([
					'value' => $byteOrder->getValue(),
				]));
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//modbus-connector.cmd.install.messages.update.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//modbus-connector.cmd.install.messages.update.device.error'));

			return;
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.manage.registers'),
			false,
		);

		$manage = (bool) $io->askQuestion($question);

		if (!$manage) {
			return;
		}

		$this->askManageDeviceAction($io, $device);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 */
	private function deleteDevice(Style\SymfonyStyle $io, Entities\ModbusConnector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->warning($this->translator->translate('//modbus-connector.cmd.install.messages.noDevices'));

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//modbus-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$this->devicesManager->delete($device);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//modbus-connector.cmd.install.messages.remove.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//modbus-connector.cmd.install.messages.remove.device.error'));
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function manageDevice(Style\SymfonyStyle $io, Entities\ModbusConnector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->warning($this->translator->translate('//modbus-connector.cmd.install.messages.noDevices'));

			return;
		}

		$this->askManageDeviceAction($io, $device);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function listDevices(Style\SymfonyStyle $io, Entities\ModbusConnector $connector): void
	{
		$findDevicesQuery = new Queries\Entities\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$devices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\ModbusDevice::class);
		usort(
			$devices,
			static function (Entities\ModbusDevice $a, Entities\ModbusDevice $b): int {
				if ($a->getIdentifier() === $b->getIdentifier()) {
					return $a->getName() <=> $b->getName();
				}

				return $a->getIdentifier() <=> $b->getIdentifier();
			},
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			$this->translator->translate('//modbus-connector.cmd.install.data.name'),
			$this->translator->translate('//modbus-connector.cmd.install.data.address'),
			$this->translator->translate('//modbus-connector.cmd.install.data.discreteInputRegistersCnt'),
			$this->translator->translate('//modbus-connector.cmd.install.data.coilRegistersCnt'),
			$this->translator->translate('//modbus-connector.cmd.install.data.inputRegistersCnt'),
			$this->translator->translate('//modbus-connector.cmd.install.data.holdingRegistersCnt'),
		]);

		foreach ($devices as $index => $device) {
			$discreteInputRegisters = $coilRegisters = $inputRegisters = $holdingRegisters = 0;

			$findChannelsQuery = new Queries\Entities\FindChannels();
			$findChannelsQuery->forDevice($device);

			$channels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\ModbusChannel::class);

			foreach ($channels as $channel) {
				if ($channel->getRegisterType() !== null) {
					if ($channel->getRegisterType()->equalsValue(Types\ChannelType::DISCRETE_INPUT)) {
						++$discreteInputRegisters;
					} elseif ($channel->getRegisterType()->equalsValue(Types\ChannelType::COIL)) {
						++$coilRegisters;
					} elseif ($channel->getRegisterType()->equalsValue(Types\ChannelType::INPUT_REGISTER)) {
						++$inputRegisters;
					} elseif ($channel->getRegisterType()->equalsValue(Types\ChannelType::HOLDING_REGISTER)) {
						++$holdingRegisters;
					}
				}
			}

			$table->addRow([
				$index + 1,
				$device->getName() ?? $device->getIdentifier(),
				$connector->getClientMode()->equalsValue(Types\ClientMode::RTU)
					? $device->getAddress()
					: $device->getIpAddress() . ':' . $device->getPort(),
				$discreteInputRegisters,
				$coilRegisters,
				$inputRegisters,
				$holdingRegisters,
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function createRegister(Style\SymfonyStyle $io, Entities\ModbusDevice $device, bool $editMode = false): void
	{
		$type = $this->askRegisterType($io);

		$dataType = $this->askRegisterDataType($io, $type);

		$addresses = $this->askRegisterAddress($io, $device);

		if (is_int($addresses)) {
			$addresses = [$addresses, $addresses];
		}

		$name = $addresses[0] === $addresses[1] ? $this->askRegisterName($io) : null;

		$readingDelay = $this->askRegisterReadingDelay($io);

		$format = null;

		if (
			$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
		) {
			$format = $this->askRegisterFormat($io, $dataType);
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			foreach (range($addresses[0], $addresses[1]) as $address) {
				$channel = $this->channelsManager->create(Utils\ArrayHash::from([
					'entity' => Entities\ModbusChannel::class,
					'identifier' => $type . '_' . $address,
					'name' => $name,
					'device' => $device,
				]));

				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Variable::class,
					'identifier' => Types\ChannelPropertyIdentifier::ADDRESS,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $address,
					'channel' => $channel,
				]));

				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Variable::class,
					'identifier' => Types\ChannelPropertyIdentifier::TYPE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $type->getValue(),
					'channel' => $channel,
				]));

				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Variable::class,
					'identifier' => Types\ChannelPropertyIdentifier::READING_DELAY,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UINT),
					'value' => $readingDelay,
					'channel' => $channel,
				]));

				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Dynamic::class,
					'identifier' => Types\ChannelPropertyIdentifier::VALUE,
					'dataType' => $dataType,
					'format' => $format,
					'settable' => (
						$type->equalsValue(Types\ChannelType::COIL)
						|| $type->equalsValue(Types\ChannelType::HOLDING_REGISTER)
					),
					'queryable' => true,
					'channel' => $channel,
				]));
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			if ($addresses[0] === $addresses[1]) {
				$io->success(
					$this->translator->translate(
						'//modbus-connector.cmd.install.messages.create.register.success',
						['name' => $device->getName() ?? $device->getIdentifier()],
					),
				);
			} else {
				$io->success(
					$this->translator->translate(
						'//modbus-connector.cmd.install.messages.create.registers.success',
						['name' => $device->getName() ?? $device->getIdentifier()],
					),
				);
			}
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				$this->translator->translate('//modbus-connector.cmd.install.messages.create.register.error'),
			);

			return;
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		if ($editMode) {
			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.create.register'),
			false,
		);

		$create = (bool) $io->askQuestion($question);

		if ($create) {
			$this->createRegister($io, $device, $editMode);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function editRegister(Style\SymfonyStyle $io, Entities\ModbusDevice $device): void
	{
		$channel = $this->askWhichRegister($io, $device);

		if ($channel === null) {
			$io->warning($this->translator->translate('//modbus-connector.cmd.install.messages.noRegisters'));

			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.create.registers'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createRegister($io, $device);
			}

			return;
		}

		$type = $channel->getRegisterType();

		if ($type === null) {
			$type = $this->askRegisterType($io, $channel);
		}

		$dataType = $this->askRegisterDataType($io, $type, $channel);

		$address = $this->askRegisterAddress($io, $device, $channel);

		if (is_array($address)) {
			$address = $address[0];
		}

		$name = $this->askRegisterName($io, $channel);

		$readingDelay = $this->askRegisterReadingDelay($io, $channel);

		$format = null;

		if (
			$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
		) {
			$format = $this->askRegisterFormat($io, $dataType, $channel);
		}

		$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::ADDRESS);

		$addressProperty = $this->channelsPropertiesRepository->findOneBy($findChannelPropertyQuery);

		$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::TYPE);

		$typeProperty = $this->channelsPropertiesRepository->findOneBy($findChannelPropertyQuery);

		$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::READING_DELAY);

		$readingDelayProperty = $this->channelsPropertiesRepository->findOneBy($findChannelPropertyQuery);

		$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

		$valueProperty = $this->channelsPropertiesRepository->findOneBy($findChannelPropertyQuery);

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$channel = $this->channelsManager->update($channel, Utils\ArrayHash::from([
				'name' => $name,
			]));

			if ($addressProperty === null) {
				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Variable::class,
					'identifier' => Types\ChannelPropertyIdentifier::ADDRESS,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $address,
					'channel' => $channel,
				]));
			} else {
				$this->channelsPropertiesManager->update($addressProperty, Utils\ArrayHash::from([
					'value' => $address,
				]));
			}

			if ($typeProperty === null) {
				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Variable::class,
					'identifier' => Types\ChannelPropertyIdentifier::TYPE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $type->getValue(),
					'channel' => $channel,
				]));
			}

			if ($readingDelayProperty === null) {
				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Variable::class,
					'identifier' => Types\ChannelPropertyIdentifier::READING_DELAY,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $readingDelay,
					'channel' => $channel,
				]));
			} else {
				$this->channelsPropertiesManager->update($readingDelayProperty, Utils\ArrayHash::from([
					'value' => $readingDelay,
				]));
			}

			if ($valueProperty === null) {
				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Dynamic::class,
					'identifier' => Types\ChannelPropertyIdentifier::VALUE,
					'dataType' => $dataType,
					'format' => $format,
					'settable' => (
						$type->equalsValue(Types\ChannelType::COIL)
						|| $type->equalsValue(Types\ChannelType::HOLDING_REGISTER)
					),
					'queryable' => true,
					'channel' => $channel,
				]));
			} else {
				$this->channelsPropertiesManager->update($valueProperty, Utils\ArrayHash::from([
					'dataType' => $dataType,
					'format' => $format,
					'settable' => (
						$type->equalsValue(Types\ChannelType::COIL)
						|| $type->equalsValue(Types\ChannelType::HOLDING_REGISTER)
					),
				]));
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//modbus-connector.cmd.install.messages.update.register.success',
					['name' => $channel->getName() ?? $channel->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				$this->translator->translate('//modbus-connector.cmd.install.messages.update.register.error'),
			);
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function deleteRegister(Style\SymfonyStyle $io, Entities\ModbusDevice $device): void
	{
		$channel = $this->askWhichRegister($io, $device);

		if ($channel === null) {
			$io->warning($this->translator->translate('//modbus-connector.cmd.install.messages.noRegisters'));

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//modbus-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$this->channelsManager->delete($channel);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//modbus-connector.cmd.install.messages.remove.register.success',
					['name' => $channel->getName() ?? $channel->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'install-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				$this->translator->translate('//modbus-connector.cmd.install.messages.remove.register.error'),
			);
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function listRegisters(Style\SymfonyStyle $io, Entities\ModbusDevice $device): void
	{
		$findChannelsQuery = new Queries\Entities\FindChannels();
		$findChannelsQuery->forDevice($device);

		$deviceChannels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\ModbusChannel::class);
		usort(
			$deviceChannels,
			static function (Entities\ModbusChannel $a, Entities\ModbusChannel $b): int {
				if ($a->getRegisterType() === $b->getRegisterType()) {
					return $a->getAddress() <=> $b->getAddress();
				}

				return $a->getRegisterType() <=> $b->getRegisterType();
			},
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			$this->translator->translate('//modbus-connector.cmd.install.data.name'),
			$this->translator->translate('//modbus-connector.cmd.install.data.type'),
			$this->translator->translate('//modbus-connector.cmd.install.data.address'),
			$this->translator->translate('//modbus-connector.cmd.install.data.dataType'),
			$this->translator->translate('//modbus-connector.cmd.install.data.readingDelay'),
		]);

		foreach ($deviceChannels as $index => $channel) {
			$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelDynamicProperties();
			$findChannelPropertyQuery->forChannel($channel);
			$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

			$valueProperty = $this->channelsPropertiesRepository->findOneBy(
				$findChannelPropertyQuery,
				DevicesEntities\Channels\Properties\Dynamic::class,
			);

			$table->addRow([
				$index + 1,
				$channel->getName() ?? $channel->getIdentifier(),
				strval($channel->getRegisterType()?->getValue()),
				$channel->getAddress(),
				$valueProperty?->getDataType()->getValue(),
				$channel->getReadingDelay(),
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function askInstallAction(Style\SymfonyStyle $io): void
	{
		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.base.questions.whatToDo'),
			[
				0 => $this->translator->translate('//modbus-connector.cmd.install.actions.create.connector'),
				1 => $this->translator->translate('//modbus-connector.cmd.install.actions.update.connector'),
				2 => $this->translator->translate('//modbus-connector.cmd.install.actions.remove.connector'),
				3 => $this->translator->translate('//modbus-connector.cmd.install.actions.manage.connector'),
				4 => $this->translator->translate('//modbus-connector.cmd.install.actions.list.connectors'),
				5 => $this->translator->translate('//modbus-connector.cmd.install.actions.nothing'),
			],
			5,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.create.connector',
			)
			|| $whatToDo === '0'
		) {
			$this->createConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.update.connector',
			)
			|| $whatToDo === '1'
		) {
			$this->editConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.remove.connector',
			)
			|| $whatToDo === '2'
		) {
			$this->deleteConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.manage.connector',
			)
			|| $whatToDo === '3'
		) {
			$this->manageConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.list.connectors',
			)
			|| $whatToDo === '4'
		) {
			$this->listConnectors($io);

			$this->askInstallAction($io);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function askManageConnectorAction(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector $connector,
	): void
	{
		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.base.questions.whatToDo'),
			[
				0 => $this->translator->translate('//modbus-connector.cmd.install.actions.create.device'),
				1 => $this->translator->translate('//modbus-connector.cmd.install.actions.update.device'),
				2 => $this->translator->translate('//modbus-connector.cmd.install.actions.remove.device'),
				3 => $this->translator->translate('//modbus-connector.cmd.install.actions.manage.device'),
				4 => $this->translator->translate('//modbus-connector.cmd.install.actions.list.devices'),
				5 => $this->translator->translate('//modbus-connector.cmd.install.actions.nothing'),
			],
			5,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.create.device',
			)
			|| $whatToDo === '0'
		) {
			$this->createDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.update.device',
			)
			|| $whatToDo === '1'
		) {
			$this->editDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.remove.device',
			)
			|| $whatToDo === '2'
		) {
			$this->deleteDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.manage.device',
			)
			|| $whatToDo === '3'
		) {
			$this->manageDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.list.devices',
			)
			|| $whatToDo === '4'
		) {
			$this->listDevices($io, $connector);

			$this->askManageConnectorAction($io, $connector);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askManageDeviceAction(
		Style\SymfonyStyle $io,
		Entities\ModbusDevice $device,
	): void
	{
		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.base.questions.whatToDo'),
			[
				0 => $this->translator->translate('//modbus-connector.cmd.install.actions.create.register'),
				1 => $this->translator->translate('//modbus-connector.cmd.install.actions.update.register'),
				2 => $this->translator->translate('//modbus-connector.cmd.install.actions.remove.register'),
				3 => $this->translator->translate('//modbus-connector.cmd.install.actions.list.registers'),
				4 => $this->translator->translate('//modbus-connector.cmd.install.actions.nothing'),
			],
			4,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.create.register',
			)
			|| $whatToDo === '0'
		) {
			$this->createRegister($io, $device);

			$this->askManageDeviceAction($io, $device);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.update.register',
			)
			|| $whatToDo === '1'
		) {
			$this->editRegister($io, $device);

			$this->askManageDeviceAction($io, $device);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.remove.register',
			)
			|| $whatToDo === '2'
		) {
			$this->deleteRegister($io, $device);

			$this->askManageDeviceAction($io, $device);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//modbus-connector.cmd.install.actions.list.registers',
			)
			|| $whatToDo === '3'
		) {
			$this->listRegisters($io, $device);

			$this->askManageDeviceAction($io, $device);
		}
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askConnectorMode(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\ClientMode
	{
		$default = null;

		if ($connector !== null) {
			if ($connector->getClientMode()->equalsValue(Types\ClientMode::RTU)) {
				$default = 0;
			} elseif ($connector->getClientMode()->equalsValue(Types\ClientMode::TCP)) {
				$default = 1;
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.connector.mode'),
			[
				0 => $this->translator->translate('//modbus-connector.cmd.install.answers.mode.rtu'),
				1 => $this->translator->translate('//modbus-connector.cmd.install.answers.mode.tcp'),
			],
			$default ?? 0,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer): Types\ClientMode {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (
				$answer === $this->translator->translate(
					'//modbus-connector.cmd.install.answers.mode.rtu',
				)
				|| $answer === '0'
			) {
				return Types\ClientMode::get(Types\ClientMode::RTU);
			}

			if (
				$answer === $this->translator->translate(
					'//modbus-connector.cmd.install.answers.mode.tcp',
				)
				|| $answer === '1'
			) {
				return Types\ClientMode::get(Types\ClientMode::TCP);
			}

			throw new Exceptions\Runtime(
				sprintf($this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'), $answer),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\ClientMode);

		return $answer;
	}

	private function askConnectorName(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): string|null
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.connector.name'),
			$connector?->getName(),
		);

		$name = $io->askQuestion($question);

		return strval($name) === '' ? null : strval($name);
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askConnectorInterface(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): string
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.connector.rtuInterface'),
			$connector?->getRtuInterface(),
		);
		$question->setValidator(function (string|null $answer): string {
			if ($answer === '' || $answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			return $answer;
		});

		return strval($io->askQuestion($question));
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askConnectorBaudRate(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\BaudRate
	{
		$default = $connector?->getBaudRate()->getValue() ?? Types\BaudRate::RATE_9600;

		$baudRates = array_combine(
			array_values(Types\BaudRate::getValues()),
			array_map(static fn (int $item): string => strval($item), array_values(Types\BaudRate::getValues())),
		);

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.connector.baudRate'),
			array_values($baudRates),
			$default,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($baudRates): Types\BaudRate {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($baudRates))) {
				$answer = array_values($baudRates)[$answer];
			}

			$baudRate = array_search($answer, $baudRates, true);

			if ($baudRate !== false && Types\BaudRate::isValidValue($baudRate)) {
				return Types\BaudRate::get(intval($baudRate));
			}

			throw new Exceptions\Runtime(
				sprintf($this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'), $answer),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\BaudRate);

		return $answer;
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askConnectorByteSize(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\ByteSize
	{
		$default = $connector?->getByteSize()->getValue() ?? Types\ByteSize::SIZE_8;

		$byteSizes = array_combine(
			array_values(Types\ByteSize::getValues()),
			array_map(static fn (int $item): string => strval($item), array_values(Types\ByteSize::getValues())),
		);

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.connector.byteSize'),
			array_values($byteSizes),
			$default,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($byteSizes): Types\ByteSize {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($byteSizes))) {
				$answer = array_values($byteSizes)[$answer];
			}

			$byteSize = array_search($answer, $byteSizes, true);

			if ($byteSize !== false && Types\ByteSize::isValidValue($byteSize)) {
				return Types\ByteSize::get(intval($byteSize));
			}

			throw new Exceptions\Runtime(
				sprintf($this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'), $answer),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\ByteSize);

		return $answer;
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askConnectorDataParity(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\Parity
	{
		$default = 0;

		switch ($connector?->getParity()->getValue()) {
			case Types\Parity::ODD:
				$default = 1;

				break;
			case Types\Parity::EVEN:
				$default = 2;

				break;
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.connector.dataParity'),
			[
				0 => $this->translator->translate('//modbus-connector.cmd.install.answers.parity.none'),
				1 => $this->translator->translate('//modbus-connector.cmd.install.answers.parity.odd'),
				2 => $this->translator->translate('//modbus-connector.cmd.install.answers.parity.even'),
			],
			$default,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer): Types\Parity {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (
				$answer === $this->translator->translate(
					'//modbus-connector.cmd.install.answers.parity.none',
				)
				|| $answer === '0'
			) {
				return Types\Parity::get(Types\Parity::NONE);
			}

			if (
				$answer === $this->translator->translate(
					'//modbus-connector.cmd.install.answers.parity.odd',
				)
				|| $answer === '1'
			) {
				return Types\Parity::get(Types\Parity::ODD);
			}

			if (
				$answer === $this->translator->translate(
					'//modbus-connector.cmd.install.answers.parity.even',
				)
				|| $answer === '2'
			) {
				return Types\Parity::get(Types\Parity::EVEN);
			}

			throw new Exceptions\Runtime(
				sprintf($this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'), $answer),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\Parity);

		return $answer;
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askConnectorStopBits(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\StopBits
	{
		$default = $connector?->getStopBits()->getValue() ?? Types\StopBits::ONE;

		$stopBits = array_combine(
			array_values(Types\StopBits::getValues()),
			array_map(static fn (int $item): string => strval($item), array_values(Types\StopBits::getValues())),
		);

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.connector.stopBits'),
			array_values($stopBits),
			$default,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($stopBits): Types\StopBits {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($stopBits))) {
				$answer = array_values($stopBits)[$answer];
			}

			$stopBit = array_search($answer, $stopBits, true);

			if ($stopBit !== false && Types\StopBits::isValidValue($stopBit)) {
				return Types\StopBits::get(intval($stopBit));
			}

			throw new Exceptions\Runtime(
				sprintf($this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'), $answer),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\StopBits);

		return $answer;
	}

	private function askDeviceName(Style\SymfonyStyle $io, Entities\ModbusDevice|null $device = null): string|null
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.device.name'),
			$device?->getName(),
		);

		$name = $io->askQuestion($question);

		return strval($name) === '' ? null : strval($name);
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askDeviceAddress(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector $connector,
		Entities\ModbusDevice|null $device = null,
	): int
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.device.stationAddress'),
			$device?->getAddress(),
		);
		$question->setValidator(function (string|null $answer) use ($connector, $device) {
			if (strval(intval($answer)) !== strval($answer)) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			$findDevicesQuery = new Queries\Entities\FindDevices();
			$findDevicesQuery->forConnector($connector);

			foreach ($this->devicesRepository->findAllBy(
				$findDevicesQuery,
				Entities\ModbusDevice::class,
			) as $connectorDevice) {
				if (
					$connectorDevice->getAddress() === intval($answer)
					&& ($device === null || !$device->getId()->equals($connectorDevice->getId()))
				) {
					throw new Exceptions\Runtime(
						$this->translator->translate(
							'//modbus-connector.cmd.install.messages.deviceStationAddressTaken',
						),
					);
				}
			}

			return $answer;
		});

		return intval($io->askQuestion($question));
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askDeviceIpAddress(
		Style\SymfonyStyle $io,
		Entities\ModbusDevice|null $device = null,
	): string
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.device.ipAddress'),
			$device?->getIpAddress(),
		);
		$question->setValidator(function (string|null $answer) {
			if (
				preg_match(self::MATCH_IP_ADDRESS_PORT, strval($answer), $matches) === 1
				&& array_key_exists('address', $matches)
			) {
				if (array_key_exists('port', $matches)) {
					return $matches['address'] . ':' . $matches['port'];
				}

				return $matches['address'];
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		return strval($io->askQuestion($question));
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askDeviceIpAddressPort(
		Style\SymfonyStyle $io,
		Entities\ModbusDevice|null $device = null,
	): int
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.device.port'),
			$device?->getPort(),
		);
		$question->setValidator(function (string|null $answer) {
			if (strval(intval($answer)) !== strval($answer)) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			return $answer;
		});

		return intval($io->askQuestion($question));
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askDeviceUnitId(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector $connector,
		Entities\ModbusDevice|null $device = null,
	): int
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.device.unitIdentifier'),
			$device?->getUnitId(),
		);
		$question->setValidator(function (string|null $answer) use ($connector, $device) {
			if (strval(intval($answer)) !== strval($answer)) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			$findDevicesQuery = new Queries\Entities\FindDevices();
			$findDevicesQuery->forConnector($connector);

			foreach ($this->devicesRepository->findAllBy(
				$findDevicesQuery,
				Entities\ModbusDevice::class,
			) as $connectorDevice) {
				if (
					$connectorDevice->getUnitId() === intval($answer)
					&& ($device === null || !$device->getId()->equals($connectorDevice->getId()))
				) {
					throw new Exceptions\Runtime(
						$this->translator->translate('//modbus-connector.cmd.install.messages.unitIdentifierTaken'),
					);
				}
			}

			return $answer;
		});

		return intval($io->askQuestion($question));
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askDeviceByteOrder(
		Style\SymfonyStyle $io,
		Entities\ModbusDevice|null $device = null,
	): Types\ByteOrder
	{
		$default = 0;

		if ($device !== null) {
			if ($device->getByteOrder()->equalsValue(Types\ByteOrder::BIG_SWAP)) {
				$default = 1;
			} elseif ($device->getByteOrder()->equalsValue(Types\ByteOrder::LITTLE)) {
				$default = 2;
			} elseif ($device->getByteOrder()->equalsValue(Types\ByteOrder::LITTLE_SWAP)) {
				$default = 3;
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.device.byteOrder'),
			[
				0 => $this->translator->translate('//modbus-connector.cmd.install.answers.endian.big'),
				1 => $this->translator->translate('//modbus-connector.cmd.install.answers.endian.bigSwap'),
				2 => $this->translator->translate('//modbus-connector.cmd.install.answers.endian.little'),
				3 => $this->translator->translate('//modbus-connector.cmd.install.answers.endian.littleSwap'),
			],
			$default,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer): Types\ByteOrder {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (
				$answer === $this->translator->translate('//modbus-connector.cmd.install.answers.endian.big')
				|| $answer === '0'
			) {
				return Types\ByteOrder::get(Types\ByteOrder::BIG);
			}

			if (
				$answer === $this->translator->translate('//modbus-connector.cmd.install.answers.endian.bigSwap')
				|| $answer === '1'
			) {
				return Types\ByteOrder::get(Types\ByteOrder::BIG_SWAP);
			}

			if (
				$answer === $this->translator->translate('//modbus-connector.cmd.install.answers.endian.little')
				|| $answer === '2'
			) {
				return Types\ByteOrder::get(Types\ByteOrder::LITTLE);
			}

			if (
				$answer === $this->translator->translate('//modbus-connector.cmd.install.answers.endian.littleSwap')
				|| $answer === '3'
			) {
				return Types\ByteOrder::get(Types\ByteOrder::LITTLE_SWAP);
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\ByteOrder);

		return $answer;
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRegisterType(
		Style\SymfonyStyle $io,
		Entities\ModbusChannel|null $channel = null,
	): Types\ChannelType
	{
		if ($channel !== null) {
			$type = $channel->getRegisterType();

			$default = 0;

			if ($type !== null && $type->equalsValue(Types\ChannelType::COIL)) {
				$default = 1;
			} elseif ($type !== null && $type->equalsValue(Types\ChannelType::INPUT_REGISTER)) {
				$default = 2;
			} elseif ($type !== null && $type->equalsValue(Types\ChannelType::HOLDING_REGISTER)) {
				$default = 3;
			}

			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.select.register.updateType'),
				[
					$this->translator->translate('//modbus-connector.cmd.install.answers.registerType.discreteInput'),
					$this->translator->translate('//modbus-connector.cmd.install.answers.registerType.coil'),
					$this->translator->translate('//modbus-connector.cmd.install.answers.registerType.inputRegister'),
					$this->translator->translate('//modbus-connector.cmd.install.answers.registerType.holdingRegister'),
				],
				$default,
			);
		} else {
			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.select.register.createType'),
				[
					$this->translator->translate('//modbus-connector.cmd.install.answers.registerType.discreteInput'),
					$this->translator->translate('//modbus-connector.cmd.install.answers.registerType.coil'),
					$this->translator->translate('//modbus-connector.cmd.install.answers.registerType.inputRegister'),
					$this->translator->translate('//modbus-connector.cmd.install.answers.registerType.holdingRegister'),
				],
				0,
			);
		}

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer): Types\ChannelType {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (
				$answer === $this->translator->translate(
					'//modbus-connector.cmd.install.answers.registerType.discreteInput',
				)
				|| $answer === '0'
			) {
				return Types\ChannelType::get(Types\ChannelType::DISCRETE_INPUT);
			}

			if (
				$answer === $this->translator->translate('//modbus-connector.cmd.install.answers.registerType.coil')
				|| $answer === '1'
			) {
				return Types\ChannelType::get(Types\ChannelType::COIL);
			}

			if (
				$answer === $this->translator->translate(
					'//modbus-connector.cmd.install.answers.registerType.inputRegister',
				)
				|| $answer === '2'
			) {
				return Types\ChannelType::get(Types\ChannelType::INPUT_REGISTER);
			}

			if (
				$answer === $this->translator->translate(
					'//modbus-connector.cmd.install.answers.registerType.holdingRegister',
				)
				|| $answer === '3'
			) {
				return Types\ChannelType::get(Types\ChannelType::HOLDING_REGISTER);
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\ChannelType);

		return $answer;
	}

	/**
	 * @return int|array<int>
	 *
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRegisterAddress(
		Style\SymfonyStyle $io,
		Entities\ModbusDevice $device,
		Entities\ModbusChannel|null $channel = null,
	): int|array
	{
		$address = $channel?->getAddress();

		$question = new Console\Question\Question(
			(
			$channel !== null
				? $this->translator->translate('//modbus-connector.cmd.install.questions.provide.register.address')
				: $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.register.addresses',
				)
			),
			$address,
		);
		$question->setValidator(function (string|null $answer) use ($device, $channel) {
			if (strval(intval($answer)) === strval($answer)) {
				$findChannelsQuery = new Queries\Entities\FindChannels();
				$findChannelsQuery->forDevice($device);

				$channels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\ModbusChannel::class);

				foreach ($channels as $deviceChannel) {
					$address = $deviceChannel->getAddress();

					if (
						intval($address) === intval($answer)
						&& (
							$channel === null
							|| !$channel->getId()->equals($deviceChannel->getId())
						)
					) {
						throw new Exceptions\Runtime(
							$this->translator->translate(
								'//modbus-connector.cmd.install.messages.registerAddressTaken',
								['address' => intval($address)],
							),
						);
					}
				}

				return intval($answer);
			}

			if ($channel === null) {
				if (
					preg_match('/^([0-9]+)-([0-9]+)$/', strval($answer), $matches) === 1
					&& count($matches) === 3
				) {
					$start = intval($matches[1]);
					$end = intval($matches[2]);

					if ($start < $end) {
						$findChannelsQuery = new Queries\Entities\FindChannels();
						$findChannelsQuery->forDevice($device);

						$channels = $this->channelsRepository->findAllBy(
							$findChannelsQuery,
							Entities\ModbusChannel::class,
						);

						foreach ($channels as $deviceChannel) {
							$address = $deviceChannel->getAddress();

							if (intval($address) >= $start && intval($address) <= $end) {
								throw new Exceptions\Runtime(
									$this->translator->translate(
										'//modbus-connector.cmd.install.messages.registerAddressTaken',
										['address' => intval($address)],
									),
								);
							}
						}

						return [$start, $end];
					}
				}
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		/** @var int|array<int> $address */
		$address = $io->askQuestion($question);

		return $address;
	}

	private function askRegisterName(
		Style\SymfonyStyle $io,
		Entities\ModbusChannel|null $channel = null,
	): string|null
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.register.name'),
			$channel?->getName(),
		);

		$name = strval($io->askQuestion($question));

		return $name === '' ? null : $name;
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRegisterReadingDelay(
		Style\SymfonyStyle $io,
		Entities\ModbusChannel|null $channel = null,
	): string|null
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.register.readingDelay'),
			$channel?->getReadingDelay() ?? Entities\ModbusChannel::READING_DELAY,
		);

		$name = strval($io->askQuestion($question));

		return $name === '' ? null : $name;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 */
	private function askRegisterDataType(
		Style\SymfonyStyle $io,
		Types\ChannelType $type,
		Entities\ModbusChannel|null $channel = null,
	): MetadataTypes\DataType
	{
		$default = null;

		if (
			$type->equalsValue(Types\ChannelType::DISCRETE_INPUT)
			|| $type->equalsValue(Types\ChannelType::COIL)
		) {
			return MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_BOOLEAN);
		} elseif (
			$type->equalsValue(Types\ChannelType::HOLDING_REGISTER)
			|| $type->equalsValue(Types\ChannelType::INPUT_REGISTER)
		) {
			$dataTypes = [
				MetadataTypes\DataType::DATA_TYPE_CHAR,
				MetadataTypes\DataType::DATA_TYPE_UCHAR,
				MetadataTypes\DataType::DATA_TYPE_SHORT,
				MetadataTypes\DataType::DATA_TYPE_USHORT,
				MetadataTypes\DataType::DATA_TYPE_INT,
				MetadataTypes\DataType::DATA_TYPE_UINT,
				MetadataTypes\DataType::DATA_TYPE_FLOAT,
				MetadataTypes\DataType::DATA_TYPE_STRING,
			];

			$dataTypes[] = $type->equalsValue(Types\ChannelType::HOLDING_REGISTER)
				? MetadataTypes\DataType::DATA_TYPE_SWITCH
				: MetadataTypes\DataType::DATA_TYPE_BUTTON;

			if ($channel !== null) {
				$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
				$findChannelPropertyQuery->forChannel($channel);
				$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

				$valueProperty = $this->channelsPropertiesRepository->findOneBy($findChannelPropertyQuery);

				switch ($valueProperty?->getDataType()->getValue()) {
					case MetadataTypes\DataType::DATA_TYPE_CHAR:
						$default = 0;

						break;
					case MetadataTypes\DataType::DATA_TYPE_UCHAR:
						$default = 1;

						break;
					case MetadataTypes\DataType::DATA_TYPE_SHORT:
						$default = 2;

						break;
					case MetadataTypes\DataType::DATA_TYPE_USHORT:
						$default = 3;

						break;
					case MetadataTypes\DataType::DATA_TYPE_INT:
						$default = 4;

						break;
					case MetadataTypes\DataType::DATA_TYPE_UINT:
						$default = 5;

						break;
					case MetadataTypes\DataType::DATA_TYPE_FLOAT:
						$default = 6;

						break;
					case MetadataTypes\DataType::DATA_TYPE_STRING:
						$default = 7;

						break;
					case MetadataTypes\DataType::DATA_TYPE_SWITCH:
						$default = 8;

						break;
					case MetadataTypes\DataType::DATA_TYPE_BUTTON:
						$default = 9;

						break;
				}
			}
		} else {
			throw new Exceptions\InvalidArgument('Unknown register type');
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.provide.register.dataType'),
			$dataTypes,
			$default ?? $dataTypes[0],
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($dataTypes): MetadataTypes\DataType {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (MetadataTypes\DataType::isValidValue($answer)) {
				return MetadataTypes\DataType::get($answer);
			}

			if (
				array_key_exists($answer, $dataTypes)
				&& MetadataTypes\DataType::isValidValue($dataTypes[$answer])
			) {
				return MetadataTypes\DataType::get($dataTypes[$answer]);
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof MetadataTypes\DataType);

		return $answer;
	}

	/**
	 * @return array<int, array<int, array<int, string>>>|null
	 *
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRegisterFormat(
		Style\SymfonyStyle $io,
		MetadataTypes\DataType $dataType,
		Entities\ModbusChannel|null $channel = null,
	): array|null
	{
		$format = [];

		if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)) {
			foreach ([
				MetadataTypes\SwitchPayload::get(MetadataTypes\SwitchPayload::PAYLOAD_ON),
				MetadataTypes\SwitchPayload::get(MetadataTypes\SwitchPayload::PAYLOAD_OFF),
				MetadataTypes\SwitchPayload::get(MetadataTypes\SwitchPayload::PAYLOAD_TOGGLE),
			] as $payloadType) {
				$result = $this->askFormatSwitchAction($io, $payloadType, $channel);

				if ($result !== null) {
					$format[] = $result;
				}
			}

			return $format;
		} elseif ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)) {
			foreach ([
				MetadataTypes\ButtonPayload::get(MetadataTypes\ButtonPayload::PAYLOAD_PRESSED),
				MetadataTypes\ButtonPayload::get(MetadataTypes\ButtonPayload::PAYLOAD_RELEASED),
				MetadataTypes\ButtonPayload::get(MetadataTypes\ButtonPayload::PAYLOAD_CLICKED),
				MetadataTypes\ButtonPayload::get(MetadataTypes\ButtonPayload::PAYLOAD_DOUBLE_CLICKED),
				MetadataTypes\ButtonPayload::get(MetadataTypes\ButtonPayload::PAYLOAD_TRIPLE_CLICKED),
				MetadataTypes\ButtonPayload::get(MetadataTypes\ButtonPayload::PAYLOAD_LONG_CLICKED),
				MetadataTypes\ButtonPayload::get(MetadataTypes\ButtonPayload::PAYLOAD_EXTRA_LONG_CLICKED),
			] as $payloadType) {
				$result = $this->askFormatButtonAction($io, $payloadType, $channel);

				if ($result !== null) {
					$format[] = $result;
				}
			}

			return $format;
		}

		return null;
	}

	/**
	 * @return array<int, array<int, string>>|null
	 *
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askFormatSwitchAction(
		Style\SymfonyStyle $io,
		MetadataTypes\SwitchPayload $payload,
		Entities\ModbusChannel|null $channel = null,
	): array|null
	{
		$defaultReading = $defaultWriting = null;

		$existingProperty = null;

		if ($channel !== null) {
			$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
			$findChannelPropertyQuery->forChannel($channel);
			$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

			$existingProperty = $this->channelsPropertiesRepository->findOneBy($findChannelPropertyQuery);
		}

		$hasSupport = false;

		if ($existingProperty !== null) {
			$format = $existingProperty->getFormat();

			if ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				foreach ($format->getItems() as $item) {
					if (count($item) === 3) {
						if (
							$item[0] !== null
							&& $item[0]->getValue() instanceof MetadataTypes\SwitchPayload
							&& $item[0]->getValue()->equals($payload)
						) {
							$defaultReading = $item[1]?->toArray();
							$defaultWriting = $item[2]?->toArray();

							$hasSupport = true;
						}
					}
				}
			}
		}

		if ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_ON)) {
			$questionText = $this->translator->translate('//modbus-connector.cmd.install.questions.switch.hasOn');
		} elseif ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_OFF)) {
			$questionText = $this->translator->translate('//modbus-connector.cmd.install.questions.switch.hasOff');
		} elseif ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_TOGGLE)) {
			$questionText = $this->translator->translate('//modbus-connector.cmd.install.questions.switch.hasToggle');
		} else {
			throw new Exceptions\InvalidArgument('Provided payload type is not valid');
		}

		$question = new Console\Question\ConfirmationQuestion($questionText, $hasSupport);

		$support = (bool) $io->askQuestion($question);

		if (!$support) {
			return null;
		}

		return [
			[
				MetadataTypes\DataTypeShort::DATA_TYPE_SWITCH,
				strval($payload->getValue()),
			],
			$this->askFormatSwitchActionValues($io, $payload, true, $defaultReading),
			$this->askFormatSwitchActionValues($io, $payload, false, $defaultWriting),
		];
	}

	/**
	 * @param array<int, bool|float|int|string>|null $default
	 *
	 * @return array<int, string>
	 *
	 * @throws Exceptions\InvalidArgument
	 */
	private function askFormatSwitchActionValues(
		Style\SymfonyStyle $io,
		MetadataTypes\SwitchPayload $payload,
		bool $reading,
		array|null $default,
	): array
	{
		assert((is_array($default) && count($default) === 2) || $default === null);

		if ($reading) {
			if ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_ON)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.switch.readOnValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.switch.readOnValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_OFF)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.switch.readOffValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.switch.readOffValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_TOGGLE)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.switch.readToggleValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.switch.readToggleValueError',
				);
			} else {
				throw new Exceptions\InvalidArgument('Provided payload type is not valid');
			}
		} else {
			if ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_ON)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.switch.writeOnValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.switch.writeOnValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_OFF)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.switch.writeOffValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.switch.writeOffValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\SwitchPayload::PAYLOAD_TOGGLE)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.switch.writeToggleValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.switch.writeToggleValueError',
				);
			} else {
				throw new Exceptions\InvalidArgument('Provided payload type is not valid');
			}
		}

		$question = new Console\Question\Question($questionText, $default !== null ? $default[1] : null);
		$question->setValidator(function (string|null $answer) use ($io, $questionError): string|null {
			if (trim(strval($answer)) === '') {
				$question = new Console\Question\ConfirmationQuestion(
					$this->translator->translate('//modbus-connector.cmd.install.questions.skipValue'),
					true,
				);

				$skip = (bool) $io->askQuestion($question);

				if ($skip) {
					return null;
				}

				throw new Exceptions\Runtime($questionError);
			}

			return strval($answer);
		});

		$switchReading = $io->askQuestion($question);
		assert(is_string($switchReading) || $switchReading === null);

		if ($switchReading === null) {
			return [];
		}

		if (strval(intval($switchReading)) === $switchReading) {
			$dataTypes = [
				MetadataTypes\DataTypeShort::DATA_TYPE_BOOLEAN,
				MetadataTypes\DataTypeShort::DATA_TYPE_CHAR,
				MetadataTypes\DataTypeShort::DATA_TYPE_UCHAR,
				MetadataTypes\DataTypeShort::DATA_TYPE_SHORT,
				MetadataTypes\DataTypeShort::DATA_TYPE_USHORT,
				MetadataTypes\DataTypeShort::DATA_TYPE_INT,
				MetadataTypes\DataTypeShort::DATA_TYPE_UINT,
				MetadataTypes\DataTypeShort::DATA_TYPE_FLOAT,
			];

			$selected = null;

			if ($default !== null) {
				if ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_BOOLEAN) {
					$selected = 0;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_CHAR) {
					$selected = 1;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_UCHAR) {
					$selected = 2;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_SHORT) {
					$selected = 3;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_USHORT) {
					$selected = 4;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_INT) {
					$selected = 5;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_UINT) {
					$selected = 6;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_FLOAT) {
					$selected = 7;
				}
			}

			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.select.register.valueDataType'),
				$dataTypes,
				$selected,
			);

			$question->setErrorMessage(
				$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(function (string|null $answer) use ($dataTypes): string {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				}

				if (MetadataTypes\DataTypeShort::isValidValue($answer)) {
					return $answer;
				}

				if (
					array_key_exists($answer, $dataTypes)
					&& MetadataTypes\DataTypeShort::isValidValue($dataTypes[$answer])
				) {
					return $dataTypes[$answer];
				}

				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			});

			$dataType = strval($io->askQuestion($question));

			return [
				$dataType,
				$switchReading,
			];
		}

		return [
			MetadataTypes\DataTypeShort::DATA_TYPE_STRING,
			$switchReading,
		];
	}

	/**
	 * @return array<int, array<int, string>>|null
	 *
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askFormatButtonAction(
		Style\SymfonyStyle $io,
		MetadataTypes\ButtonPayload $payload,
		Entities\ModbusChannel|null $channel = null,
	): array|null
	{
		$defaultReading = $defaultWriting = null;

		$existingProperty = null;

		if ($channel !== null) {
			$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
			$findChannelPropertyQuery->forChannel($channel);
			$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

			$existingProperty = $this->channelsPropertiesRepository->findOneBy($findChannelPropertyQuery);
		}

		$hasSupport = false;

		if ($existingProperty !== null) {
			$format = $existingProperty->getFormat();

			if ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				foreach ($format->getItems() as $item) {
					if (count($item) === 3) {
						if (
							$item[0] !== null
							&& $item[0]->getValue() instanceof MetadataTypes\SwitchPayload
							&& $item[0]->getValue()->equals($payload)
						) {
							$defaultReading = $item[1]?->toArray();
							$defaultWriting = $item[2]?->toArray();

							$hasSupport = true;
						}
					}
				}
			}
		}

		if ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_PRESSED)) {
			$questionText = $this->translator->translate('//modbus-connector.cmd.install.questions.button.hasPress');
		} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_RELEASED)) {
			$questionText = $this->translator->translate('//modbus-connector.cmd.install.questions.button.hasRelease');
		} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_CLICKED)) {
			$questionText = $this->translator->translate('//modbus-connector.cmd.install.questions.button.hasClick');
		} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_DOUBLE_CLICKED)) {
			$questionText = $this->translator->translate(
				'//modbus-connector.cmd.install.questions.button.hasDoubleClick',
			);
		} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_TRIPLE_CLICKED)) {
			$questionText = $this->translator->translate(
				'//modbus-connector.cmd.install.questions.button.hasTripleClick',
			);
		} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_LONG_CLICKED)) {
			$questionText = $this->translator->translate(
				'//modbus-connector.cmd.install.questions.button.hasLongClick',
			);
		} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_EXTRA_LONG_CLICKED)) {
			$questionText = $this->translator->translate(
				'//modbus-connector.cmd.install.questions.button.hasExtraLongClick',
			);
		} else {
			throw new Exceptions\InvalidArgument('Provided payload type is not valid');
		}

		$question = new Console\Question\ConfirmationQuestion($questionText, $hasSupport);

		$support = (bool) $io->askQuestion($question);

		if (!$support) {
			return null;
		}

		return [
			[
				MetadataTypes\DataTypeShort::DATA_TYPE_BUTTON,
				strval($payload->getValue()),
			],
			$this->askFormatButtonActionValues($io, $payload, true, $defaultReading),
			$this->askFormatButtonActionValues($io, $payload, false, $defaultWriting),
		];
	}

	/**
	 * @param array<int, bool|float|int|string>|null $default
	 *
	 * @return array<int, string>
	 *
	 * @throws Exceptions\InvalidArgument
	 */
	private function askFormatButtonActionValues(
		Style\SymfonyStyle $io,
		MetadataTypes\ButtonPayload $payload,
		bool $reading,
		array|null $default,
	): array
	{
		assert((is_array($default) && count($default) === 2) || $default === null);

		if ($reading) {
			if ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_PRESSED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.readPressValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.readPressValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_RELEASED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.readReleaseValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.readReleaseValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.readClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.readClickValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_DOUBLE_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.readDoubleClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.readDoubleClickValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_TRIPLE_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.readTripleClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.readTripleClickValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_LONG_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.readLongClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.readLongClickValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_EXTRA_LONG_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.readExtraLongClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.readExtraLongClickValueError',
				);
			} else {
				throw new Exceptions\InvalidArgument('Provided payload type is not valid');
			}
		} else {
			if ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_PRESSED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.writePressValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.writePressValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_RELEASED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.writeReleaseValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.writeReleaseValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.writeClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.writeClickValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_DOUBLE_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.writeDoubleClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.writeDoubleClickValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_TRIPLE_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.writeTripleClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.writeTripleClickValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_LONG_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.writeLongClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.writeLongClickValueError',
				);
			} elseif ($payload->equalsValue(MetadataTypes\ButtonPayload::PAYLOAD_EXTRA_LONG_CLICKED)) {
				$questionText = $this->translator->translate(
					'//modbus-connector.cmd.install.questions.provide.button.writeExtraLongClickValue',
				);
				$questionError = $this->translator->translate(
					'//modbus-connector.cmd.install.messages.provide.button.writeExtraLongClickValueError',
				);
			} else {
				throw new Exceptions\InvalidArgument('Provided payload type is not valid');
			}
		}

		$question = new Console\Question\Question($questionText, $default !== null ? $default[1] : null);
		$question->setValidator(function (string|null $answer) use ($io, $questionError): string|null {
			if (trim(strval($answer)) === '') {
				$question = new Console\Question\ConfirmationQuestion(
					$this->translator->translate('//modbus-connector.cmd.install.questions.skipValue'),
					false,
				);

				$skip = (bool) $io->askQuestion($question);

				if ($skip) {
					return null;
				}

				throw new Exceptions\Runtime($questionError);
			}

			return $answer;
		});

		$switchReading = $io->askQuestion($question);
		assert(is_string($switchReading) || $switchReading === null);

		if ($switchReading === null) {
			return [];
		}

		if (strval(intval($switchReading)) === $switchReading) {
			$dataTypes = [
				MetadataTypes\DataTypeShort::DATA_TYPE_CHAR,
				MetadataTypes\DataTypeShort::DATA_TYPE_UCHAR,
				MetadataTypes\DataTypeShort::DATA_TYPE_SHORT,
				MetadataTypes\DataTypeShort::DATA_TYPE_USHORT,
				MetadataTypes\DataTypeShort::DATA_TYPE_INT,
				MetadataTypes\DataTypeShort::DATA_TYPE_UINT,
				MetadataTypes\DataTypeShort::DATA_TYPE_FLOAT,
			];

			$selected = null;

			if ($default !== null) {
				if ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_CHAR) {
					$selected = 0;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_UCHAR) {
					$selected = 1;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_SHORT) {
					$selected = 2;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_USHORT) {
					$selected = 3;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_INT) {
					$selected = 4;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_UINT) {
					$selected = 5;
				} elseif ($default[0] === MetadataTypes\DataTypeShort::DATA_TYPE_FLOAT) {
					$selected = 6;
				}
			}

			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//modbus-connector.cmd.install.questions.select.register.valueDataType'),
				$dataTypes,
				$selected,
			);

			$question->setErrorMessage(
				$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(function (string|null $answer) use ($dataTypes): string {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				}

				if (MetadataTypes\DataTypeShort::isValidValue($answer)) {
					return $answer;
				}

				if (
					array_key_exists($answer, $dataTypes)
					&& MetadataTypes\DataTypeShort::isValidValue($dataTypes[$answer])
				) {
					return $dataTypes[$answer];
				}

				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			});

			$dataType = strval($io->askQuestion($question));

			return [
				$dataType,
				$switchReading,
			];
		}

		return [
			MetadataTypes\DataTypeShort::DATA_TYPE_STRING,
			$switchReading,
		];
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichConnector(Style\SymfonyStyle $io): Entities\ModbusConnector|null
	{
		$connectors = [];

		$findConnectorsQuery = new Queries\Entities\FindConnectors();

		$systemConnectors = $this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\ModbusConnector::class,
		);
		usort(
			$systemConnectors,
			// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
			static fn (Entities\ModbusConnector $a, Entities\ModbusConnector $b): int => $a->getIdentifier() <=> $b->getIdentifier()
		);

		foreach ($systemConnectors as $connector) {
			$connectors[$connector->getIdentifier()] = $connector->getIdentifier()
				. ($connector->getName() !== null ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.item.connector'),
			array_values($connectors),
			count($connectors) === 1 ? 0 : null,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|int|null $answer) use ($connectors): Entities\ModbusConnector {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($connectors))) {
				$answer = array_values($connectors)[$answer];
			}

			$identifier = array_search($answer, $connectors, true);

			if ($identifier !== false) {
				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\ModbusConnector::class,
				);

				if ($connector !== null) {
					return $connector;
				}
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$connector = $io->askQuestion($question);
		assert($connector instanceof Entities\ModbusConnector);

		return $connector;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichDevice(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector $connector,
	): Entities\ModbusDevice|null
	{
		$devices = [];

		$findDevicesQuery = new Queries\Entities\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$connectorDevices = $this->devicesRepository->findAllBy(
			$findDevicesQuery,
			Entities\ModbusDevice::class,
		);
		usort(
			$connectorDevices,
			static fn (Entities\ModbusDevice $a, Entities\ModbusDevice $b): int => $a->getIdentifier() <=> $b->getIdentifier()
		);

		foreach ($connectorDevices as $device) {
			$devices[$device->getIdentifier()] = $device->getIdentifier()
				. ($device->getName() !== null ? ' [' . $device->getName() . ']' : '');
		}

		if (count($devices) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.item.device'),
			array_values($devices),
			count($devices) === 1 ? 0 : null,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(
			function (string|int|null $answer) use ($connector, $devices): Entities\ModbusDevice {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				}

				if (array_key_exists($answer, array_values($devices))) {
					$answer = array_values($devices)[$answer];
				}

				$identifier = array_search($answer, $devices, true);

				if ($identifier !== false) {
					$findDeviceQuery = new Queries\Entities\FindDevices();
					$findDeviceQuery->byIdentifier($identifier);
					$findDeviceQuery->forConnector($connector);

					$device = $this->devicesRepository->findOneBy(
						$findDeviceQuery,
						Entities\ModbusDevice::class,
					);

					if ($device !== null) {
						return $device;
					}
				}

				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			},
		);

		$device = $io->askQuestion($question);
		assert($device instanceof Entities\ModbusDevice);

		return $device;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askWhichRegister(
		Style\SymfonyStyle $io,
		Entities\ModbusDevice $device,
	): Entities\ModbusChannel|null
	{
		$channels = [];

		$findChannelsQuery = new Queries\Entities\FindChannels();
		$findChannelsQuery->forDevice($device);

		$deviceChannels = $this->channelsRepository->findAllBy(
			$findChannelsQuery,
			Entities\ModbusChannel::class,
		);
		usort(
			$deviceChannels,
			static fn (Entities\ModbusChannel $a, Entities\ModbusChannel $b): int => $a->getIdentifier() <=> $b->getIdentifier()
		);

		foreach ($deviceChannels as $channel) {
			$channels[$channel->getIdentifier()] = sprintf(
				'%s %s, Type: %s, Address: %d',
				$channel->getIdentifier(),
				($channel->getName() !== null ? ' [' . $channel->getName() . ']' : ''),
				strval($channel->getRegisterType()?->getValue()),
				$channel->getAddress(),
			);
		}

		if (count($channels) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//modbus-connector.cmd.install.questions.select.item.register'),
			array_values($channels),
			count($channels) === 1 ? 0 : null,
		);

		$question->setErrorMessage(
			$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(
			function (string|int|null $answer) use ($device, $channels): Entities\ModbusChannel {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				}

				if (array_key_exists($answer, array_values($channels))) {
					$answer = array_values($channels)[$answer];
				}

				$identifier = array_search($answer, $channels, true);

				if ($identifier !== false) {
					$findChannelQuery = new Queries\Entities\FindChannels();
					$findChannelQuery->byIdentifier($identifier);
					$findChannelQuery->forDevice($device);

					$channel = $this->channelsRepository->findOneBy(
						$findChannelQuery,
						Entities\ModbusChannel::class,
					);

					if ($channel !== null) {
						return $channel;
					}
				}

				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//modbus-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			},
		);

		$channel = $io->askQuestion($question);
		assert($channel instanceof Entities\ModbusChannel);

		return $channel;
	}

	/**
	 * @throws Exceptions\Runtime
	 */
	private function getOrmConnection(): DBAL\Connection
	{
		$connection = $this->managerRegistry->getConnection();

		if ($connection instanceof DBAL\Connection) {
			return $connection;
		}

		throw new Exceptions\Runtime('Database connection could not be established');
	}

}
