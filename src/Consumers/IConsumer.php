<?php declare(strict_types = 1);

/**
 * IConsumer.php
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

use FastyBird\ModbusConnector\Entities;

/**
 * Clients messages consumer interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IConsumer
{

	/**
	 * @param Entities\Messages\IEntity $entity
	 *
	 * @return bool
	 */
	public function consume(Entities\Messages\IEntity $entity): bool;

}
