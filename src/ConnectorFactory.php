<?php declare(strict_types = 1);

/**
 * ConnectorFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     common
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;

/**
 * Connector service container factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorFactory implements DevicesModuleConnectors\IConnectorFactory
{

	use Nette\SmartObject;

	/** @var Clients\ClientFactory[] */
	private array $clientsFactories;

	/** @var DevicesModuleModels\DataStorage\IConnectorPropertiesRepository */
	private DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $connectorPropertiesRepository;

	/** @var Connector\ConnectorFactory */
	private Connector\ConnectorFactory $connectorFactory;

	/**
	 * @param Clients\ClientFactory[] $clientsFactories
	 * @param Connector\ConnectorFactory $connectorFactory
	 * @param DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $connectorPropertiesRepository;
	 */
	public function __construct(
		array $clientsFactories,
		Connector\ConnectorFactory $connectorFactory,
		DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $connectorPropertiesRepository
	) {
		$this->clientsFactories = $clientsFactories;
		$this->connectorFactory = $connectorFactory;

		$this->connectorPropertiesRepository = $connectorPropertiesRepository;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return Entities\ModbusConnectorEntity::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function create(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	): DevicesModuleConnectors\IConnector {
		foreach ($this->clientsFactories as $clientFactory) {
			if (method_exists($clientFactory, 'create')) {
				return $this->connectorFactory->create($connector, $clientFactory->create($connector));
			}
		}

		throw new DevicesModuleExceptions\TerminateException('Connector client is not configured');
	}

}
