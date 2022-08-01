<?php declare(strict_types = 1);

/**
 * DataBitsType.php
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
 * Communication data bits types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DataBitsType extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	//public const DATA_BIT_4 = 4; // win
	public const DATA_BIT_5 = 5;
	public const DATA_BIT_6 = 6;
	public const DATA_BIT_7 = 7;
	public const DATA_BIT_8 = 8;

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
