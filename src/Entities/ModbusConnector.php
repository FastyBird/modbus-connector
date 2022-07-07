<?php declare(strict_types = 1);

/**
 * ModbusConnector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           07.12.21
 */

namespace FastyBird\ModbusConnector\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * @ORM\Entity
 */
class ModbusConnector extends DevicesModuleEntities\Connectors\Connector implements IModbusConnector
{

	public const CONNECTOR_TYPE = 'modbus';

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getInterface(): string
	{
		$property = $this->findProperty(MetadataTypes\ConnectorPropertyNameType::NAME_INTERFACE);

		if (
			$property === null
			|| !$property instanceof DevicesModuleEntities\Connectors\Properties\IStaticProperty
			|| !is_string($property->getValue())
		) {
			return '/dev/ttyAMA0';
		}

		return $property->getValue();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBaudRate(): int
	{
		$property = $this->findProperty(MetadataTypes\ConnectorPropertyNameType::NAME_BAUD_RATE);

		if (
			$property === null
			|| !$property instanceof DevicesModuleEntities\Connectors\Properties\IStaticProperty
			|| !is_int($property->getValue())
		) {
			return 9600;
		}

		return $property->getValue();
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'interface' => $this->getInterface(),
			'baud_rate' => $this->getBaudRate(),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDiscriminatorName(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): MetadataTypes\ModuleSourceType|MetadataTypes\PluginSourceType|MetadataTypes\ConnectorSourceType
	{
		return MetadataTypes\ConnectorSourceType::get(MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_MODBUS);
	}

}
