<?php declare(strict_types = 1);

/**
 * Connector\ModbusExtension.php
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

use Doctrine\Persistence;
use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Clients;
use FastyBird\Connector\Modbus\Commands;
use FastyBird\Connector\Modbus\Connector;
use FastyBird\Connector\Modbus\Consumers;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Fixtures;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Hydrators;
use FastyBird\Connector\Modbus\Schemas;
use FastyBird\Connector\Modbus\Subscribers;
use FastyBird\Connector\Modbus\Writers;
use FastyBird\Library\Bootstrap\Boot as BootstrapBoot;
use FastyBird\Module\Devices\DI as DevicesDI;
use Nette\DI;
use Nette\Schema;
use Nettrine\Fixtures as NettrineFixtures;
use stdClass;
use function assert;
use const DIRECTORY_SEPARATOR;

/**
 * Modbus connector
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ModbusExtension extends DI\CompilerExtension
{

	public const NAME = 'fbModbusConnector';

	public static function register(
		BootstrapBoot\Configurator $config,
		string $extensionName = self::NAME,
	): void
	{
		// @phpstan-ignore-next-line
		$config->onCompile[] = static function (
			BootstrapBoot\Configurator $config,
			DI\Compiler $compiler,
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new ModbusExtension());
		};
	}

	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'writer' => Schema\Expect::anyOf(
				Writers\Event::NAME,
				Writers\Exchange::NAME,
				Writers\Periodic::NAME,
			)->default(
				Writers\Periodic::NAME,
			),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$configuration = $this->getConfig();
		assert($configuration instanceof stdClass);

		if ($configuration->writer === Writers\Event::NAME) {
			$builder->addDefinition($this->prefix('writers.event'), new DI\Definitions\ServiceDefinition())
				->setType(Writers\Event::class);
		} elseif ($configuration->writer === Writers\Exchange::NAME) {
			$builder->addDefinition($this->prefix('writers.exchange'), new DI\Definitions\ServiceDefinition())
				->setType(Writers\Exchange::class);
		} elseif ($configuration->writer === Writers\Periodic::NAME) {
			$builder->addDefinition($this->prefix('writers.periodic'), new DI\Definitions\ServiceDefinition())
				->setType(Writers\Periodic::class);
		}

		$builder->addFactoryDefinition($this->prefix('client.rtu'))
			->setImplement(Clients\RtuFactory::class)
			->getResultDefinition()
			->setType(Clients\Rtu::class);

		$builder->addFactoryDefinition($this->prefix('client.tcp'))
			->setImplement(Clients\TcpFactory::class)
			->getResultDefinition()
			->setType(Clients\Tcp::class);

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

		$builder->addDefinition(
			$this->prefix('consumers.messages.device.state'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Consumers\Messages\State::class);

		$builder->addDefinition($this->prefix('consumers.messages'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\Messages::class)
			->setArguments([
				'consumers' => $builder->findByType(Consumers\Consumer::class),
			]);

		$builder->addDefinition($this->prefix('subscribers.properties'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Properties::class);

		$builder->addDefinition($this->prefix('schemas.connector.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusConnector::class);

		$builder->addDefinition($this->prefix('schemas.device.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusDevice::class);

		$builder->addDefinition($this->prefix('schemas.channel.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusChannel::class);

		$builder->addDefinition($this->prefix('hydrators.connector.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusConnector::class);

		$builder->addDefinition($this->prefix('hydrators.device.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusDevice::class);

		$builder->addDefinition($this->prefix('hydrators.channel.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusChannel::class);

		$builder->addDefinition($this->prefix('helpers.property'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Property::class);

		$builder->addFactoryDefinition($this->prefix('executor.factory'))
			->setImplement(Connector\ConnectorFactory::class)
			->addTag(
				DevicesDI\DevicesExtension::CONNECTOR_TYPE_TAG,
				Entities\ModbusConnector::CONNECTOR_TYPE,
			)
			->getResultDefinition()
			->setType(Connector\Connector::class)
			->setArguments([
				'clientsFactories' => $builder->findByType(Clients\ClientFactory::class),
			]);

		$builder->addDefinition($this->prefix('commands.initialize'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Initialize::class);

		$builder->addDefinition($this->prefix('commands.execute'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Execute::class);

		$builder->addDefinition($this->prefix('commands.devices'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Devices::class);
	}

	/**
	 * @throws DI\MissingServiceException
	 */
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/**
		 * Doctrine entities
		 */

		$ormAnnotationDriverService = $builder->getDefinition('nettrineOrmAnnotations.annotationDriver');

		if ($ormAnnotationDriverService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverService->addSetup(
				'addPaths',
				[[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Entities']],
			);
		}

		$ormAnnotationDriverChainService = $builder->getDefinitionByType(
			Persistence\Mapping\Driver\MappingDriverChain::class,
		);

		if ($ormAnnotationDriverChainService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverChainService->addSetup('addDriver', [
				$ormAnnotationDriverService,
				'FastyBird\Connector\Modbus\Entities',
			]);
		}

		/**
		 * Database fixtures
		 */

		$fixturesLoaderService = $builder->getDefinitionByType(NettrineFixtures\Loader\FixturesLoader::class);

		if ($fixturesLoaderService instanceof DI\Definitions\ServiceDefinition) {
			$fixturesLoaderService->addSetup('addFixture', [new Fixtures\Connector()]);
			$fixturesLoaderService->addSetup('addFixture', [new Fixtures\ConnectorProperties()]);
			$fixturesLoaderService->addSetup('addFixture', [new Fixtures\Devices()]);
			$fixturesLoaderService->addSetup('addFixture', [new Fixtures\DevicesProperties()]);
			$fixturesLoaderService->addSetup('addFixture', [new Fixtures\Channels()]);
			$fixturesLoaderService->addSetup('addFixture', [new Fixtures\ChannelsProperties()]);
		}
	}

}
