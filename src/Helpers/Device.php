<?php declare(strict_types = 1);

/**
 * Device.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 * @since          0.34.0
 *
 * @date           02.08.22
 */

namespace FastyBird\Connector\Modbus\Helpers;

use FastyBird\Connector\Modbus\Types;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use Nette;
use Ramsey\Uuid;
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

	public function __construct(
		private readonly DevicesModuleModels\DataStorage\DevicePropertiesRepository $propertiesRepository,
	)
	{
	}

	/**
	 * @throws DevicesModuleExceptions\InvalidState
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function getConfiguration(
		Uuid\UuidInterface $deviceId,
		Types\DevicePropertyIdentifier $type,
	): float|bool|int|string|null
	{
		$configuration = $this->propertiesRepository->findByIdentifier($deviceId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\DevicesModule\DeviceVariableProperty) {
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
