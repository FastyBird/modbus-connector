<?php declare(strict_types = 1);

/**
 * ClientVersionType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Types;

use Consistence;

/**
 * Connector client versions types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ClientVersionType extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const TYPE_RTU_DIO = 'rtu_dio';
	public const TYPE_RTU_FILE = 'rtu_file';
	public const TYPE_TCP = 'tcp';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
