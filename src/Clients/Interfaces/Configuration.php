<?php declare(strict_types = 1);

/**
 * Serial.php
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

namespace FastyBird\ModbusConnector\Clients\Interfaces;

use FastyBird\ModbusConnector\Types;
use Nette;

/**
 * Base serial interface configuration
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Configuration
{

	use Nette\SmartObject;

	/** @var Types\BaudRateType */
	private Types\BaudRateType $baudRate;

	/** @var Types\ByteSizeType */
	private Types\ByteSizeType $dataBits;

	/** @var Types\StopBitsType */
	private Types\StopBitsType $stopBits;

	/** @var Types\ParityType */
	private Types\ParityType $parity;

	/** @var int */
	private int $flowControl;

	/** @var int */
	private int $isCanonical;

	/**
	 * @param Types\BaudRateType|null $baudRate
	 * @param Types\ByteSizeType|null $dataBits
	 * @param Types\StopBitsType|null $stopBits
	 * @param Types\ParityType|null $parity
	 * @param bool $flowControl
	 * @param bool $isCanonical
	 */
	public function __construct(
		?Types\BaudRateType $baudRate = null,
		?Types\ByteSizeType $dataBits = null,
		?Types\StopBitsType $stopBits = null,
		?Types\ParityType $parity = null,
		bool $flowControl = true,
		bool $isCanonical = true,
	) {
		$this->baudRate = $baudRate ?? Types\BaudRateType::get(Types\BaudRateType::BAUD_RATE_9600);
		$this->dataBits = $dataBits ?? Types\ByteSizeType::get(Types\ByteSizeType::SIZE_8);
		$this->stopBits = $stopBits ?? Types\StopBitsType::get(Types\StopBitsType::STOP_BIT_ONE);
		$this->parity = $parity ?? Types\ParityType::get(Types\ParityType::PARITY_NONE);

		$this->flowControl = $flowControl ? 1 : 0;
		$this->isCanonical = $isCanonical ? 1 : 0;
	}

	/**
	 * Sets the data rate
	 *
	 * @param Types\BaudRateType $baudRate
	 *
	 * @return void
	 */
	public function setBaudRate(Types\BaudRateType $baudRate): void
	{
		$this->baudRate = $baudRate;
	}

	/**
	 * Sets the number of data bits
	 *
	 * @param Types\ByteSizeType $dataBits
	 *
	 * @return void
	 */
	public function setDataBits(Types\ByteSizeType $dataBits): void
	{
		$this->dataBits = $dataBits;
	}

	/**
	 * Sets the number of stop bits
	 *
	 * @param Types\StopBitsType $stopBits
	 *
	 * @return void
	 */
	public function setStopBits(Types\StopBitsType $stopBits): void
	{
		$this->stopBits = $stopBits;
	}

	/**
	 * Sets the parity
	 *
	 * @param Types\ParityType $parity
	 *
	 * @return void
	 */
	public function setParity(Types\ParityType $parity): void
	{
		$this->parity = $parity;
	}

	/**
	 * Sets the flow control
	 *
	 * @param bool $flowControl
	 *
	 * @return void
	 */
	public function setFlowControl(bool $flowControl): void
	{
		$this->flowControl = $flowControl ? 1 : 0;
	}

	/**
	 * Sets the canonical option of serial port
	 *
	 * @param bool $canonical
	 *
	 * @return void
	 */
	public function setCanonical(bool $canonical): void
	{
		$this->isCanonical = $canonical ? 1 : 0;
	}

	/**
	 * @return Array<string, string|int>
	 */
	public function toArray(): array
	{
		return [
			'data_rate'    => intval($this->baudRate->getValue()),
			'data_bits'    => intval($this->dataBits->getValue()),
			'stop_bits'    => intval($this->stopBits->getValue()),
			'parity'       => intval($this->parity->getValue()),
			'flow_control' => $this->flowControl,
			'is_canonical' => $this->isCanonical,
		];
	}

}
