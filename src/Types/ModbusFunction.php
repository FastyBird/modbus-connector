<?php declare(strict_types = 1);

/**
 * ModbusFunction.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           03.02.23
 */

namespace FastyBird\Connector\Modbus\Types;

use Consistence;
use function intval;
use function strval;

/**
 * RTU function types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ModbusFunction extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const READ_COIL = 0x01;

	public const READ_DISCRETE = 0x02;

	public const READ_HOLDINGS_REGISTERS = 0x03;

	public const READ_INPUTS_REGISTERS = 0x04;

	public const WRITE_SINGLE_COIL = 0x05;

	public const WRITE_SINGLE_HOLDING_REGISTER = 0x06;

	public const WRITE_MULTIPLE_COILS = 0x1F;

	public const WRITE_MULTIPLE_HOLDINGS_REGISTERS = 0x10;

	public function getValue(): int
	{
		return intval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
