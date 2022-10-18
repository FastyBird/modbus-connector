<?php declare(strict_types = 1);

/**
 * ChannelsProperties.php
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
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Types\ChannelPropertyIdentifier;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use Throwable;
use function strval;

/**
 * Channels properties database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ChannelsProperties extends DataFixtures\AbstractFixture implements DataFixtures\DependentFixtureInterface
{

	/**
	 * @throws Throwable
	 */
	public function load(Persistence\ObjectManager $manager): void
	{
		for ($i = 1; $i <= 4; $i++) {
			$channel = $this->getReference('modbus-rtu-channel-' . $i);

			if (!$channel instanceof DevicesEntities\Channels\Channel) {
				throw new Exceptions\InvalidState('Channel reference could not be loaded');
			}

			$addressProperty = new DevicesEntities\Channels\Properties\Variable(
				$channel,
				ChannelPropertyIdentifier::IDENTIFIER_ADDRESS,
			);
			$addressProperty->setDataType(MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UINT));
			$addressProperty->setValue(strval($i));

			$switchProperty = new DevicesEntities\Channels\Properties\Dynamic(
				$channel,
				'switch',
			);
			$switchProperty->setDataType(MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_SWITCH));
			$switchProperty->setSettable(true);
			$switchProperty->setQueryable(true);
			$switchProperty->setFormat(
				'sw|switch_on:u8|1:u16|256,sw|switch_off:u8|0:u16|512,sw|switch_toggle::u16|768',
			);

			$manager->persist($addressProperty);
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
			Channels::class,
		];
	}

}
