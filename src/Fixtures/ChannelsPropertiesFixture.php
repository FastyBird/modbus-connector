<?php declare(strict_types = 1);

/**
 * ChannelsPropertiesFixture.php
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
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ModbusConnector\Exceptions;
use Throwable;

/**
 * Channels properties database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ChannelsPropertiesFixture extends DataFixtures\AbstractFixture implements DataFixtures\DependentFixtureInterface
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
		$channel = $this->getReference('modbus-rtu-channel-1');

		if (!$channel instanceof DevicesModuleEntities\Channels\IChannel) {
			throw new Exceptions\InvalidStateException('Channel reference could not be loaded');
		}

		for ($i = 1; $i <= 4; $i++) {
			$switchProperty = new DevicesModuleEntities\Channels\Properties\DynamicProperty(
				$channel,
				'switch-' . $i
			);
			$switchProperty->setDataType(MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH));
			$switchProperty->setSettable(true);
			$switchProperty->setQueryable(true);
			$switchProperty->setFormat('sw|switch-on:u8|1:u16|1000,sw|switch-off:u8|0:u16|2000,sw|switch-toggle::u16|3000');

			$manager->persist($switchProperty);
		}

		$manager->flush();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDependencies(): array
	{
		return [
			ChannelsFixture::class,
		];
	}

}
