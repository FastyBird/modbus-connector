<?php declare(strict_types = 1);

/**
 * EventFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Writers
 * @since          1.0.0
 *
 * @date           08.12.23
 */

namespace FastyBird\Connector\Modbus\Writers;

use FastyBird\Library\Metadata\Documents as MetadataDocuments;

/**
 * System event device state periodic writer factory
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface EventFactory extends WriterFactory
{

	public function create(MetadataDocuments\DevicesModule\Connector $connector): Event;

}
