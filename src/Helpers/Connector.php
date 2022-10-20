<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 * @since          0.34.0
 *
 * @date           01.08.22
 */

namespace FastyBird\Connector\Modbus\Helpers;

use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use Nette;
use Ramsey\Uuid;
use function strval;

/**
 * Useful connector helpers
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector
{

	use Nette\SmartObject;

	public function __construct(
		private readonly DevicesModels\DataStorage\ConnectorPropertiesRepository $propertiesRepository,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function getConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifier $type,
	): float|bool|int|string|null
	{
		$configuration = $this->propertiesRepository->findByIdentifier($connectorId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\DevicesModule\ConnectorVariableProperty) {
			if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE) {
				return Types\ClientMode::isValidValue($configuration->getValue()) ? $configuration->getValue() : null;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BYTE_SIZE
				&& !Types\ByteSize::isValidValue($configuration->getValue())
			) {
				return Types\ByteSize::SIZE_8;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BAUD_RATE
				&& !Types\BaudRate::isValidValue($configuration->getValue())
			) {
				return Types\BaudRate::BAUD_RATE_9600;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_PARITY
				&& !Types\Parity::isValidValue($configuration->getValue())
			) {
				return Types\Parity::PARITY_NONE;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_STOP_BITS
				&& !Types\StopBits::isValidValue($configuration->getValue())
			) {
				return Types\StopBits::STOP_BIT_ONE;
			}

			return $configuration->getValue();
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_INTERFACE) {
			return Modbus\Constants::DEFAULT_RTU_SERIAL_INTERFACE;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BYTE_SIZE) {
			return Types\ByteSize::SIZE_8;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BAUD_RATE) {
			return Types\BaudRate::BAUD_RATE_9600;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_PARITY) {
			return Types\Parity::PARITY_NONE;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_STOP_BITS) {
			return Types\StopBits::STOP_BIT_ONE;
		}

		return null;
	}

}
