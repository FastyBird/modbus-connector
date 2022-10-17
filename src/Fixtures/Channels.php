<?php declare(strict_types = 1);

/**
 * Channels.php
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

namespace FastyBird\Connector\Modbus\Fixtures;

use Doctrine\Common\DataFixtures;
use Doctrine\Persistence;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use Throwable;

/**
 * Devices channels database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Channels extends DataFixtures\AbstractFixture implements DataFixtures\DependentFixtureInterface
{

	/**
	 * @throws Throwable
	 */
	public function load(Persistence\ObjectManager $manager): void
	{
		$device = $this->getReference('modbus-rtu-device');

		if (!$device instanceof Entities\ModbusDevice) {
			throw new Exceptions\InvalidState('Device reference could not be loaded');
		}

		for ($i = 1; $i <= 4; $i++) {
			$channel = new DevicesModuleEntities\Channels\Channel(
				$device,
				'channel-' . $i,
			);

			$manager->persist($channel);

			$this->setReference('modbus-rtu-channel-' . $i, $channel);
		}

		$manager->flush();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDependencies(): array
	{
		return [
			Devices::class,
		];
	}

}
