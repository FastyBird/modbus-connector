<?php declare(strict_types = 1);

/**
 * TcpFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           06.02.23
 */

namespace FastyBird\Connector\Modbus\API;

/**
 * Modbus TCP API interface factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface TcpFactory
{

	public function create(): Tcp;

}
