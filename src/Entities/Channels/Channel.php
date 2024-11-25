<?php declare(strict_types = 1);

/**
 * Channel.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           30.01.22
 */

namespace FastyBird\Connector\Modbus\Entities\Channels;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Core\Application\Entities\Mapping as ApplicationMapping;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use FastyBird\Core\Tools\Utilities as ToolsUtilities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use Ramsey\Uuid;
use TypeError;
use ValueError;
use function assert;
use function is_float;
use function is_int;
use function is_string;

#[ORM\Entity]
#[ApplicationMapping\DiscriminatorEntry(name: self::TYPE)]
class Channel extends DevicesEntities\Channels\Channel
{

	public const TYPE = 'modbus-connector';

	public function __construct(
		Entities\Devices\Device $device,
		string $identifier,
		string|null $name = null,
		Uuid\UuidInterface|null $id = null,
	)
	{
		parent::__construct($device, $identifier, $name, $id);
	}

	public static function getType(): string
	{
		return self::TYPE;
	}

	public function getSource(): MetadataTypes\Sources\Connector
	{
		return MetadataTypes\Sources\Connector::MODBUS;
	}

	public function getDevice(): Entities\Devices\Device
	{
		assert($this->device instanceof Entities\Devices\Device);

		return $this->device;
	}

	/**
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getAddress(): int|null
	{
		$property = $this->properties
			->filter(
				// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
				static fn (DevicesEntities\Channels\Properties\Property $property): bool => $property->getIdentifier() === Types\ChannelPropertyIdentifier::ADDRESS->value,
			)
			->first();

		if (
			$property instanceof DevicesEntities\Channels\Properties\Variable
			&& is_int($property->getValue())
		) {
			return $property->getValue();
		}

		return null;
	}

	/**
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getRegisterType(): Types\ChannelType|null
	{
		$property = $this->properties
			->filter(
				// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
				static fn (DevicesEntities\Channels\Properties\Property $property): bool => $property->getIdentifier() === Types\ChannelPropertyIdentifier::TYPE->value,
			)
			->first();

		if (
			$property instanceof DevicesEntities\Channels\Properties\Variable
			&& is_string($property->getValue())
			&& Types\ChannelType::tryFrom(ToolsUtilities\Value::toString($property->getValue(), true)) !== null
		) {
			return Types\ChannelType::from(ToolsUtilities\Value::toString($property->getValue(), true));
		}

		return null;
	}

	/**
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getReadingDelay(): float
	{
		$property = $this->properties
			->filter(
				// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
				static fn (DevicesEntities\Channels\Properties\Property $property): bool => $property->getIdentifier() === Types\ChannelPropertyIdentifier::READING_DELAY->value,
			)
			->first();

		if (
			$property instanceof DevicesEntities\Channels\Properties\Variable
			&& (
				is_int($property->getValue())
				|| is_float($property->getValue())
			)
		) {
			return $property->getValue();
		}

		return Modbus\Constants::READING_DELAY;
	}

}
