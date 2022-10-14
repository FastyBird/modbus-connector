<?php declare(strict_types = 1);

/**
 * ModbusConnectorExtension.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           30.01.22
 */

namespace FastyBird\ModbusConnector\DI;

use Doctrine\Persistence;
use FastyBird\DevicesModule\DI as DevicesModuleDI;
use FastyBird\ModbusConnector\API;
use FastyBird\ModbusConnector\Clients;
use FastyBird\ModbusConnector\Commands;
use FastyBird\ModbusConnector\Connector;
use FastyBird\ModbusConnector\Entities;
use FastyBird\ModbusConnector\Fixtures;
use FastyBird\ModbusConnector\Helpers;
use FastyBird\ModbusConnector\Hydrators;
use FastyBird\ModbusConnector\Schemas;
use FastyBird\ModbusConnector\Subscribers;
use Nette;
use Nette\DI;
use Nettrine\Fixtures as NettrineFixtures;
use const DIRECTORY_SEPARATOR;

/**
 * Modbus connector
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ModbusConnectorExtension extends DI\CompilerExtension
{

	public const NAME = 'fbModbusConnector';

	public static function register(
		Nette\Configurator $config,
		string $extensionName = self::NAME,
	): void
	{
		$config->onCompile[] = static function (
			Nette\Configurator $config,
			DI\Compiler $compiler,
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new ModbusConnectorExtension());
		};
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		// Clients
		$builder->addFactoryDefinition($this->prefix('client.rtu'))
			->setImplement(Clients\RtuFactory::class)
			->getResultDefinition()
			->setType(Clients\Rtu::class);

		// Messages API
		$builder->addDefinition($this->prefix('api.transformer'), new DI\Definitions\ServiceDefinition())
			->setType(API\Transformer::class);

		// Events subscribers
		$builder->addDefinition($this->prefix('subscribers.properties'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Properties::class);

		// API schemas
		$builder->addDefinition($this->prefix('schemas.connector.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusConnector::class);

		$builder->addDefinition($this->prefix('schemas.device.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusDevice::class);

		// API hydrators
		$builder->addDefinition($this->prefix('hydrators.connector.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusConnector::class);

		$builder->addDefinition($this->prefix('hydrators.device.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusDevice::class);

		// Helpers
		$builder->addDefinition($this->prefix('helpers.database'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Database::class);

		$builder->addDefinition($this->prefix('helpers.connector'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Connector::class);

		$builder->addDefinition($this->prefix('helpers.device'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Device::class);

		$builder->addDefinition($this->prefix('helpers.channel'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Channel::class);

		$builder->addDefinition($this->prefix('helpers.property'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Property::class);

		// Service factory
		$builder->addFactoryDefinition($this->prefix('executor.factory'))
			->setImplement(Connector\ConnectorFactory::class)
			->addTag(
				DevicesModuleDI\DevicesModuleExtension::CONNECTOR_TYPE_TAG,
				Entities\ModbusConnector::CONNECTOR_TYPE,
			)
			->getResultDefinition()
			->setType(Connector\Connector::class)
			->setArguments([
				'clientsFactories' => $builder->findByType(Clients\ClientFactory::class),
			]);

		// Console commands
		$builder->addDefinition($this->prefix('commands.initialize'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Initialize::class);

		$builder->addDefinition($this->prefix('commands.execute'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Execute::class);
	}

	/**
	 * @throws Nette\DI\MissingServiceException
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
				'FastyBird\ModbusConnector\Entities',
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
