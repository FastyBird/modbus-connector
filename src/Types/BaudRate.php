<?php declare(strict_types = 1);

/**
 * BaudRate.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\Types;

use Consistence;
use function intval;
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
	public const RATE_50 = 50; // posix

	public const RATE_75 = 75;

	public const RATE_110 = 110;

	public const RATE_134 = 134;

	public const RATE_150 = 150;

	public const RATE_200 = 200; // posix

	public const RATE_300 = 300;

	public const RATE_600 = 600;

	public const RATE_1200 = 1_200;

	public const RATE_1800 = 1_800;

	public const RATE_2400 = 2_400;

	public const RATE_4800 = 4_800;

	public const RATE_7200 = 7_200; // win

	public const RATE_9600 = 9_600;

	public const RATE_14400 = 14_400; // win

	public const RATE_19200 = 19_200;

	public const RATE_38400 = 38_400;

	public const RATE_56000 = 56_000; // win

	public const RATE_115200 = 115_200;

	public const RATE_128000 = 128_000; // win

	public const RATE_256000 = 256_000; // win

	public const RATE_230400 = 230_400; // posix

	public const RATE_460800 = 460_800; // posix

	public function getValue(): int
	{
		return intval(parent::getValue());
	}

	/**
	 * @return array<int>
	 */
	public static function getValues(): array
	{
		/** @var iterable<int> $availableValues */
		$availableValues = parent::getAvailableValues();

		return (array) $availableValues;
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
