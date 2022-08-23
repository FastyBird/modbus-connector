<?php declare(strict_types = 1);

/**
 * ByteOrderType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          0.34.0
 *
 * @date           21.08.22
 */

namespace FastyBird\ModbusConnector\Types;

use Consistence;

/**
 * Communication byte order types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ByteOrderType extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const BYTE_ORDER_BIG = 'big';
	public const BYTE_ORDER_BIG_SWAP = 'big_swap';
	public const BYTE_ORDER_LITTLE = 'little';
	public const BYTE_ORDER_LITTLE_SWAP = 'little_swap';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
