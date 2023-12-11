<?php declare(strict_types = 1);

/**
 * ByteSize.php
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
 * Communication data bits types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ByteSize extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const SIZE_4 = 4; // win

	public const SIZE_5 = 5;

	public const SIZE_6 = 6;

	public const SIZE_7 = 7;

	public const SIZE_8 = 8;

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
