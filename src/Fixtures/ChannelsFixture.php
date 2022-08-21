<?php declare(strict_types = 1);

/**
 * ChannelsFixture.php
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
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\ModbusConnector\Entities;
use FastyBird\ModbusConnector\Exceptions;
use Throwable;

/**
 * Devices channels database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ChannelsFixture extends DataFixtures\AbstractFixture implements DataFixtures\DependentFixtureInterface
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
		$device = $this->getReference('modbus-rtu-device');

		if (!$device instanceof Entities\IModbusDeviceEntity) {
			throw new Exceptions\InvalidStateException('Device reference could not be loaded');
		}

		$channel = new DevicesModuleEntities\Channels\Channel(
			$device,
			'channel-1'
		);

		$manager->persist($channel);
		$manager->flush();

		$this->setReference('modbus-rtu-channel-1', $channel);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDependencies(): array
	{
		return [
			DevicesFixture::class,
		];
	}

}
