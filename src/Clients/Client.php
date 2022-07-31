<?php declare(strict_types = 1);

/**
 * Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Clients;

use Nette;

/**
 * Base client service
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Client implements IClient
{

	use Nette\SmartObject;

}
