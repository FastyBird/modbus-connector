<?php declare(strict_types = 1);

/**
 * EntitiesSubscriber.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Subscribers
 * @since          0.34.0
 *
 * @date           04.08.22
 */

namespace FastyBird\ModbusConnector\Subscribers;

use Doctrine\Common;
use Doctrine\ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Exceptions\InvalidArgumentException;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ModbusConnector\Entities;
use FastyBird\ModbusConnector\Types;
use Nette;
use Nette\Utils;

/**
 * Doctrine entities events
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Subscribers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class EntitiesSubscriber implements Common\EventSubscriber
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesManager */
	private DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager;

	/**
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	 */
	public function __construct(
		DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	) {
		$this->propertiesManager = $propertiesManager;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSubscribedEvents(): array
	{
		return [
			ORM\Events::postPersist,
		];
	}

	/**
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 *
	 * @return void
	 */
	public function postPersist(ORM\Event\LifecycleEventArgs $eventArgs): void
	{
		// onFlush was executed before, everything already initialized
		$entity = $eventArgs->getObject();

		// Check for valid entity
		if ($entity instanceof Entities\IModbusDeviceEntity) {
			$stateProperty = $entity->getProperty(Types\DevicePropertyIdentifierType::IDENTIFIER_STATE);

			if ($stateProperty !== null) {
				$entity->removeProperty($stateProperty);
			}

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'device'     => $entity,
				'entity'     => DevicesModuleEntities\Devices\Properties\DynamicProperty::class,
				'identifier' => Types\DevicePropertyIdentifierType::IDENTIFIER_STATE,
				'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_ENUM),
				'unit'       => null,
				'format'     => [
					MetadataTypes\ConnectionStateType::STATE_CONNECTED,
					MetadataTypes\ConnectionStateType::STATE_DISCONNECTED,
					MetadataTypes\ConnectionStateType::STATE_STOPPED,
					MetadataTypes\ConnectionStateType::STATE_LOST,
					MetadataTypes\ConnectionStateType::STATE_UNKNOWN,
				],
				'settable'   => false,
				'queryable'  => false,
			]));

		} elseif (
			$entity instanceof DevicesModuleEntities\Connectors\Properties\StaticProperty
			&& $entity->getConnector() instanceof Entities\IModbusConnectorEntity
		) {
			if (
				(
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE
					&& !Types\ClientModeType::isValidValue($entity->getValue())
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BYTE_SIZE
					&& !Types\ByteSizeType::isValidValue($entity->getValue())
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BAUD_RATE
					&& !Types\BaudRateType::isValidValue($entity->getValue())
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_PARITY
					&& !Types\ParityType::isValidValue($entity->getValue())
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_STOP_BITS
					&& !Types\StopBitsType::isValidValue($entity->getValue())
				)
			) {
				throw new InvalidArgumentException(sprintf(
					'Provided value for connector property: %s is not in valid range',
					$entity->getIdentifier()
				));
			}
		} elseif (
			$entity instanceof DevicesModuleEntities\Devices\Properties\StaticProperty
			&& $entity->getDevice() instanceof Entities\IModbusDeviceEntity
		) {
			if (
				(
					$entity->getIdentifier() === Types\DevicePropertyIdentifierType::IDENTIFIER_BYTE_ORDER
					&& !Types\ByteOrderType::isValidValue($entity->getValue())
				)
			) {
				throw new InvalidArgumentException(sprintf(
					'Provided value for device property: %s is not in valid range',
					$entity->getIdentifier()
				));
			}
		}
	}

}
