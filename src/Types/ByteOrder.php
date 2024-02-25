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

/**
 * Communication byte order types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ByteOrder: string
{

	case BIG = 'big';

	case BIG_SWAP = 'big_swap';

	case BIG_LOW_WORD_FIRST = 'big_lwf';

	case LITTLE = 'little';

	case LITTLE_SWAP = 'little_swap';

	case LITTLE_LOW_WORD_FIRST = 'little_lwf';

}
