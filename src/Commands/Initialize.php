<?php declare(strict_types = 1);

/**
 * Initialize.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Commands
 * @since          1.0.0
 *
 * @date           04.08.22
 */

namespace FastyBird\Connector\Modbus\Commands;

use Doctrine\DBAL;
use Doctrine\Persistence;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Nette\Utils;
use Psr\Log;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;
use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_values;
use function assert;
use function count;
use function intval;
use function sprintf;
use function strval;
use function usort;

/**
 * Connector initialize command
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Initialize extends Console\Command\Command
{

	public const NAME = 'fb:modbus-connector:initialize';

	private const CHOICE_QUESTION_CREATE_CONNECTOR = 'Create new connector configuration';

	private const CHOICE_QUESTION_EDIT_CONNECTOR = 'Edit existing connector configuration';

	private const CHOICE_QUESTION_DELETE_CONNECTOR = 'Delete existing connector configuration';

	private const CHOICE_QUESTION_RTU_MODE = 'Modbus RTU devices over serial line';

	private const CHOICE_QUESTION_TCP_MODE = 'Modbus devices over TCP network';

	private const CHOICE_QUESTION_PARITY_NONE = 'None';

	private const CHOICE_QUESTION_PARITY_ODD = 'Odd verification';

	private const CHOICE_QUESTION_PARITY_EVEN = 'Even verification';

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly DevicesModels\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Connectors\ConnectorsManager $connectorsManager,
		private readonly DevicesModels\Connectors\Properties\PropertiesManager $propertiesManager,
		private readonly DevicesModels\Connectors\Controls\ControlsManager $controlsManager,
		private readonly Persistence\ManagerRegistry $managerRegistry,
		Log\LoggerInterface|null $logger = null,
		string|null $name = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 */
	protected function configure(): void
	{
		$this
			->setName(self::NAME)
			->setDescription('Modbus connector initialization')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption(
						'no-confirm',
						null,
						Input\InputOption::VALUE_NONE,
						'Do not ask for any confirmation',
					),
				]),
			);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws Console\Exception\InvalidArgumentException
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title('Modbus connector - initialization');

		$io->note('This action will create|update|delete connector configuration.');

		if ($input->getOption('no-confirm') === false) {
			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to continue?',
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if (!$continue) {
				return Console\Command\Command::SUCCESS;
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			'What would you like to do?',
			[
				0 => self::CHOICE_QUESTION_CREATE_CONNECTOR,
				1 => self::CHOICE_QUESTION_EDIT_CONNECTOR,
				2 => self::CHOICE_QUESTION_DELETE_CONNECTOR,
			],
		);
		$question->setErrorMessage('Selected answer: "%s" is not valid.');

		$whatToDo = $io->askQuestion($question);

		if ($whatToDo === self::CHOICE_QUESTION_CREATE_CONNECTOR) {
			$this->createNewConfiguration($io);

		} elseif ($whatToDo === self::CHOICE_QUESTION_EDIT_CONNECTOR) {
			$this->editExistingConfiguration($io);

		} elseif ($whatToDo === self::CHOICE_QUESTION_DELETE_CONNECTOR) {
			$this->deleteExistingConfiguration($io);
		}

		return Console\Command\Command::SUCCESS;
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function createNewConfiguration(Style\SymfonyStyle $io): void
	{
		$mode = $this->askMode($io);

		$question = new Console\Question\Question('Provide connector identifier');

		$question->setValidator(function (string|null $answer) {
			if ($answer !== '' && $answer !== null) {
				$findConnectorQuery = new DevicesQueries\FindConnectors();
				$findConnectorQuery->byIdentifier($answer);

				if (
					$this->connectorsRepository->findOneBy(
						$findConnectorQuery,
						Entities\ModbusConnector::class,
					) !== null
				) {
					throw new Exceptions\Runtime('This identifier is already used');
				}
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'modbus-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				$findConnectorQuery = new DevicesQueries\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				if (
					$this->connectorsRepository->findOneBy(
						$findConnectorQuery,
						Entities\ModbusConnector::class,
					) === null
				) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error('Connector identifier have to be provided');

			return;
		}

		$name = $this->askName($io);

		$interface = $baudRate = $byteSize = $dataParity = $stopBits = null;

		if ($mode->equalsValue(Types\ClientMode::MODE_RTU)) {
			$interface = $this->askRtuInterface($io);
			$baudRate = $this->askRtuBaudRate($io);
			$byteSize = $this->askRtuByteSize($io);
			$dataParity = $this->askRtuDataParity($io);
			$stopBits = $this->askRtuStopBits($io);
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->create(Utils\ArrayHash::from([
				'entity' => Entities\ModbusConnector::class,
				'identifier' => $identifier,
				'name' => $name,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
				'value' => $mode->getValue(),
				'connector' => $connector,
			]));

			if ($mode->equalsValue(Types\ClientMode::MODE_RTU)) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_INTERFACE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $interface,
					'connector' => $connector,
				]));

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BAUD_RATE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UINT),
					'value' => $baudRate?->getValue(),
					'connector' => $connector,
				]));

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BYTE_SIZE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $byteSize?->getValue(),
					'connector' => $connector,
				]));

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_PARITY,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $dataParity?->getValue(),
					'connector' => $connector,
				]));

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_STOP_BITS,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $stopBits?->getValue(),
					'connector' => $connector,
				]));
			}

			$this->controlsManager->create(Utils\ArrayHash::from([
				'name' => Types\ConnectorControlName::NAME_REBOOT,
				'connector' => $connector,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'New connector "%s" was successfully created',
				$connector->getName() ?? $connector->getIdentifier(),
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error('Something went wrong, connector could not be created. Error was logged.');
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
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function editExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->warning('No Modbus connectors registered in system');

			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to create new Modbus connector configuration?',
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createNewConfiguration($io);
			}

			return;
		}

		$modeProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE);

		if ($modeProperty === null) {
			$changeMode = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to change connector devices support?',
				false,
			);

			$changeMode = (bool) $io->askQuestion($question);
		}

		$mode = null;

		if ($changeMode) {
			$mode = $this->askMode($io);
		}

		$name = $this->askName($io, $connector);

		$enabled = $connector->isEnabled();

		if ($connector->isEnabled()) {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to disable connector?',
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = false;
			}
		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to enable connector?',
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = true;
			}
		}

		$interface = $baudRate = $byteSize = $dataParity = $stopBits = null;

		if (
			(
				$mode !== null
				&& $mode->equalsValue(Types\ClientMode::MODE_RTU)
			)
			|| $connector->getClientMode()->equalsValue(Types\ClientMode::MODE_RTU)
		) {
			$interface = $this->askRtuInterface($io, $connector);
			$baudRate = $this->askRtuBaudRate($io, $connector);
			$byteSize = $this->askRtuByteSize($io, $connector);
			$dataParity = $this->askRtuDataParity($io, $connector);
			$stopBits = $this->askRtuStopBits($io, $connector);
		}

		$interfaceProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_INTERFACE);
		$baudRateProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BAUD_RATE);
		$byteSizeProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BYTE_SIZE);
		$dataParityProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_PARITY);
		$stopBitsProperty = $connector->findProperty(Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_STOP_BITS);

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
					$mode = $this->askMode($io);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'value' => $mode->getValue(),
					'connector' => $connector,
				]));
			} elseif ($mode !== null) {
				$this->propertiesManager->update($modeProperty, Utils\ArrayHash::from([
					'value' => $mode->getValue(),
				]));
			}

			if (
				(
					$mode !== null
					&& $mode->equalsValue(Types\ClientMode::MODE_RTU)
				)
				|| $connector->getClientMode()->equalsValue(Types\ClientMode::MODE_RTU)
			) {
				if ($interfaceProperty === null) {
					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_INTERFACE,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
						'value' => $interface,
						'connector' => $connector,
					]));
				} elseif ($interfaceProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->propertiesManager->update($interfaceProperty, Utils\ArrayHash::from([
						'value' => $interface,
					]));
				}

				if ($baudRateProperty === null) {
					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BAUD_RATE,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UINT),
						'value' => $baudRate?->getValue(),
						'connector' => $connector,
					]));
				} elseif ($baudRateProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->propertiesManager->update($baudRateProperty, Utils\ArrayHash::from([
						'value' => $baudRate?->getValue(),
					]));
				}

				if ($byteSizeProperty === null) {
					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BYTE_SIZE,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
						'value' => $byteSize?->getValue(),
						'connector' => $connector,
					]));
				} elseif ($byteSizeProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->propertiesManager->update($byteSizeProperty, Utils\ArrayHash::from([
						'value' => $byteSize?->getValue(),
					]));
				}

				if ($dataParityProperty === null) {
					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_PARITY,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
						'value' => $dataParity?->getValue(),
						'connector' => $connector,
					]));
				} elseif ($dataParityProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->propertiesManager->update($dataParityProperty, Utils\ArrayHash::from([
						'value' => $dataParity?->getValue(),
					]));
				}

				if ($stopBitsProperty === null) {
					$this->propertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_STOP_BITS,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
						'value' => $stopBits?->getValue(),
						'connector' => $connector,
					]));
				} elseif ($stopBitsProperty instanceof DevicesEntities\Connectors\Properties\Variable) {
					$this->propertiesManager->update($stopBitsProperty, Utils\ArrayHash::from([
						'value' => $stopBits?->getValue(),
					]));
				}
			} else {
				if ($interfaceProperty !== null) {
					$this->propertiesManager->delete($interfaceProperty);
				}

				if ($baudRateProperty !== null) {
					$this->propertiesManager->delete($baudRateProperty);
				}

				if ($byteSizeProperty !== null) {
					$this->propertiesManager->delete($byteSizeProperty);
				}

				if ($dataParityProperty !== null) {
					$this->propertiesManager->delete($dataParityProperty);
				}

				if ($stopBitsProperty !== null) {
					$this->propertiesManager->delete($stopBitsProperty);
				}
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'Connector "%s" was successfully updated',
				$connector->getName() ?? $connector->getIdentifier(),
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error('Something went wrong, connector could not be updated. Error was logged.');
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
	 */
	private function deleteExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->info('No Modbus connectors registered in system');

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			'Would you like to continue?',
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

			$io->success(sprintf(
				'Connector "%s" was successfully removed',
				$connector->getName() ?? $connector->getIdentifier(),
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_MODBUS,
					'type' => 'initialize-cmd',
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error('Something went wrong, connector could not be removed. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	private function askMode(Style\SymfonyStyle $io): Types\ClientMode
	{
		$question = new Console\Question\ChoiceQuestion(
			'What type of Modbus devices will this connector handle?',
			[
				self::CHOICE_QUESTION_RTU_MODE,
				self::CHOICE_QUESTION_TCP_MODE,
			],
			0,
		);
		$question->setErrorMessage('Selected answer: "%s" is not valid.');
		$question->setValidator(static function (string|null $answer): Types\ClientMode {
			if ($answer === null) {
				throw new Exceptions\InvalidState('Selected answer is not valid');
			}

			if ($answer === self::CHOICE_QUESTION_RTU_MODE || intval($answer) === 0) {
				return Types\ClientMode::get(Types\ClientMode::MODE_RTU);
			}

			if ($answer === self::CHOICE_QUESTION_TCP_MODE || intval($answer) === 1) {
				return Types\ClientMode::get(Types\ClientMode::MODE_TCP);
			}

			throw new Exceptions\InvalidState('Selected answer is not valid');
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\ClientMode);

		return $answer;
	}

	private function askName(Style\SymfonyStyle $io, Entities\ModbusConnector|null $connector = null): string|null
	{
		$question = new Console\Question\Question('Provide connector name', $connector?->getName());

		$name = $io->askQuestion($question);

		return $name === '' ? null : strval($name);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRtuInterface(Style\SymfonyStyle $io, Entities\ModbusConnector|null $connector = null): string
	{
		$question = new Console\Question\Question('Provide interface path', $connector?->getRtuInterface());
		$question->setValidator(static function (string|null $answer): string {
			if ($answer === '' || $answer === null) {
				throw new Exceptions\Runtime('You have to provide valid interface path');
			}

			return $answer;
		});

		return strval($io->askQuestion($question));
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRtuByteSize(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\ByteSize
	{
		$default = $connector?->getByteSize()->getValue() ?? Types\ByteSize::SIZE_8;

		$question = new Console\Question\ChoiceQuestion(
			'What byte size device uses?',
			array_combine(
				array_values(Types\ByteSize::getValues()),
				array_values(Types\ByteSize::getValues()),
			),
			$default,
		);
		$question->setErrorMessage('Selected answer: "%s" is not valid.');
		$question->setValidator(static function (string|null $answer): Types\ByteSize {
			if ($answer === null) {
				throw new Exceptions\InvalidState('Selected answer is not valid');
			}

			foreach ((array) Types\ByteSize::getAvailableValues() as $value) {
				if (intval($answer) === $value) {
					return Types\ByteSize::get(intval($value));
				}
			}

			throw new Exceptions\InvalidState('Selected answer is not valid');
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\ByteSize);

		return $answer;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRtuBaudRate(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\BaudRate
	{
		$default = $connector?->getBaudRate()->getValue() ?? Types\BaudRate::BAUD_RATE_9600;

		$question = new Console\Question\ChoiceQuestion(
			'What communication baud rate devices use?',
			array_combine(
				array_values(Types\BaudRate::getValues()),
				array_values(Types\BaudRate::getValues()),
			),
			$default,
		);
		$question->setErrorMessage('Selected answer: "%s" is not valid.');
		$question->setValidator(static function (string|null $answer): Types\BaudRate {
			if ($answer === null) {
				throw new Exceptions\InvalidState('Selected answer is not valid');
			}

			foreach ((array) Types\BaudRate::getAvailableValues() as $value) {
				if (intval($answer) === $value) {
					return Types\BaudRate::get(intval($value));
				}
			}

			throw new Exceptions\InvalidState('Selected answer is not valid');
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\BaudRate);

		return $answer;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRtuDataParity(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\Parity
	{
		$default = 0;

		switch ($connector?->getParity()->getValue()) {
			case Types\Parity::PARITY_ODD:
				$default = 1;

				break;
			case Types\Parity::PARITY_EVEN:
				$default = 2;

				break;
		}

		$question = new Console\Question\ChoiceQuestion(
			'What parity checking devices use?',
			[
				self::CHOICE_QUESTION_PARITY_NONE,
				self::CHOICE_QUESTION_PARITY_ODD,
				self::CHOICE_QUESTION_PARITY_EVEN,
			],
			$default,
		);
		$question->setErrorMessage('Selected answer: "%s" is not valid.');
		$question->setValidator(static function (string|null $answer): Types\Parity {
			if ($answer === null) {
				throw new Exceptions\InvalidState('Selected answer is not valid');
			}

			if ($answer === self::CHOICE_QUESTION_PARITY_NONE || intval($answer) === 0) {
				return Types\Parity::get(Types\Parity::PARITY_NONE);
			} elseif ($answer === self::CHOICE_QUESTION_PARITY_ODD || intval($answer) === 1) {
				return Types\Parity::get(Types\Parity::PARITY_ODD);
			} elseif ($answer === self::CHOICE_QUESTION_PARITY_EVEN || intval($answer) === 2) {
				return Types\Parity::get(Types\Parity::PARITY_EVEN);
			}

			throw new Exceptions\InvalidState('Selected answer is not valid');
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\Parity);

		return $answer;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askRtuStopBits(
		Style\SymfonyStyle $io,
		Entities\ModbusConnector|null $connector = null,
	): Types\StopBits
	{
		$default = $connector?->getStopBits()->getValue() ?? Types\StopBits::STOP_BIT_ONE;

		$question = new Console\Question\ChoiceQuestion(
			'How many stop bits devices use?',
			array_combine(
				array_values(Types\StopBits::getValues()),
				array_values(Types\StopBits::getValues()),
			),
			$default,
		);
		$question->setErrorMessage('Selected answer: "%s" is not valid.');
		$question->setValidator(static function (string|null $answer): Types\StopBits {
			if ($answer === null) {
				throw new Exceptions\InvalidState('Selected answer is not valid');
			}

			foreach ((array) Types\StopBits::getAvailableValues() as $value) {
				if ($value === intval($answer)) {
					return Types\StopBits::get(intval($value));
				}
			}

			throw new Exceptions\InvalidState('Selected answer is not valid');
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\StopBits);

		return $answer;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichConnector(Style\SymfonyStyle $io): Entities\ModbusConnector|null
	{
		$connectors = [];

		$findConnectorsQuery = new DevicesQueries\FindConnectors();

		$systemConnectors = $this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\ModbusConnector::class,
		);
		usort(
			$systemConnectors,
			// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
			static fn (DevicesEntities\Connectors\Connector $a, DevicesEntities\Connectors\Connector $b): int => $a->getIdentifier() <=> $b->getIdentifier()
		);

		foreach ($systemConnectors as $connector) {
			assert($connector instanceof Entities\ModbusConnector);

			$connectors[$connector->getIdentifier()] = $connector->getIdentifier()
				. ($connector->getName() !== null ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			'Please select connector to manage',
			array_values($connectors),
		);
		$question->setErrorMessage('Selected answer: "%s" is not valid.');
		$question->setValidator(function (string|null $answer) use ($connectors): Entities\ModbusConnector {
			if ($answer === null) {
				throw new Exceptions\InvalidState('Selected answer is not valid');
			}

			$connectorIdentifiers = array_keys($connectors);

			if (!array_key_exists(intval($answer), $connectorIdentifiers)) {
				throw new Exceptions\Runtime('You have to select connector from list');
			}

			$findConnectorQuery = new DevicesQueries\FindConnectors();
			$findConnectorQuery->byIdentifier($connectorIdentifiers[intval($answer)]);

			$connector = $this->connectorsRepository->findOneBy($findConnectorQuery, Entities\ModbusConnector::class);
			assert($connector instanceof Entities\ModbusConnector || $connector === null);

			if ($connector === null) {
				throw new Exceptions\Runtime('You have to select connector from list');
			}

			return $connector;
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Entities\ModbusConnector);

		return $answer;
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

		throw new Exceptions\Runtime('Entity manager could not be loaded');
	}

}
