<?php declare(strict_types = 1);

/**
 * StopBits.php
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
 * Communication stop bits types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class StopBits extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const NONE = 0;

	public const ONE = 1;

	public const TWO = 2;

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
