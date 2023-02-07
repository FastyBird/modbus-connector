<?php declare(strict_types = 1);

/**
 * RtuFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\Clients;

use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Types;

/**
 * Modbus RTU devices client factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface RtuFactory extends ClientFactory
{

	public const MODE = Types\ClientMode::MODE_RTU;

	public function create(Entities\ModbusConnector $connector): Rtu;

}
