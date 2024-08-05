<?php declare(strict_types = 1);

/**
 * Properties.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Subscribers
 * @since          1.0.0
 *
 * @date           04.08.22
 */

namespace FastyBird\Connector\Modbus\Subscribers;

use Doctrine\Common;
use Doctrine\DBAL;
use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Queries;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Application\Exceptions as ApplicationExceptions;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Types as DevicesTypes;
use IPub\DoctrineCrud\Exceptions as DoctrineCrudExceptions;
use Nette;
use Nette\Utils;
use TypeError;
use ValueError;
use function intval;
use function sprintf;

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

	public function __construct(
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesRepository $propertiesRepository,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesManager $propertiesManager,
	)
	{
	}

	public function getSubscribedEvents(): array
	{
		return [
			ORM\Events::postPersist,
		];
	}

	/**
	 * @param Persistence\Event\LifecycleEventArgs<ORM\EntityManagerInterface> $eventArgs
	 *
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DBAL\Exception\UniqueConstraintViolationException
	 * @throws DoctrineCrudExceptions\EntityCreation
	 * @throws DoctrineCrudExceptions\InvalidArgument
	 * @throws DoctrineCrudExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function postPersist(Persistence\Event\LifecycleEventArgs $eventArgs): void
	{
		// onFlush was executed before, everything already initialized
		$entity = $eventArgs->getObject();

		// Check for valid entity
		if ($entity instanceof Entities\Devices\Device) {
			$findDevicePropertyQuery = new Queries\Entities\FindDeviceProperties();
			$findDevicePropertyQuery->forDevice($entity);
			$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::STATE);

			$stateProperty = $this->propertiesRepository->findOneBy($findDevicePropertyQuery);

			if ($stateProperty !== null && !$stateProperty instanceof DevicesEntities\Devices\Properties\Dynamic) {
				$this->propertiesManager->delete($stateProperty);

				$stateProperty = null;
			}

			if ($stateProperty !== null) {
				$this->propertiesManager->update($stateProperty, Utils\ArrayHash::from([
					'dataType' => MetadataTypes\DataType::ENUM,
					'unit' => null,
					'format' => [
						DevicesTypes\ConnectionState::CONNECTED->value,
						DevicesTypes\ConnectionState::DISCONNECTED->value,
						DevicesTypes\ConnectionState::LOST->value,
						DevicesTypes\ConnectionState::ALERT->value,
						DevicesTypes\ConnectionState::UNKNOWN->value,
					],
					'settable' => false,
					'queryable' => false,
				]));
			} else {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'device' => $entity,
					'entity' => DevicesEntities\Devices\Properties\Dynamic::class,
					'identifier' => Types\DevicePropertyIdentifier::STATE->value,
					'dataType' => MetadataTypes\DataType::ENUM,
					'unit' => null,
					'format' => [
						DevicesTypes\ConnectionState::CONNECTED->value,
						DevicesTypes\ConnectionState::DISCONNECTED->value,
						DevicesTypes\ConnectionState::LOST->value,
						DevicesTypes\ConnectionState::ALERT->value,
						DevicesTypes\ConnectionState::UNKNOWN->value,
					],
					'settable' => false,
					'queryable' => false,
				]));
			}
		} elseif (
			$entity instanceof DevicesEntities\Connectors\Properties\Variable
			&& $entity->getConnector() instanceof Entities\Connectors\Connector
		) {
			if (
				(
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::CLIENT_MODE->value
					&& Types\ClientMode::tryFrom(MetadataUtilities\Value::toString($entity->getValue(), true)) === null
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::RTU_BYTE_SIZE->value
					&& Types\ByteSize::tryFrom(
						intval(MetadataUtilities\Value::flattenValue($entity->getValue())),
					) === null
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::RTU_BAUD_RATE->value
					&& Types\BaudRate::tryFrom(
						intval(MetadataUtilities\Value::flattenValue($entity->getValue())),
					) === null
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::RTU_PARITY->value
					&& Types\Parity::tryFrom(
						intval(MetadataUtilities\Value::flattenValue($entity->getValue())),
					) === null
				) || (
					$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::RTU_STOP_BITS->value
					&& Types\StopBits::tryFrom(
						intval(MetadataUtilities\Value::flattenValue($entity->getValue())),
					) === null
				)
			) {
				throw new DevicesExceptions\InvalidArgument(sprintf(
					'Provided value for connector property: %s is not in valid range',
					$entity->getIdentifier(),
				));
			}
		} elseif (
			$entity instanceof DevicesEntities\Devices\Properties\Variable
			&& $entity->getDevice() instanceof Entities\Devices\Device
		) {
			if (
				(
					$entity->getIdentifier() === Types\DevicePropertyIdentifier::BYTE_ORDER->value
					&& Types\ByteOrder::tryFrom(MetadataUtilities\Value::toString($entity->getValue(), true)) === null
				)
			) {
				throw new DevicesExceptions\InvalidArgument(sprintf(
					'Provided value for device property: %s is not in valid range',
					$entity->getIdentifier(),
				));
			}
		}
	}

}
