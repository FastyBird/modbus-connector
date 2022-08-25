<?php declare(strict_types = 1);

namespace Tests\Cases;

use FastyBird\ModbusConnector\Hydrators;
use FastyBird\ModbusConnector\Schemas;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../BaseTestCase.php';

/**
 * @testCase
 */
final class ServicesTest extends BaseTestCase
{

	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		Assert::notNull($container->getByType(Schemas\ModbusDeviceSchema::class));
		Assert::notNull($container->getByType(Schemas\ModbusConnectorSchema::class));

		Assert::notNull($container->getByType(Hydrators\ModbusDeviceHydrator::class));
		Assert::notNull($container->getByType(Hydrators\ModbusConnectorHydrator::class));
	}

}

$test_case = new ServicesTest();
$test_case->run();
