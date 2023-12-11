<?php declare(strict_types = 1);

/**
 * ByteOrder.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           21.08.22
 */

namespace FastyBird\Connector\Modbus\Types;

use Consistence;
use function strval;

/**
 * Communication byte order types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ByteOrder extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const BIG = 'big';

	public const BIG_SWAP = 'big_swap';

	public const BIG_LOW_WORD_FIRST = 'big_lwf';

	public const LITTLE = 'little';

	public const LITTLE_SWAP = 'little_swap';

	public const LITTLE_LOW_WORD_FIRST = 'little_lwf';

	public function getValue(): string
	{
		return strval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
