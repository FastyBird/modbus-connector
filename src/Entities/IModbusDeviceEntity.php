<?php declare(strict_types = 1);

/**
 * IModbusDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           30.01.22
 */

namespace FastyBird\ModbusConnector\Entities;

use FastyBird\DevicesModule\Entities as DevicesModuleEntities;

/**
 * Modbus device entity interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IModbusDeviceEntity extends DevicesModuleEntities\Devices\IDevice
{

}
