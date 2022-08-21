<?php declare(strict_types = 1);

/**
 * DevicesFixture.php
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
use FastyBird\ModbusConnector\Exceptions;
use Throwable;

/**
 * Connector devices database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DevicesFixture extends DataFixtures\AbstractFixture implements DataFixtures\DependentFixtureInterface
{

	/**
	 * @param Persistence\ObjectManager $manager
	 *
	 * @return void
	 *
	 * @throws Throwable
	 */
	public function load(Persistence\ObjectManager $manager): void
	{
		$connector = $this->getReference('modbus-rtu-connector');

		if (!$connector instanceof Entities\IModbusConnectorEntity) {
			throw new Exceptions\InvalidStateException('Connector reference could not be loaded');
		}

		$device = new Entities\ModbusDeviceEntity(
			'fixture-device',
			$connector,
			'Fixture device'
		);

		$manager->persist($device);
		$manager->flush();

		$this->addReference('modbus-rtu-device', $device);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDependencies(): array
	{
		return [
			ConnectorFixture::class,
		];
	}

}
