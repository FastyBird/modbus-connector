<?php declare(strict_types = 1);

/**
 * Connector\ModbusExtension.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           30.01.22
 */

namespace FastyBird\Connector\Modbus\DI;

use Doctrine\Persistence;
use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Clients;
use FastyBird\Connector\Modbus\Commands;
use FastyBird\Connector\Modbus\Connector;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Fixtures;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Hydrators;
use FastyBird\Connector\Modbus\Schemas;
use FastyBird\Connector\Modbus\Subscribers;
use FastyBird\Module\Devices\DI as DevicesDI;
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
class ModbusExtension extends DI\CompilerExtension
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
			$compiler->addExtension($extensionName, new ModbusExtension());
		};
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addFactoryDefinition($this->prefix('client.rtu'))
			->setImplement(Clients\RtuFactory::class)
			->getResultDefinition()
			->setType(Clients\Rtu::class);

		$builder->addDefinition($this->prefix('api.transformer'), new DI\Definitions\ServiceDefinition())
			->setType(API\Transformer::class);

		$builder->addDefinition($this->prefix('subscribers.properties'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Properties::class);

		$builder->addDefinition($this->prefix('schemas.connector.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusConnector::class);

		$builder->addDefinition($this->prefix('schemas.device.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusDevice::class);

		$builder->addDefinition($this->prefix('hydrators.connector.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusConnector::class);

		$builder->addDefinition($this->prefix('hydrators.device.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusDevice::class);

		$builder->addDefinition($this->prefix('helpers.connector'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Connector::class);

		$builder->addDefinition($this->prefix('helpers.device'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Device::class);

		$builder->addDefinition($this->prefix('helpers.channel'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Channel::class);

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
