<?php declare(strict_types = 1);

/**
 * ChannelsProperties.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 * @since          1.0.0
 *
 * @date           22.08.22
 */

namespace FastyBird\Connector\Modbus\Fixtures;

use Doctrine\Common\DataFixtures;
use Doctrine\Persistence;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Types\ChannelPropertyIdentifier;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use TypeError;
use ValueError;
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
	 * @throws Exceptions\InvalidState
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function load(Persistence\ObjectManager $manager): void
	{
		for ($i = 1; $i <= 4; $i++) {
			$channel = $this->getReference('modbus-rtu-channel-' . $i);

			if (!$channel instanceof Entities\Channels\Channel) {
				throw new Exceptions\InvalidState('Channel reference could not be loaded');
			}

			$addressProperty = new DevicesEntities\Channels\Properties\Variable(
				$channel,
				ChannelPropertyIdentifier::ADDRESS->value,
			);
			$addressProperty->setDataType(MetadataTypes\DataType::UINT);
			$addressProperty->setValue(strval($i));

			$switchProperty = new DevicesEntities\Channels\Properties\Dynamic(
				$channel,
				'switch',
			);
			$switchProperty->setDataType(MetadataTypes\DataType::SWITCH);
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
