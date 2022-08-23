<?php declare(strict_types = 1);

/**
 * ConnectorPropertiesFixture.php
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
 * Connector properties database fixture
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Fixtures
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorPropertiesFixture extends DataFixtures\AbstractFixture implements DataFixtures\DependentFixtureInterface
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

		$clientModeProperty = new DevicesModuleEntities\Connectors\Properties\StaticProperty(
			$connector,
			Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE
		);
		$clientModeProperty->setDataType(MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING));
		$clientModeProperty->setValue(Types\ClientModeType::MODE_RTU);

		$interfaceProperty = new DevicesModuleEntities\Connectors\Properties\StaticProperty(
			$connector,
			Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_INTERFACE
		);
		$interfaceProperty->setDataType(MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING));
		$interfaceProperty->setValue('/dev/ttyUSB0');

		$manager->persist($clientModeProperty);
		$manager->persist($interfaceProperty);
		$manager->flush();
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
