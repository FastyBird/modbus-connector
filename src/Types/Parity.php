<?php declare(strict_types = 1);

/**
 * Parity.php
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
 * Communication stop bits types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Parity extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const PARITY_NONE = 0;

	public const PARITY_ODD = 1;

	public const PARITY_EVEN = 2;

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
