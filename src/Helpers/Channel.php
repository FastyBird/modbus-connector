<?php declare(strict_types = 1);

/**
 * Channel.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 * @since          0.34.0
 *
 * @date           24.08.22
 */

namespace FastyBird\Connector\Modbus\Helpers;

use DateTimeInterface;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use Nette;
use function is_int;
use function strval;

/**
 * Useful channel helpers
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Channel
{

	use Nette\SmartObject;

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function getConfiguration(
		DevicesEntities\Channels\Channel $channel,
		Types\DevicePropertyIdentifier $type,
	): float|bool|int|string|MetadataTypes\ButtonPayload|MetadataTypes\SwitchPayload|DateTimeInterface|null
	{
		$configuration = $channel->findProperty(strval($type->getValue()));

		if ($configuration instanceof DevicesEntities\Channels\Properties\Variable) {
			if ($type->getValue() === Types\ChannelPropertyIdentifier::IDENTIFIER_ADDRESS) {
				return is_int($configuration->getValue()) ? $configuration->getValue() : null;
			}

			return $configuration->getValue();
		}

		return null;
	}

}
