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
use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use IPub\DoctrineCrud;
use Nette;
use Nette\Utils;
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
		private readonly DevicesModels\Devices\Properties\PropertiesRepository $propertiesRepository,
		private readonly DevicesModels\Devices\Properties\PropertiesManager $propertiesManager,
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
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws DoctrineCrud\Exceptions\InvalidArgumentException
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function postPersist(Persistence\Event\LifecycleEventArgs $eventArgs): void
	{
		// onFlush was executed before, everything already initialized
		$entity = $eventArgs->getObject();

		// Check for valid entity
		if ($entity instanceof Entities\ModbusDevice) {
			$findDevicePropertyQuery = new DevicesQueries\FindDeviceProperties();
			$findDevicePropertyQuery->forDevice($entity);
			$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::IDENTIFIER_STATE);

			$stateProperty = $this->propertiesRepository->findOneBy($findDevicePropertyQuery);

			if ($stateProperty !== null && !$stateProperty instanceof DevicesEntities\Devices\Properties\Dynamic) {
				$this->propertiesManager->delete($stateProperty);

				$stateProperty = null;
			}

			if ($stateProperty !== null) {
				$this->propertiesManager->update($stateProperty, Utils\ArrayHash::from([
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_ENUM),
					'unit' => null,
					'format' => [
						MetadataTypes\ConnectionState::STATE_CONNECTED,
						MetadataTypes\ConnectionState::STATE_DISCONNECTED,
						MetadataTypes\ConnectionState::STATE_STOPPED,
						MetadataTypes\ConnectionState::STATE_LOST,
						MetadataTypes\ConnectionState::STATE_UNKNOWN,
					],
					'settable' => false,
					'queryable' => false,
				]));
			} else {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'device' => $entity,
					'entity' => DevicesEntities\Devices\Properties\Dynamic::class,
					'identifier' => Types\DevicePropertyIdentifier::IDENTIFIER_STATE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_ENUM),
					'unit' => null,
					'format' => [
						MetadataTypes\ConnectionState::STATE_CONNECTED,
						MetadataTypes\ConnectionState::STATE_DISCONNECTED,
						MetadataTypes\ConnectionState::STATE_STOPPED,
						MetadataTypes\ConnectionState::STATE_LOST,
						MetadataTypes\ConnectionState::STATE_UNKNOWN,
					],
					'settable' => false,
					'queryable' => false,
				]));
			}
		} elseif (
			$entity instanceof DevicesEntities\Connectors\Properties\Variable
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
				throw new DevicesExceptions\InvalidArgument(sprintf(
					'Provided value for connector property: %s is not in valid range',
					$entity->getIdentifier(),
				));
			}
		} elseif (
			$entity instanceof DevicesEntities\Devices\Properties\Variable
			&& $entity->getDevice() instanceof Entities\ModbusDevice
		) {
			if (
				(
					$entity->getIdentifier() === Types\DevicePropertyIdentifier::IDENTIFIER_BYTE_ORDER
					&& !Types\ByteOrder::isValidValue($entity->getValue())
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
