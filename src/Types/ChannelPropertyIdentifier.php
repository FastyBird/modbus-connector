<?php declare(strict_types = 1);

/**
 * ChannelPropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           01.08.22
 */

namespace FastyBird\Connector\Modbus\Types;

use FastyBird\Module\Devices\Types as DevicesTypes;

/**
 * Device property identifier types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ChannelPropertyIdentifier: string
{

	case ADDRESS = DevicesTypes\ChannelPropertyIdentifier::ADDRESS->value;

	case TYPE = 'type';

	case VALUE = 'value';

	case READING_DELAY = 'reading_delay';

}
