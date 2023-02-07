<?php declare(strict_types = 1);

/**
 * Serial.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\API\Interfaces;

use FastyBird\Connector\Modbus\Types;
use Nette;
use function intval;

/**
 * Base serial interface configuration
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Configuration
{

	use Nette\SmartObject;

	private Types\BaudRate $baudRate;

	private Types\ByteSize $dataBits;

	private Types\StopBits $stopBits;

	private Types\Parity $parity;

	private int $flowControl;

	private int $isCanonical;

	public function __construct(
		Types\BaudRate|null $baudRate = null,
		Types\ByteSize|null $dataBits = null,
		Types\StopBits|null $stopBits = null,
		Types\Parity|null $parity = null,
		bool $flowControl = true,
		bool $isCanonical = true,
	)
	{
		$this->baudRate = $baudRate ?? Types\BaudRate::get(Types\BaudRate::BAUD_RATE_9600);
		$this->dataBits = $dataBits ?? Types\ByteSize::get(Types\ByteSize::SIZE_8);
		$this->stopBits = $stopBits ?? Types\StopBits::get(Types\StopBits::STOP_BIT_ONE);
		$this->parity = $parity ?? Types\Parity::get(Types\Parity::PARITY_NONE);

		$this->flowControl = $flowControl ? 1 : 0;
		$this->isCanonical = $isCanonical ? 1 : 0;
	}

	/**
	 * @return array<string, string|int>
	 */
	public function toArray(): array
	{
		return [
			'data_rate' => intval($this->baudRate->getValue()),
			'data_bits' => intval($this->dataBits->getValue()),
			'stop_bits' => intval($this->stopBits->getValue()),
			'parity' => intval($this->parity->getValue()),
			'flow_control' => $this->flowControl,
			'is_canonical' => $this->isCanonical,
		];
	}

}
