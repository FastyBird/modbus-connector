<?php declare(strict_types = 1);

/**
 * Channel.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Helpers
 * @since          1.0.0
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
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
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

	public function __construct(
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelPropertiesRepository,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function getConfiguration(
		DevicesEntities\Channels\Channel $channel,
		Types\ChannelPropertyIdentifier $type,
	): float|bool|int|string|MetadataTypes\ButtonPayload|MetadataTypes\SwitchPayload|MetadataTypes\CoverPayload|DateTimeInterface|null
	{
		$findChannelPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byIdentifier(strval($type->getValue()));

		$configuration = $this->channelPropertiesRepository->findOneBy($findChannelPropertyQuery);

		if ($configuration instanceof DevicesEntities\Channels\Properties\Variable) {
			if ($type->getValue() === Types\ChannelPropertyIdentifier::IDENTIFIER_ADDRESS) {
				return is_int($configuration->getValue()) ? $configuration->getValue() : null;
			}

			if ($type->getValue() === Types\ChannelPropertyIdentifier::IDENTIFIER_TYPE) {
				if (Types\ChannelType::isValidValue($configuration->getValue())) {
					return DevicesUtilities\ValueHelper::flattenValue($configuration->getValue());
				}

				return null;
			}

			return $configuration->getValue();
		}

		return null;
	}

}
