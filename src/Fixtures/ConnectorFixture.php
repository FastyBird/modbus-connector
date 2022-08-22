<?php declare(strict_types = 1);

/**
 * ConnectorFixture.php
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
final class ConnectorFixture extends DataFixtures\AbstractFixture implements DataFixtures\FixtureInterface
{

	/**
	 * @param Persistence\ObjectManager $manager
	 *
	 * @return void
	 */
	public function load(Persistence\ObjectManager $manager): void
	{
		$connector = new Entities\ModbusConnectorEntity('modbus-rtu');

        $manager->persist($connector);
        $manager->flush();

		$this->addReference('modbus-rtu-connector', $connector);
	}

}
