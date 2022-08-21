<?php declare(strict_types = 1);

/**
 * DevicesPropertiesFixture.php
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
use FastyBird\ModbusConnector\Entities;
use FastyBird\ModbusConnector\Exceptions;
use FastyBird\ModbusConnector\Types;
use Throwable;

/**
 * Devices properties database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DevicesPropertiesFixture extends DataFixtures\AbstractFixture implements DataFixtures\DependentFixtureInterface
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

		$addressProperty = new DevicesModuleEntities\Devices\Properties\StaticProperty(
			$device,
			Types\DevicePropertyIdentifierType::IDENTIFIER_ADDRESS
		);
		$addressProperty->setDataType(MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UINT));
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
			DevicesFixture::class,
		];
	}

}
