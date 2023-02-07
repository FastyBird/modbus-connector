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

use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Types;

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

	public const MODE = Types\ClientMode::MODE_TCP;

	public function create(Entities\ModbusConnector $connector): Tcp;

}
