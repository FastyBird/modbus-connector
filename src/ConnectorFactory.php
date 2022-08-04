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
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use ReflectionClass;

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

	/** @var Connector\ConnectorFactory */
	private Connector\ConnectorFactory $connectorFactory;

	/** @var Helpers\ConnectorHelper */
	private Helpers\ConnectorHelper $connectorHelper;

	/**
	 * @param Clients\ClientFactory[] $clientsFactories
	 * @param Connector\ConnectorFactory $connectorFactory
	 * @param Helpers\ConnectorHelper $connectorHelper
	 */
	public function __construct(
		array $clientsFactories,
		Connector\ConnectorFactory $connectorFactory,
		Helpers\ConnectorHelper $connectorHelper
	) {
		$this->clientsFactories = $clientsFactories;
		$this->connectorFactory = $connectorFactory;
		$this->connectorHelper = $connectorHelper;
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
		$mode = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE)
		);

		if ($mode === null) {
			throw new DevicesModuleExceptions\TerminateException('Connector client mode is not configured');
		}

		foreach ($this->clientsFactories as $clientFactory) {
			$rc = new ReflectionClass($clientFactory);

			$constants = $rc->getConstants();

			if (
				array_key_exists(Clients\ClientFactory::MODE_CONSTANT_NAME, $constants)
				&& $constants[Clients\ClientFactory::MODE_CONSTANT_NAME] === $mode
				&& method_exists($clientFactory, 'create')
			) {
				return $this->connectorFactory->create($connector, $clientFactory->create($connector));
			}
		}

		throw new DevicesModuleExceptions\TerminateException('Connector client is not configured');
	}

}
