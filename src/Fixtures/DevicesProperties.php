<?php declare(strict_types = 1);

/**
 * DevicesProperties.php
 *
 * @license        More in LICENSE.md
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
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;

/**
 * Devices properties database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DevicesProperties extends DataFixtures\AbstractFixture implements DataFixtures\DependentFixtureInterface
{

	/**
	 * @throws Exceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 */
	public function load(Persistence\ObjectManager $manager): void
	{
		$device = $this->getReference('modbus-rtu-device');

		if (!$device instanceof Entities\ModbusDevice) {
			throw new Exceptions\InvalidState('Device reference could not be loaded');
		}

		$addressProperty = new DevicesEntities\Devices\Properties\Variable(
			$device,
			Types\DevicePropertyIdentifier::IDENTIFIER_ADDRESS,
		);
		$addressProperty->setDataType(MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UINT));
		$addressProperty->setValue('1');

		$manager->persist($addressProperty);
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
