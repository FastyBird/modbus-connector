<?php declare(strict_types = 1);

/**
 * TcpFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           18.01.23
 */

namespace FastyBird\Connector\Modbus\Clients;

use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;

/**
 * Modbus TCP devices client factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface TcpFactory extends ClientFactory
{

	public const MODE = Types\ClientMode::TCP;

	public function create(MetadataDocuments\DevicesModule\Connector $connector): Tcp;

}
