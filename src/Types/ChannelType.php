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

/**
 * Device channel type types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ChannelType: string
{

	case DISCRETE_INPUT = 'discrete_input';

	case COIL = 'coil';

	case INPUT_REGISTER = 'input_register';

	case HOLDING_REGISTER = 'holding_register';

}
