<?php declare(strict_types = 1);

/**
 * ModbusExtension.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           30.01.22
 */

namespace FastyBird\Connector\Modbus\DI;

use Contributte\Translation;
use Doctrine\Persistence;
use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Clients;
use FastyBird\Connector\Modbus\Commands;
use FastyBird\Connector\Modbus\Connector;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Hydrators;
use FastyBird\Connector\Modbus\Queue;
use FastyBird\Connector\Modbus\Schemas;
use FastyBird\Connector\Modbus\Subscribers;
use FastyBird\Connector\Modbus\Writers;
use FastyBird\Library\Application\Boot as ApplicationBoot;
use FastyBird\Library\Exchange\DI as ExchangeDI;
use FastyBird\Library\Metadata;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Module\Devices\DI as DevicesDI;
use Nette\Bootstrap;
use Nette\DI;
use Nettrine\Fixtures as NettrineFixtures;
use Nettrine\ORM as NettrineORM;
use function array_keys;
use function array_pop;
use const DIRECTORY_SEPARATOR;

/**
 * Modbus connector
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ModbusExtension extends DI\CompilerExtension implements Translation\DI\TranslationProviderInterface
{

	public const NAME = 'fbModbusConnector';

	public static function register(
		ApplicationBoot\Configurator $config,
		string $extensionName = self::NAME,
	): void
	{
		$config->onCompile[] = static function (
			Bootstrap\Configurator $config,
			DI\Compiler $compiler,
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new self());
		};
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$logger = $builder->addDefinition($this->prefix('logger'), new DI\Definitions\ServiceDefinition())
			->setType(Modbus\Logger::class)
			->setAutowired(false);

		/**
		 * WRITERS
		 */

		$builder->addFactoryDefinition($this->prefix('writers.event'))
			->setImplement(Writers\EventFactory::class)
			->getResultDefinition()
			->setType(Writers\Event::class);

		$builder->addFactoryDefinition($this->prefix('writers.exchange'))
			->setImplement(Writers\ExchangeFactory::class)
			->getResultDefinition()
			->setType(Writers\Exchange::class)
			->addTag(ExchangeDI\ExchangeExtension::CONSUMER_STATE, false);

		/**
		 * CLIENTS
		 */

		$builder->addFactoryDefinition($this->prefix('clients.rtu'))
			->setImplement(Clients\RtuFactory::class)
			->getResultDefinition()
			->setType(Clients\Rtu::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addFactoryDefinition($this->prefix('clients.tcp'))
			->setImplement(Clients\TcpFactory::class)
			->getResultDefinition()
			->setType(Clients\Tcp::class)
			->setArguments([
				'logger' => $logger,
			]);

		/**
		 * API
		 */

		$builder->addDefinition($this->prefix('api.connectionsManager'), new DI\Definitions\ServiceDefinition())
			->setType(API\ConnectionManager::class);

		$builder->addFactoryDefinition($this->prefix('api.rtu'))
			->setImplement(API\RtuFactory::class)
			->getResultDefinition()
			->setType(API\Rtu::class);

		$builder->addFactoryDefinition($this->prefix('api.tcp'))
			->setImplement(API\TcpFactory::class)
			->getResultDefinition()
			->setType(API\Tcp::class);

		$builder->addDefinition($this->prefix('api.transformer'), new DI\Definitions\ServiceDefinition())
			->setType(API\Transformer::class);

		/**
		 * MESSAGES QUEUE
		 */

		$builder->addDefinition(
			$this->prefix('queue.consumers.store.deviceConnectionState'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers\StoreDeviceConnectionState::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.consumers.store.channelPropertyState'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers\StoreChannelPropertyState::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.consumers.write.channelPropertyState'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers\WriteChannelPropertyState::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.consumers'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers::class)
			->setArguments([
				'consumers' => $builder->findByType(Queue\Consumer::class),
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.queue'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Queue::class)
			->setArguments([
				'logger' => $logger,
			]);

		/**
		 * SUBSCRIBERS
		 */

		$builder->addDefinition($this->prefix('subscribers.properties'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Properties::class);

		$builder->addDefinition($this->prefix('subscribers.controls'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Controls::class);

		/**
		 * JSON-API SCHEMAS
		 */

		$builder->addDefinition($this->prefix('schemas.connector'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\Connectors\Connector::class);

		$builder->addDefinition($this->prefix('schemas.device'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\Devices\Device::class);

		$builder->addDefinition($this->prefix('schemas.channel'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\Channels\Channel::class);

		/**
		 * JSON-API HYDRATORS
		 */

		$builder->addDefinition($this->prefix('hydrators.connector'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Connectors\Connector::class);

		$builder->addDefinition($this->prefix('hydrators.device'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Devices\Device::class);

		$builder->addDefinition($this->prefix('hydrators.channel'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Channels\Channel::class);

		/**
		 * HELPERS
		 */

		$builder->addDefinition($this->prefix('helpers.connector'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Connector::class);

		$builder->addDefinition($this->prefix('helpers.device'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Device::class);

		$builder->addDefinition($this->prefix('helpers.channel'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Channel::class);

		$builder->addDefinition($this->prefix('helpers.messageBuilder'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\MessageBuilder::class);

		/**
		 * COMMANDS
		 */

		$builder->addDefinition($this->prefix('commands.execute'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Execute::class);

		$builder->addDefinition($this->prefix('commands.install'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Install::class)
			->setArguments([
				'logger' => $logger,
			]);

		/**
		 * CONNECTOR
		 */

		$builder->addFactoryDefinition($this->prefix('executor.factory'))
			->setImplement(Connector\ConnectorFactory::class)
			->addTag(
				DevicesDI\DevicesExtension::CONNECTOR_TYPE_TAG,
				Entities\Connectors\Connector::TYPE,
			)
			->getResultDefinition()
			->setType(Connector\Connector::class)
			->setArguments([
				'clientsFactories' => $builder->findByType(Clients\ClientFactory::class),
				'writersFactories' => $builder->findByType(Writers\WriterFactory::class),
				'logger' => $logger,
			]);
	}

	/**
	 * @throws DI\MissingServiceException
	 */
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/**
		 * DOCTRINE ENTITIES
		 */

		$services = $builder->findByTag(NettrineORM\DI\OrmAttributesExtension::DRIVER_TAG);

		if ($services !== []) {
			$services = array_keys($services);
			$ormAttributeDriverServiceName = array_pop($services);

			$ormAttributeDriverService = $builder->getDefinition($ormAttributeDriverServiceName);

			if ($ormAttributeDriverService instanceof DI\Definitions\ServiceDefinition) {
				$ormAttributeDriverService->addSetup(
					'addPaths',
					[[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Entities']],
				);

				$ormAttributeDriverChainService = $builder->getDefinitionByType(
					Persistence\Mapping\Driver\MappingDriverChain::class,
				);

				if ($ormAttributeDriverChainService instanceof DI\Definitions\ServiceDefinition) {
					$ormAttributeDriverChainService->addSetup('addDriver', [
						$ormAttributeDriverService,
						'FastyBird\Connector\Modbus\Entities',
					]);
				}
			}
		}

		/**
		 * APPLICATION DOCUMENTS
		 */

		$services = $builder->findByTag(Metadata\DI\MetadataExtension::DRIVER_TAG);

		if ($services !== []) {
			$services = array_keys($services);
			$documentAttributeDriverServiceName = array_pop($services);

			$documentAttributeDriverService = $builder->getDefinition($documentAttributeDriverServiceName);

			if ($documentAttributeDriverService instanceof DI\Definitions\ServiceDefinition) {
				$documentAttributeDriverService->addSetup(
					'addPaths',
					[[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Documents']],
				);

				$documentAttributeDriverChainService = $builder->getDefinitionByType(
					MetadataDocuments\Mapping\Driver\MappingDriverChain::class,
				);

				if ($documentAttributeDriverChainService instanceof DI\Definitions\ServiceDefinition) {
					$documentAttributeDriverChainService->addSetup('addDriver', [
						$documentAttributeDriverService,
						'FastyBird\Connector\Modbus\Documents',
					]);
				}
			}
		}

		/**
		 * DOCTRINE FIXTURES
		 */

		$fixturesLoaderService = $builder->getDefinitionByType(NettrineFixtures\Loader\FixturesLoader::class);

		if ($fixturesLoaderService instanceof DI\Definitions\ServiceDefinition) {
			//$fixturesLoaderService->addSetup('addFixture', [new Fixtures\Connector()]);
			//$fixturesLoaderService->addSetup('addFixture', [new Fixtures\ConnectorProperties()]);
			//$fixturesLoaderService->addSetup('addFixture', [new Fixtures\Devices()]);
			//$fixturesLoaderService->addSetup('addFixture', [new Fixtures\DevicesProperties()]);
			//$fixturesLoaderService->addSetup('addFixture', [new Fixtures\Channels()]);
			//$fixturesLoaderService->addSetup('addFixture', [new Fixtures\ChannelsProperties()]);
		}
	}

	/**
	 * @return array<string>
	 */
	public function getTranslationResources(): array
	{
		return [
			__DIR__ . '/../Translations/',
		];
	}

}
