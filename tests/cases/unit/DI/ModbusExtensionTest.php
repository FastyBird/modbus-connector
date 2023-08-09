<?php declare(strict_types = 1);

namespace FastyBird\Connector\Modbus\Tests\Cases\Unit\DI;

use Error;
use FastyBird\Connector\Modbus\Hydrators;
use FastyBird\Connector\Modbus\Schemas;
use FastyBird\Connector\Modbus\Tests\Cases\Unit\BaseTestCase;
use FastyBird\Library\Bootstrap\Exceptions as BootstrapExceptions;
use Nette;

final class ModbusExtensionTest extends BaseTestCase
{

	/**
	 * @throws BootstrapExceptions\InvalidArgument
	 * @throws Nette\DI\MissingServiceException
	 * @throws Error
	 */
	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		self::assertNotNull($container->getByType(Schemas\ModbusConnector::class, false));
		self::assertNotNull($container->getByType(Schemas\ModbusDevice::class, false));
		self::assertNotNull($container->getByType(Schemas\ModbusChannel::class, false));

		self::assertNotNull($container->getByType(Hydrators\ModbusConnector::class, false));
		self::assertNotNull($container->getByType(Hydrators\ModbusDevice::class, false));
		self::assertNotNull($container->getByType(Hydrators\ModbusChannel::class, false));
	}

}
