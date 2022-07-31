<?php declare(strict_types = 1);

/**
 * ConnectorFactory.php
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

namespace FastyBird\ModbusConnector\Connector;

use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ModbusConnector\Clients;

/**
 * Connector service factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface ConnectorFactory
{

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Clients\IClient $client
	 *
	 * @return Connector
	 */
	public function create(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Clients\IClient $client
	): Connector;

}
