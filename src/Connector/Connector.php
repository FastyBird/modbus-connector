<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Connector
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\Connector;

use FastyBird\Connector\Modbus\Clients;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Connectors as DevicesConnectors;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use Nette;
use ReflectionClass;
use function array_key_exists;

/**
 * Connector service executor
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesConnectors\Connector
{

	use Nette\SmartObject;

	private Clients\Client|null $client = null;

	/**
	 * @param Array<Clients\ClientFactory> $clientsFactories
	 */
	public function __construct(
		private readonly MetadataEntities\DevicesModule\Connector $connector,
		private readonly array $clientsFactories,
		private readonly Helpers\Connector $connectorHelper,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Terminate
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function execute(): void
	{
		$mode = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE),
		);

		if ($mode === null) {
			throw new DevicesExceptions\Terminate('Connector client mode is not configured');
		}

		foreach ($this->clientsFactories as $clientFactory) {
			$rc = new ReflectionClass($clientFactory);

			$constants = $rc->getConstants();

			if (
				array_key_exists(Clients\ClientFactory::MODE_CONSTANT_NAME, $constants)
				&& $constants[Clients\ClientFactory::MODE_CONSTANT_NAME] === $mode
			) {
				$this->client = $clientFactory->create($this->connector);
			}
		}

		if ($this->client === null) {
			throw new DevicesExceptions\Terminate('Connector client is not configured');
		}

		$this->client->connect();
	}

	public function terminate(): void
	{
		$this->client?->disconnect();
	}

	public function hasUnfinishedTasks(): bool
	{
		return false;
	}

}
