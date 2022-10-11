<?php declare(strict_types = 1);

/**
 * BaudRate.php
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
use function strval;

/**
 * Communication baud rate types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class BaudRate extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const BAUD_RATE_50 = 50; // posix

	public const BAUD_RATE_75 = 75;

	public const BAUD_RATE_110 = 110;

	public const BAUD_RATE_134 = 134;

	public const BAUD_RATE_150 = 150;

	public const BAUD_RATE_200 = 200; // posix

	public const BAUD_RATE_300 = 300;

	public const BAUD_RATE_600 = 600;

	public const BAUD_RATE_1200 = 1_200;

	public const BAUD_RATE_1800 = 1_800;

	public const BAUD_RATE_2400 = 2_400;

	public const BAUD_RATE_4800 = 4_800;

	public const BAUD_RATE_7200 = 7_200; // win

	public const BAUD_RATE_9600 = 9_600;

	public const BAUD_RATE_14400 = 14_400; // win

	public const BAUD_RATE_19200 = 19_200;

	public const BAUD_RATE_38400 = 38_400;

	public const BAUD_RATE_56000 = 56_000; // win

	public const BAUD_RATE_115200 = 115_200;

	public const BAUD_RATE_128000 = 128_000; // win

	public const BAUD_RATE_256000 = 256_000; // win

	public const BAUD_RATE_230400 = 230_400; // posix

	public const BAUD_RATE_460800 = 460_800; // posix

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
