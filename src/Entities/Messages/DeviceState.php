<?php declare(strict_types = 1);

/**
 * DeviceState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Properties
 * @since          1.0.0
 *
 * @date           18.01.23
 */

namespace FastyBird\Connector\Modbus\Entities\Messages;

use FastyBird\Library\Metadata\Types as MetadataTypes;
use Ramsey\Uuid;

/**
 * Device state message entity
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceState implements Entity
{

	public function __construct(
		private readonly Uuid\UuidInterface $connector,
		private readonly string $identifier,
		private readonly MetadataTypes\ConnectionState $state,
	)
	{
	}

	public function getConnector(): Uuid\UuidInterface
	{
		return $this->connector;
	}

	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	public function getState(): MetadataTypes\ConnectionState
	{
		return $this->state;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'connector' => $this->connector->toString(),
			'identifier' => $this->getIdentifier(),
			'state' => $this->getState()->getValue(),
		];
	}

}
