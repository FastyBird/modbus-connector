<?php declare(strict_types = 1);

/**
 * ConnectorControlName.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 * @since          0.34.0
 *
 * @date           04.08.22
 */

namespace FastyBird\ModbusConnector\Types;

use Consistence;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * Connector control name types
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ConnectorControlName extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const NAME_REBOOT = MetadataTypes\ControlNameType::NAME_REBOOT;

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
