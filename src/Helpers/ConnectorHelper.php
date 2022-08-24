<?php declare(strict_types = 1);

/**
 * ConnectorHelper.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 * @since          0.34.0
 *
 * @date           01.08.22
 */

namespace FastyBird\ModbusConnector\Helpers;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ModbusConnector;
use FastyBird\ModbusConnector\Types;
use Nette;
use Ramsey\Uuid;

/**
 * Useful connector helpers
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorHelper
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\DataStorage\IConnectorPropertiesRepository */
	private DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository;

	/**
	 * @param DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository
	) {
		$this->propertiesRepository = $propertiesRepository;
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param Types\ConnectorPropertyIdentifierType $type
	 *
	 * @return float|bool|int|string|null
	 */
	public function getConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifierType $type
	): float|bool|int|string|null {
		$configuration = $this->propertiesRepository->findByIdentifier($connectorId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\Modules\DevicesModule\IConnectorStaticPropertyEntity) {
			if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE) {
				return Types\ClientModeType::isValidValue($configuration->getValue()) ? $configuration->getValue() : null;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BYTE_SIZE
				&& !Types\ByteSizeType::isValidValue($configuration->getValue())
			) {
				return Types\ByteSizeType::SIZE_8;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BAUD_RATE
				&& !Types\BaudRateType::isValidValue($configuration->getValue())
			) {
				return Types\BaudRateType::BAUD_RATE_9600;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_PARITY
				&& !Types\ParityType::isValidValue($configuration->getValue())
			) {
				return Types\ParityType::PARITY_NONE;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_STOP_BITS
				&& !Types\StopBitsType::isValidValue($configuration->getValue())
			) {
				return Types\StopBitsType::STOP_BIT_ONE;
			}

			return $configuration->getValue();
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_INTERFACE) {
			return ModbusConnector\Constants::DEFAULT_RTU_SERIAL_INTERFACE;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BYTE_SIZE) {
			return Types\ByteSizeType::SIZE_8;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BAUD_RATE) {
			return Types\BaudRateType::BAUD_RATE_9600;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_PARITY) {
			return Types\ParityType::PARITY_NONE;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_STOP_BITS) {
			return Types\StopBitsType::STOP_BIT_ONE;
		}

		return null;
	}

}
