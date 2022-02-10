<?php declare(strict_types = 1);

/**
 * IModbusConnector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           07.12.21
 */

namespace FastyBird\ModbusConnector\Entities;

use FastyBird\DevicesModule\Entities as DevicesModuleEntities;

/**
 * Modbus connector entity interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IModbusConnector extends DevicesModuleEntities\Connectors\IConnector
{

	/**
	 * @return string|null
	 */
	public function getInterface(): ?string;

	/**
	 * @return int|null
	 */
	public function getBaudRate(): ?int;

}
