<?php declare(strict_types = 1);

namespace Tests\Cases\Unit;

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

		Assert::notNull($container->getByType(Schemas\ModbusDevice::class));
		Assert::notNull($container->getByType(Schemas\ModbusConnector::class));

		Assert::notNull($container->getByType(Hydrators\ModbusDevice::class));
		Assert::notNull($container->getByType(Hydrators\ModbusConnector::class));
	}

}

$test_case = new ServicesTest();
$test_case->run();
