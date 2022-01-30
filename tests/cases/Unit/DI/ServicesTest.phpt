<?php declare(strict_types = 1);

namespace Tests\Cases;

use FastyBird\ModbusConnector\DI;
use FastyBird\ModbusConnector\Hydrators;
use FastyBird\ModbusConnector\Schemas;
use Nette;
use Ninjify\Nunjuck\TestCase\BaseTestCase;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

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

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer(): Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../../../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5((string) time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/../../../common.neon');

		DI\ModbusConnectorExtension::register($config);

		return $config->createContainer();
	}

}

$test_case = new ServicesTest();
$test_case->run();
