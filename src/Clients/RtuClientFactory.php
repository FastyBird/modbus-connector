<?php declare(strict_types = 1);

/**
 * RtuClientFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Clients;

use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ModbusConnector\Types;

/**
 * Generation 1 devices client factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface RtuClientFactory extends ClientFactory
{

	public const VERSION = Types\ClientVersionType::TYPE_RTU_DIO;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return RtuClient
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): RtuClient;

}
