<?php declare(strict_types = 1);

/**
 * Properties.php
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
final class Properties implements Common\EventSubscriber
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
		if ($entity instanceof Entities\ModbusDevice) {
			$stateProperty = $entity->getProperty(Types\DevicePropertyIdentifier::IDENTIFIER_STATE);

			if ($stateProperty !== null) {
				$entity->removeProperty($stateProperty);
			}

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'device'     => $entity,
				'entity'     => DevicesModuleEntities\Devices\Properties\DynamicProperty::class,
				'identifier' => Types\DevicePropertyIdentifier::IDENTIFIER_STATE,
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
			&& $entity->getConnector() instanceof Entities\ModbusConnector
		) {
			if (
				(
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE
					&& !Types\ClientMode::isValidValue($entity->getValue())
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BYTE_SIZE
					&& !Types\ByteSize::isValidValue($entity->getValue())
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_BAUD_RATE
					&& !Types\BaudRate::isValidValue($entity->getValue())
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_PARITY
					&& !Types\Parity::isValidValue($entity->getValue())
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_RTU_STOP_BITS
					&& !Types\StopBits::isValidValue($entity->getValue())
				)
			) {
				throw new InvalidArgumentException(sprintf(
					'Provided value for connector property: %s is not in valid range',
					$entity->getIdentifier()
				));
			}
		} elseif (
			$entity instanceof DevicesModuleEntities\Devices\Properties\StaticProperty
			&& $entity->getDevice() instanceof Entities\ModbusDevice
		) {
			if (
				(
					$entity->getIdentifier() === Types\DevicePropertyIdentifier::IDENTIFIER_BYTE_ORDER
					&& !Types\ByteOrder::isValidValue($entity->getValue())
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
