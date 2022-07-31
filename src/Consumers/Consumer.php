<?php declare(strict_types = 1);

/**
 * Consumer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Consumers
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Consumers;

use FastyBird\Metadata;
use FastyBird\ModbusConnector\Entities;
use Nette;
use Psr\Log;
use SplObjectStorage;
use SplQueue;

/**
 * Clients message consumer proxy
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Consumer
{

	use Nette\SmartObject;

	/** @var SplObjectStorage<IConsumer, null> */
	private SplObjectStorage $consumers;

	/** @var SplQueue<Entities\Messages\IEntity> */
	private SplQueue $queue;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param IConsumer[] $consumers
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		array $consumers,
		?Log\LoggerInterface $logger = null
	) {
		$this->consumers = new SplObjectStorage();
		$this->queue = new SplQueue();

		$this->logger = $logger ?? new Log\NullLogger();

		foreach ($consumers as $consumer) {
			$this->consumers->attach($consumer);
		}

		$this->logger->debug(
			sprintf('Registered %d messages consumers', count($this->consumers)),
			[
				'source' => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
				'type'   => 'consumer',
			]
		);
	}

	/**
	 * @param Entities\Messages\IEntity $entity
	 *
	 * @return void
	 */
	public function append(Entities\Messages\IEntity $entity): void
	{
		$this->queue->enqueue($entity);

		$this->logger->debug(
			'Appended new message into consumers queue',
			[
				'source'  => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
				'type'    => 'consumer',
				'message' => $entity->toArray(),
			]
		);
	}

	/**
	 * @return void
	 */
	public function consume(): void
	{
		$this->queue->rewind();

		if ($this->queue->isEmpty()) {
			return;
		}

		$this->consumers->rewind();

		if ($this->consumers->count() === 0) {
			$this->logger->error(
				'No consumer is registered, messages could not be consumed',
				[
					'source'  => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
					'type'    => 'consumer',
				]
			);

			return;
		}

		$entity = $this->queue->dequeue();

		/** @var IConsumer $consumer */
		foreach ($this->consumers as $consumer) {
			if ($consumer->consume($entity) === true) {
				return;
			}
		}

		$this->logger->error(
			'Message could not be consumed',
			[
				'source'  => Metadata\Constants::CONNECTOR_MODBUS_SOURCE,
				'type'    => 'consumer',
				'message' => $entity->toArray(),
			]
		);
	}

	/**
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return $this->queue->isEmpty();
	}

}
