<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 * @since          0.34.0
 *
 * @date           22.08.22
 */

namespace FastyBird\ModbusConnector\Fixtures;

use BadMethodCallException;
use Doctrine\Common\DataFixtures;
use Doctrine\Persistence;
use FastyBird\ModbusConnector\Entities;

/**
 * Connector database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector extends DataFixtures\AbstractFixture implements DataFixtures\FixtureInterface
{

	/**
	 * @throws BadMethodCallException
	 */
	public function load(Persistence\ObjectManager $manager): void
	{
		$connector = new Entities\ModbusConnector('modbus-rtu');
		$connector->setName('Modbus RTU');

		$manager->persist($connector);
		$manager->flush();

		$this->addReference('modbus-rtu-connector', $connector);
	}

}
