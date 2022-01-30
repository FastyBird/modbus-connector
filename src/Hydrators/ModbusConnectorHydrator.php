<?php declare(strict_types = 1);

/**
 * ModbusConnectorHydrator.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           07.12.21
 */

namespace FastyBird\ModbusConnector\Hydrators;

use FastyBird\DevicesModule\Hydrators as DevicesModuleHydrators;
use FastyBird\ModbusConnector\Entities;
use IPub\JsonAPIDocument;

/**
 * Modbus connector entity hydrator
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleHydrators\Connectors\ConnectorHydrator<Entities\IModbusConnector>
 */
final class ModbusConnectorHydrator extends DevicesModuleHydrators\Connectors\ConnectorHydrator
{

	/** @var string[] */
	protected array $attributes = [
		0 => 'name',
		1 => 'enabled',

		'serial_interface' => 'serialInterface',
		'baud_rate'        => 'baudRate',
	];

	/**
	 * {@inheritDoc}
	 */
	public function getEntityName(): string
	{
		return Entities\ModbusConnector::class;
	}

	/**
	 * @param JsonAPIDocument\Objects\IStandardObject $attributes
	 *
	 * @return string|null
	 */
	protected function hydrateSerialInterfaceAttribute(JsonAPIDocument\Objects\IStandardObject $attributes): ?string
	{
		if (
			!is_scalar($attributes->get('serial_interface'))
			|| (string) $attributes->get('serial_interface') === ''
		) {
			return null;
		}

		return (string) $attributes->get('serial_interface');
	}

	/**
	 * @param JsonAPIDocument\Objects\IStandardObject $attributes
	 *
	 * @return int|null
	 */
	protected function hydrateBaudRateAttribute(JsonAPIDocument\Objects\IStandardObject $attributes): ?int
	{
		if (
			!is_scalar($attributes->get('baud_rate'))
			|| (string) $attributes->get('baud_rate') === ''
		) {
			return null;
		}

		return (int) $attributes->get('baud_rate');
	}

}
