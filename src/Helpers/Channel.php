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
use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\Documents;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Queries;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use FastyBird\Core\Tools\Utilities as ToolsUtilities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use Nette;
use TypeError;
use ValueError;
use function assert;
use function is_float;
use function is_int;
use function is_string;

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
		private readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getAddress(Documents\Channels\Channel $channel): int|null
	{
		$findPropertyQuery = new Queries\Configuration\FindChannelVariableProperties();
		$findPropertyQuery->forChannel($channel);
		$findPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::ADDRESS);

		$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Channels\Properties\Variable::class,
		);

		if ($property === null) {
			return null;
		}

		$value = $property->getValue();
		assert(is_int($value) || $value === null);

		return $value;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getRegisterType(
		Documents\Channels\Channel $channel,
	): Types\ChannelType|null
	{
		$findPropertyQuery = new Queries\Configuration\FindChannelVariableProperties();
		$findPropertyQuery->forChannel($channel);
		$findPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::TYPE);

		$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Channels\Properties\Variable::class,
		);

		if (
			$property?->getValue() === null
			|| !is_string($property->getValue())
			|| Types\ChannelType::tryFrom($property->getValue()) === null
		) {
			return null;
		}

		return Types\ChannelType::from($property->getValue());
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getReadingDelay(Documents\Channels\Channel $channel): float
	{
		$findPropertyQuery = new Queries\Configuration\FindChannelVariableProperties();
		$findPropertyQuery->forChannel($channel);
		$findPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::READING_DELAY);

		$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Channels\Properties\Variable::class,
		);

		if ($property?->getValue() === null) {
			return Modbus\Constants::READING_DELAY;
		}

		$value = $property->getValue();
		assert(is_float($value));

		return $value;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getConfiguration(
		Documents\Channels\Channel $channel,
		Types\ChannelPropertyIdentifier $type,
	): float|bool|int|string|MetadataTypes\Payloads\Payload|DateTimeInterface|null
	{
		$findChannelPropertyQuery = new Queries\Configuration\FindChannelVariableProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byIdentifier($type);

		$configuration = $this->channelsPropertiesConfigurationRepository->findOneBy(
			$findChannelPropertyQuery,
			DevicesDocuments\Channels\Properties\Variable::class,
		);

		if ($configuration instanceof DevicesDocuments\Channels\Properties\Variable) {
			if ($type === Types\ChannelPropertyIdentifier::ADDRESS) {
				return is_int($configuration->getValue()) ? $configuration->getValue() : null;
			}

			if ($type === Types\ChannelPropertyIdentifier::TYPE) {
				if (Types\ChannelType::tryFrom(
					ToolsUtilities\Value::toString($configuration->getValue(), true),
				) !== null) {
					return ToolsUtilities\Value::flattenValue($configuration->getValue());
				}

				return null;
			}

			return $configuration->getValue();
		}

		return null;
	}

}
