<?php declare(strict_types = 1);

/**
 * ClientModeType.php
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
 * Connector client mode types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ClientModeType extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const MODE_RTU = 'rtu';
	public const MODE_ASCII = 'ascii';
	public const MODE_TCP = 'tcp';
	public const MODE_TCP_RTU = 'rtu_tcp';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
