<?php declare(strict_types = 1);

/**
 * RtuFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           20.12.22
 */

namespace FastyBird\Connector\Modbus\API;

use FastyBird\Connector\Modbus\Types;

/**
 * Modbus RTU API interface factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface RtuFactory
{

	public function create(
		Types\BaudRate $baudRate,
		Types\ByteSize $byteSize,
		Types\StopBits $stopBits,
		Types\Parity $parity,
		string $interface,
	): Rtu;

}
