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
use FastyBird\ModbusConnector\Hydrators;
use FastyBird\ModbusConnector\Schemas;
use Nette;
use Nette\DI;

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
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

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
