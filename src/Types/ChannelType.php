<?php declare(strict_types = 1);

/**
 * ChannelType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           21.01.23
 */

namespace FastyBird\Connector\Modbus\Types;

use Consistence;
use function strval;

/**
 * Device channel type types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ChannelType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const DISCRETE_INPUT = 'discrete_input';

	public const COIL = 'coil';

	public const INPUT_REGISTER = 'input_register';

	public const HOLDING_REGISTER = 'holding_register';

	public function getValue(): string
	{
		return strval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
