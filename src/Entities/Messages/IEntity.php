<?php declare(strict_types = 1);

/**
 * IEntity.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Entities\Messages;

/**
 * Modbus base message data entity interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IEntity
{

	/**
	 * @return Array<string, mixed>
	 */
	public function toArray(): array;

}
