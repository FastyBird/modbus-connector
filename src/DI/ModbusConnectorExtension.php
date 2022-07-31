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
use FastyBird\ModbusConnector;
use FastyBird\ModbusConnector\Clients;
use FastyBird\ModbusConnector\Connector;
use FastyBird\ModbusConnector\Consumers;
use FastyBird\ModbusConnector\Helpers;
use FastyBird\ModbusConnector\Hydrators;
use FastyBird\ModbusConnector\Schemas;
use Nette;
use Nette\DI;
use Nette\Schema;
use React\EventLoop;
use stdClass;

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

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'fbModbusConnector'
	): void {
		$config->onCompile[] = function (
			Nette\Configurator $config,
			DI\Compiler $compiler
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new ModbusConnectorExtension());
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'loop' => Schema\Expect::anyOf(Schema\Expect::string(), Schema\Expect::type(DI\Definitions\Statement::class))
				->nullable(),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var stdClass $configuration */
		$configuration = $this->getConfig();

		if ($configuration->loop === null && $builder->getByType(EventLoop\LoopInterface::class) === null) {
			$builder->addDefinition($this->prefix('client.loop'), new DI\Definitions\ServiceDefinition())
				->setType(EventLoop\LoopInterface::class)
				->setFactory('React\EventLoop\Factory::create');
		}

		// Service factory
		$builder->addDefinition($this->prefix('service.factory'), new DI\Definitions\ServiceDefinition())
			->setType(ModbusConnector\ConnectorFactory::class);

		// Connector
		$builder->addFactoryDefinition($this->prefix('connector'))
			->setImplement(Connector\ConnectorFactory::class)
			->getResultDefinition()
			->setType(Connector\Connector::class);

		// Clients
		$builder->addFactoryDefinition($this->prefix('client.rtu'))
			->setImplement(Clients\RtuClientFactory::class)
			->getResultDefinition()
			->setType(Clients\RtuClient::class);

		// Consumers
		$builder->addDefinition($this->prefix('consumer.proxy'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\Consumer::class);

		// API schemas
		$builder->addDefinition($this->prefix('schemas.connector.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusConnectorSchema::class);

		$builder->addDefinition($this->prefix('schemas.device.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ModbusDeviceSchema::class);

		// API hydrators
		$builder->addDefinition($this->prefix('hydrators.connector.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusConnectorHydrator::class);

		$builder->addDefinition($this->prefix('hydrators.device.modbus'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ModbusDeviceHydrator::class);

		// Helpers
		$builder->addDefinition($this->prefix('helpers.database'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\DatabaseHelper::class);

	}

	/**
	 * {@inheritDoc}
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
			$ormAnnotationDriverService->addSetup('addPaths', [[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Entities']]);
		}

		$ormAnnotationDriverChainService = $builder->getDefinitionByType(Persistence\Mapping\Driver\MappingDriverChain::class);

		if ($ormAnnotationDriverChainService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverChainService->addSetup('addDriver', [
				$ormAnnotationDriverService,
				'FastyBird\ModbusConnector\Entities',
			]);
		}
	}

}
