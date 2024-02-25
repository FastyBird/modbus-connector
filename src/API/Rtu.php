<?php declare(strict_types = 1);

/**
 * Rtu.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           03.02.23
 */

namespace FastyBird\Connector\Modbus\API;

use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\API\Messages\Response\ReadAnalogInputs;
use FastyBird\Connector\Modbus\API\Messages\Response\ReadDigitalInputs;
use FastyBird\Connector\Modbus\API\Messages\Response\WriteCoil;
use FastyBird\Connector\Modbus\API\Messages\Response\WriteHoldingRegister;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Types;
use Nette;
use Nette\Utils;
use function array_combine;
use function array_fill;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function current;
use function decbin;
use function get_loaded_extensions;
use function pack;
use function str_repeat;
use function str_split;
use function strlen;
use function strrev;
use function substr;
use function unpack;

/**
 * Modbus RTU API interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Rtu
{

	use Nette\SmartObject;

	private const MODBUS_ADU = 'C1station/C1function/C*data/';

	private const MODBUS_ERROR = 'C1station/C1error/C1exception/';

	private API\Interfaces\Serial $interface;

	private bool $isOpen = false;

	/**
	 * @throws Exceptions\InvalidState
	 */
	public function __construct(
		Types\BaudRate $baudRate,
		Types\ByteSize $byteSize,
		Types\StopBits $stopBits,
		Types\Parity $parity,
		string $interface,
	)
	{
		$configuration = new API\Interfaces\Configuration(
			$baudRate,
			$byteSize,
			$stopBits,
			$parity,
			false,
			false,
		);

		$useDio = false;

		foreach (get_loaded_extensions() as $extension) {
			if (Utils\Strings::contains('dio', Utils\Strings::lower($extension))) {
				$useDio = true;

				break;
			}
		}

		$this->interface = $useDio
			? new API\Interfaces\SerialDio($interface, $configuration)
			: new API\Interfaces\SerialFile($interface, $configuration);
	}

	/**
	 * @throws Exceptions\InvalidState
	 */
	public function open(): void
	{
		$this->interface->open();
		$this->isOpen = true;
	}

	/**
	 * @throws Exceptions\InvalidState
	 */
	public function close(): void
	{
		$this->isOpen = false;
		$this->interface->close();
	}

	/**
	 * (0x01) Read Coils
	 *
	 * This function code is used to read from 1 to 2000 contiguous status of coils in a remote device.
	 * The Request PDU specifies the starting address, i.e. the address of the first coil specified,
	 * and the number of coils. In the PDU Coils are addressed starting at zero, therefore coils
	 * numbered 1-16 are addressed as 0-15.
	 *
	 * The coils in the response message are packed as one coil per a bit of the data field.
	 * Status is indicated as 1= ON and 0= OFF. The LSB of the first data byte contains the output
	 * addressed in the query. The other coils follow toward the high order end of this byte,
	 * and from low order to high order in subsequent bytes.
	 *
	 * If the returned output quantity is not a multiple of eight, the remaining bits in the final data byte
	 * will be padded with zeros (toward the high order end of the byte). The Byte Count field specifies
	 * the quantity of complete bytes of data.
	 *
	 * @return ($raw is true ? string : ReadDigitalInputs)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	public function readCoils(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false,
	): string|Messages\Response\ReadDigitalInputs
	{
		return $this->readDigitalRegisters(
			Types\ModbusFunction::READ_COIL,
			$station,
			$startingAddress,
			$quantity,
			$raw,
		);
	}

	/**
	 * (0x02) Read Discrete Inputs
	 *
	 * @return ($raw is true ? string : ReadDigitalInputs)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	public function readDiscreteInputs(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false,
	): string|Messages\Response\ReadDigitalInputs
	{
		return $this->readDigitalRegisters(
			Types\ModbusFunction::READ_DISCRETE,
			$station,
			$startingAddress,
			$quantity,
			$raw,
		);
	}

	/**
	 * (0x03) Read Holding Registers
	 *
	 * @return ($raw is true ? string : ReadAnalogInputs)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	public function readHoldingRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false,
	): string|Messages\Response\ReadAnalogInputs
	{
		return $this->readAnalogRegisters(
			Types\ModbusFunction::READ_HOLDINGS_REGISTERS,
			$station,
			$startingAddress,
			$quantity,
			$raw,
		);
	}

	/**
	 * (0x04) Read Input Registers
	 *
	 * @return ($raw is true ? string : ReadAnalogInputs)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	public function readInputRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false,
	): string|Messages\Response\ReadAnalogInputs
	{
		return $this->readAnalogRegisters(
			Types\ModbusFunction::READ_INPUTS_REGISTERS,
			$station,
			$startingAddress,
			$quantity,
			$raw,
		);
	}

	/**
	 * (0x05) Write Single Coil
	 *
	 * @return ($raw is true ? string : WriteCoil)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	public function writeSingleCoil(
		int $station,
		int $coilAddress,
		bool $value,
		bool $raw = false,
	): string|Messages\Response\WriteCoil
	{
		$functionCode = Types\ModbusFunction::WRITE_SINGLE_COIL;

		// Pack header (transform to binary)
		$request = pack('C2n1', $station, $functionCode->value, $coilAddress);
		// Pack value (transform to binary)
		$request .= pack('n1', $value ? 0xFF00 : 0x0000);
		// Append CRC check
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if ($raw === false) {
			$header = unpack('C1station/C1function/n1address', $response);

			if ($header === false) {
				throw new Exceptions\ModbusRtu('Response header could not be parsed');
			}

			$valueUnpacked = unpack('n1', substr($response, 4, -2));

			if ($valueUnpacked === false) {
				throw new Exceptions\ModbusRtu('Response data could not be parsed');
			}

			return new Messages\Response\WriteCoil(
				$header['station'],
				$functionCode,
				current($valueUnpacked) === 0xFF00,
			);
		}

		return $response;
	}

	/**
	 * (0x06) Write Single Register
	 *
	 * @param array<int> $value
	 *
	 * @return ($raw is true ? string : WriteHoldingRegister)
	 *
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	public function writeSingleHolding(
		int $station,
		int $registerAddress,
		array $value,
		bool $raw = false,
	): string|Messages\Response\WriteHoldingRegister
	{
		$functionCode = count($value) === 2
			? Types\ModbusFunction::WRITE_SINGLE_HOLDING_REGISTER
			: Types\ModbusFunction::WRITE_MULTIPLE_HOLDINGS_REGISTERS;

		// Pack header (transform to binary)
		$request = pack('C2n1', $station, $functionCode->value, $registerAddress);

		if (count($value) === 2) {
			// Pack value (transform to binary)
			$request .= pack('C2', ...$value);

		} elseif (count($value) === 4) {
			$request .= pack('n1C1', 2, 4);
			// Pack value (transform to binary)
			$request .= pack('C4', ...$value);

		} else {
			throw new Exceptions\InvalidState('Value could not be converted to bytes');
		}

		// Append CRC check
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if ($raw === false) {
			$header = unpack('C1station/C1function/n1address', $response);

			if ($header === false) {
				throw new Exceptions\ModbusRtu('Response header could not be parsed');
			}

			return new Messages\Response\WriteHoldingRegister(
				$header['station'],
				$functionCode,
			);
		}

		return $response;
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	private function sendRequest(string $request): string
	{
		if (!$this->isOpen) {
			throw new Exceptions\ModbusRtu('Connection was closed');
		}

		$this->interface->send($request);

		$response = $this->interface->read();

		if ($response === false) {
			throw new Exceptions\ModbusRtu('Response could not be read from interface');
		}

		if (strlen($response) < 4) {
			throw new Exceptions\ModbusRtu('Response length too short', -1, $request, $response);
		}

		$aduRequest = unpack(self::MODBUS_ADU, $request);

		if ($aduRequest === false) {
			throw new Exceptions\ModbusRtu('ADU could not be extracted from response');
		}

		$aduResponse = unpack(self::MODBUS_ERROR, $response);

		if ($aduResponse === false) {
			throw new Exceptions\ModbusRtu('Error could not be extracted from response');
		}

		if ($aduRequest['function'] !== $aduResponse['error']) {
			// Error code = Function code + 0x80
			if ($aduResponse['error'] === $aduRequest['function'] + 0x80) {
				throw new Exceptions\ModbusRtu(null, $aduResponse['exception'], $request, $response);
			} else {
				throw new Exceptions\ModbusRtu('Illegal error code', -3, $request, $response);
			}
		}

		if (substr($response, -2) !== $this->crc16(substr($response, 0, -2))) {
			throw new Exceptions\ModbusRtu('Error check fails', -2, $request, $response);
		}

		return $response;
	}

	/**
	 * @throws Exceptions\ModbusRtu
	 */
	private function crc16(string $data): string
	{
		$crc = 0xFFFF;

		$bytes = unpack('C*', $data);

		if ($bytes === false) {
			throw new Exceptions\ModbusRtu('Message CRC could not be calculated');
		}

		foreach ($bytes as $byte) {
			$crc ^= $byte;

			for ($j = 8; $j; $j--) {
				$crc = ($crc >> 1) ^ ($crc & 0x0001) * 0xA001;
			}
		}

		return pack('v1', $crc);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	private function readDigitalRegisters(
		Types\ModbusFunction $functionCode,
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false,
	): string|Messages\Response\ReadDigitalInputs
	{
		$request = pack('C2n2', $station, $functionCode->value, $startingAddress, $quantity);
		// Append CRC check
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if ($raw === false) {
			$header = unpack('C1station/C1function/C1count', $response);

			if ($header === false) {
				throw new Exceptions\ModbusRtu('Response header could not be parsed');
			}

			$registersUnpacked = unpack('C*', substr($response, 3, -2));

			if ($registersUnpacked === false) {
				throw new Exceptions\ModbusRtu('Response data could not be parsed');
			}

			$bits = array_map(
				static fn (int $byte): array => array_map(
					static fn (string $bit): bool => $bit === '1',
					str_split(substr(strrev(str_repeat('0', 8) . decbin($byte)), 0, 8)),
				),
				array_values($registersUnpacked),
			);
			$bits = array_merge(...$bits);

			$addresses = array_fill($startingAddress, count($bits), 'value');

			return new Messages\Response\ReadDigitalInputs(
				$header['station'],
				$functionCode,
				$header['count'],
				array_combine(array_keys($addresses), array_values($bits)),
			);
		}

		return $response;
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\ModbusRtu
	 */
	private function readAnalogRegisters(
		Types\ModbusFunction $functionCode,
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false,
	): string|Messages\Response\ReadAnalogInputs
	{
		$request = pack('C2n2', $station, $functionCode->value, $startingAddress, $quantity);
		// Append CRC check
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if ($raw === false) {
			$header = unpack('C1station/C1function/C1count', $response);

			if ($header === false) {
				throw new Exceptions\ModbusRtu('Response header could not be parsed');
			}

			$registersUnpacked = unpack('C*', substr($response, 3, -2));

			if ($registersUnpacked === false) {
				throw new Exceptions\ModbusRtu('Response data could not be parsed');
			}

			return new Messages\Response\ReadAnalogInputs(
				$header['station'],
				$functionCode,
				$header['count'],
				$registersUnpacked,
			);
		}

		return $response;
	}

}
