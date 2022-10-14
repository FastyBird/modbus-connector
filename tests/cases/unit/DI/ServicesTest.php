<?php declare(strict_types = 1);

namespace Tests\Cases\Unit\DI;

use FastyBird\ModbusConnector\Hydrators;
use FastyBird\ModbusConnector\Schemas;
use Nette;
use Tests\Cases\Unit\BaseTestCase;

final class ServicesTest extends BaseTestCase
{

	/**
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		self::assertNotNull($container->getByType(Schemas\ModbusDevice::class, false));
		self::assertNotNull($container->getByType(Schemas\ModbusConnector::class, false));

		self::assertNotNull($container->getByType(Hydrators\ModbusDevice::class, false));
		self::assertNotNull($container->getByType(Hydrators\ModbusConnector::class, false));
	}

}
