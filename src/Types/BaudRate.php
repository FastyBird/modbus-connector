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

/**
 * Communication baud rate types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum BaudRate: int
{

	case RATE_50 = 50; // posix

	case RATE_75 = 75;

	case RATE_110 = 110;

	case RATE_134 = 134;

	case RATE_150 = 150;

	case RATE_200 = 200; // posix

	case RATE_300 = 300;

	case RATE_600 = 600;

	case RATE_1200 = 1_200;

	case RATE_1800 = 1_800;

	case RATE_2400 = 2_400;

	case RATE_4800 = 4_800;

	case RATE_7200 = 7_200; // win

	case RATE_9600 = 9_600;

	case RATE_14400 = 14_400; // win

	case RATE_19200 = 19_200;

	case RATE_38400 = 38_400;

	case RATE_56000 = 56_000; // win

	case RATE_115200 = 115_200;

	case RATE_128000 = 128_000; // win

	case RATE_256000 = 256_000; // win

	case RATE_230400 = 230_400; // posix

	case RATE_460800 = 460_800; // posix

}
