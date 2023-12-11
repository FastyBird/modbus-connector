<?php declare(strict_types = 1);

namespace FastyBird\Connector\Modbus\Tests\Cases\Unit\DI;

use Error;
use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Clients;
use FastyBird\Connector\Modbus\Commands;
use FastyBird\Connector\Modbus\Connector;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Hydrators;
use FastyBird\Connector\Modbus\Queue;
use FastyBird\Connector\Modbus\Schemas;
use FastyBird\Connector\Modbus\Subscribers;
use FastyBird\Connector\Modbus\Tests;
use FastyBird\Connector\Modbus\Writers;
use FastyBird\Library\Bootstrap\Exceptions as BootstrapExceptions;
use Nette;

final class ModbusExtensionTest extends Tests\Cases\Unit\BaseTestCase
{

	/**
	 * @throws BootstrapExceptions\InvalidArgument
	 * @throws Nette\DI\MissingServiceException
	 * @throws Error
	 */
	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		self::assertNotNull($container->getByType(Writers\WriterFactory::class, false));

		self::assertNotNull($container->getByType(Clients\RtuFactory::class, false));
		self::assertNotNull($container->getByType(Clients\TcpFactory::class, false));

		self::assertNotNull($container->getByType(API\RtuFactory::class, false));
		self::assertNotNull($container->getByType(API\TcpFactory::class, false));
		self::assertNotNull($container->getByType(API\Transformer::class, false));

		self::assertNotNull($container->getByType(Queue\Consumers\StoreDeviceConnectionState::class, false));
		self::assertNotNull($container->getByType(Queue\Consumers\StoreChannelPropertyState::class, false));
		self::assertNotNull($container->getByType(Queue\Consumers\WriteChannelPropertyState::class, false));
		self::assertNotNull($container->getByType(Queue\Consumers::class, false));
		self::assertNotNull($container->getByType(Queue\Queue::class, false));

		self::assertNotNull($container->getByType(Subscribers\Properties::class, false));
		self::assertNotNull($container->getByType(Subscribers\Controls::class, false));

		self::assertNotNull($container->getByType(Schemas\ModbusConnector::class, false));
		self::assertNotNull($container->getByType(Schemas\ModbusDevice::class, false));
		self::assertNotNull($container->getByType(Schemas\ModbusChannel::class, false));

		self::assertNotNull($container->getByType(Hydrators\ModbusConnector::class, false));
		self::assertNotNull($container->getByType(Hydrators\ModbusDevice::class, false));
		self::assertNotNull($container->getByType(Hydrators\ModbusChannel::class, false));

		self::assertNotNull($container->getByType(Helpers\Entity::class, false));
		self::assertNotNull($container->getByType(Helpers\Connector::class, false));
		self::assertNotNull($container->getByType(Helpers\Device::class, false));
		self::assertNotNull($container->getByType(Helpers\Channel::class, false));

		self::assertNotNull($container->getByType(Commands\Execute::class, false));
		self::assertNotNull($container->getByType(Commands\Install::class, false));

		self::assertNotNull($container->getByType(Connector\ConnectorFactory::class, false));
	}

}
