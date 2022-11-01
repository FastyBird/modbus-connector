<?php declare(strict_types = 1);

/**
 * Device.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 * @since          0.34.0
 *
 * @date           02.08.22
 */

namespace FastyBird\Connector\Modbus\Helpers;

use DateTimeInterface;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use Nette;
use function is_int;
use function strval;

/**
 * Useful device helpers
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Device
{

	use Nette\SmartObject;

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function getConfiguration(
		Entities\ModbusDevice $device,
		Types\DevicePropertyIdentifier $type,
	): float|bool|int|string|MetadataTypes\ButtonPayload|MetadataTypes\SwitchPayload|DateTimeInterface|null
	{
		$configuration = $device->findProperty(strval($type->getValue()));

		if ($configuration instanceof DevicesEntities\Devices\Properties\Variable) {
			if ($type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_ADDRESS) {
				return is_int($configuration->getValue()) ? $configuration->getValue() : null;
			}

			if (
				$type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_BYTE_ORDER
				&& !Types\ByteOrder::isValidValue($configuration->getValue())
			) {
				return Types\ByteOrder::BYTE_ORDER_BIG;
			}

			return $configuration->getValue();
		}

		if ($type->getValue() === Types\DevicePropertyIdentifier::IDENTIFIER_BYTE_ORDER) {
			return Types\ByteOrder::BYTE_ORDER_BIG;
		}

		return null;
	}

}
