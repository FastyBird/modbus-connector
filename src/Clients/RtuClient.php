<?php declare(strict_types = 1);

/**
 * IClient.php
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

use FastyBird\Metadata\Entities as MetadataEntities;
use Fawno\PhpSerial;
use Psr\Log;
use React\EventLoop;

/**
 * Modbus RTU devices client interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class RtuClient extends Client
{

	public const MODBUS_ADU = 'C1station/C1function/C*data/';
	public const MODBUS_ERROR = 'C1station/C1error/C1exception/';

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var PhpSerial\SerialDio */
	private PhpSerial\SerialDio $port;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 *
	 * @throws PhpSerial\SerialException
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$config = new PhpSerial\SerialConfig();
		$config->setBaudRate(PhpSerial\Config\BaudRates::B9600);
		$config->setDataBits(PhpSerial\Config\DataBits::CS8);
		$config->setStopBits(PhpSerial\Config\StopBits::ONE);
		$config->setParity(PhpSerial\Config\Parity::NONE);
		$config->setFlowControl(false);
		$config->setCanonical(false);

		$this->port = new PhpSerial\SerialDio('/dev/ttyAMA0', $config);

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function isConnected(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws PhpSerial\SerialException
	 */
	public function connect(): void
	{
		$this->port->open('r+b');

		$this->port->setBlocking(false);
		$this->port->setTimeout(0, 100000);

		$this->eventLoop->addTimer(
			1,
			function (): void {
				var_dump('READING...');
				var_dump($this->readHoldingRegisters(1, 0, 20));
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws PhpSerial\SerialException
	 */
	public function disconnect(): void
	{
		$this->port->close();
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	/**
	 * @param $data
	 *
	 * @return string|bool
	 */
	public function crc16($data): string|bool
	{
		$crc = 0xFFFF;

		foreach (unpack('C*', $data) as $byte) {
			$crc ^= $byte;

			for ($j = 8; $j; $j--) {
				$crc = ($crc >> 1) ^ (($crc & 0x0001) * 0xA001);
			}
		}

		return pack('v1', $crc);
	}

	/**
	 * (0x01) Read Coils
	 *
	 * This function code is used to read from 1 to 2000 contiguous status of coils in a remote device. The Request PDU specifies the starting address, i.e. the address of the first coil specified, and the number of coils. In the PDU Coils are addressed starting at zero. Therefore coils numbered 1-16 are addressed as 0-15.
	 *
	 * The coils in the response message are packed as one coil per bit of the data field. Status is indicated as 1= ON and 0= OFF. The LSB of the first data byte contains the output addressed in the query. The other coils follow toward the high order end of this byte, and from low order to high order in subsequent bytes.
	 *
	 * If the returned output quantity is not a multiple of eight, the remaining bits in the final data byte will be padded with zeros (toward the high order end of the byte). The Byte Count field specifies the quantity of complete bytes of data.
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $startingAddress
	 * Starting Address (n1)
	 *
	 * @param int $quantity
	 * Quantity of coils (n1)
	 *
	 * @param bool $raw
	 *
	 * @return mixed
	 * [
	 *    'station' => $station,
	 *    'function' => 0x01,
	 *    'count' => $count,
	 *    'status' => [],
	 * ]
	 *
	 * @throws Exception
	 */
	public function readCoils(int $station, int $startingAddress, int $quantity, bool $raw = false)
	{
		$request = pack('C2n2', $station, 0x01, $startingAddress, $quantity);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/C1count', $response) + ['status' => array_values(unpack('C*', substr($response, 3, -2)))];
		}

		return $response;
	}

	/**
	 * (0x02) Read Discrete Inputs
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $startingAddress
	 * Starting Address (n1)
	 *
	 * @param int $quantity
	 * Quantity of Inputs (n1)
	 *
	 * @param bool $raw
	 *
	 * @return mixed
	 * [
	 *    'station' => $station,
	 *    'function' => 0x02,
	 *    'count' => $count,
	 *    'status' => [],
	 * ]
	 *
	 * @throws Exception
	 */
	public function readDiscreteInputs(int $station, int $startingAddress, int $quantity, bool $raw = false)
	{
		$request = pack('C2n2', $station, 0x02, $startingAddress, $quantity);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/C1count', $response) + ['status' => array_values(unpack('C*', substr($response, 3, -2)))];
		}

		return $response;
	}

	/**
	 * (0x03) Read Holding Registers
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $startingAddress
	 * Starting Address (n1)
	 *
	 * @param int $quantity
	 * Quantity of Registers (n1)
	 *
	 * @param bool $raw
	 *
	 * @return mixed
	 * [
	 *    'station' => $station,
	 *    'function' => 0x01,
	 *    'count' => $count,
	 *    'registers' => [],
	 * ]
	 *
	 * @throws Exception
	 */
	public function readHoldingRegisters(int $station, int $startingAddress, int $quantity, bool $raw = false)
	{
		$request = pack('C2n2', $station, 0x03, $startingAddress, $quantity);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/C1count', $response) + ['registers' => array_values(unpack('n*', substr($response, 3, -2)))];
		}

		return $response;
	}

	/**
	 * (0x04) Read Input Registers
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $startingAddress
	 * Starting Address (n1)
	 *
	 * @param int $quantity
	 * Quantity of Input Registers
	 *
	 * @param bool $raw
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function readInputRegisters(int $station, int $startingAddress, int $quantity, bool $raw = false)
	{
		$request = pack('C2n2', $station, 0x04, $startingAddress, $quantity);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/C1count', $response) + ['registers' => array_values(unpack('n*', substr($response, 3, -2)))];
		}

		return $response;
	}

	/**
	 * (0x05) Write Single Coil
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $output_address
	 * Output Address (n1)
	 *
	 * @param int $value
	 * Output Value (n1)
	 *
	 * @param bool $raw
	 *
	 * @return string|false|array
	 * @throws Exception
	 */
	public function writeSingleCoil(int $station, int $output_address, int $value, bool $raw = false)
	{
		$request = pack('C2n2', $station, 0x05, $output_address, $value);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/n1address/n1value', $response);
		}

		return $response;
	}

	/**
	 * (0x06) Write Single Register
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $register_address
	 * Register Address (n1)
	 *
	 * @param int $value
	 * Register Value (n1)
	 *
	 * @param bool $raw
	 *
	 * @return string|false|array
	 * @throws Exception
	 */
	public function writeSingleRegister(int $station, int $register_address, int $value, bool $raw = false)
	{
		$request = pack('C2n2', $station, 0x06, $register_address, $value);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/n1address/n1value', $response);
		}

		return $response;
	}

	/**
	 * (0x07) Read Exception Status (Serial Line only)
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param bool $raw
	 *
	 * @return string|false|array
	 * @throws Exception
	 */
	public function readExceptionStatus(int $station, bool $raw = false)
	{
		$request = pack('C2', $station, 0x07);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/C1data', $response);
		}

		return $response;
	}

	/**
	 * (0x08) Diagnostics (Serial Line only)
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $subfunction
	 * Sub-function (n1)
	 *
	 * @return string|false
	 * @throws ModbusException
	 */
	public function diagnostics(int $station, int $subfunction)
	{
		if (func_num_args() < 3) {
			throw new ModbusException('Incorrect number of arguments', -4);
		}

		$request = pack('C2n1', $station, 0x08, $subfunction);
		$request .= pack('n*', ...array_slice(func_get_args(), 2));
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		return $response;
	}

	/**
	 * (0x0B) Get Comm Event Counter (Serial Line only)
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param bool $raw
	 *
	 * @return string|false|array
	 * @throws Exception
	 */
	public function getCommEventCounter(int $station, bool $raw = false)
	{
		$request = pack('C2', $station, 0x0B);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/n1status/n1eventcount', $response);
		}

		return $response;
	}

	/**
	 * (0x0C) Get Comm Event Log (Serial Line only)
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param bool $raw
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function getCommEventLog(int $station, bool $raw = false)
	{
		$request = pack('C2', $station, 0x0C);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		if (!$raw) {
			$response = unpack('C1station/C1function/C1count/n1status/n1eventcount/n1messagecount', $response) + ['events' => array_values(unpack('C*', substr($response, 9, -2)))];
		}

		return $response;
	}

	/**
	 * (0x0F) Write Multiple Coils
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $startingAddress
	 * Starting Address (n1)
	 *
	 * @param int $quantity
	 * Quantity of Outputs (n1)
	 *
	 * @return string|false
	 * @throws ModbusException
	 */
	public function writeMultipleCoils(int $station, int $startingAddress, int $quantity)
	{
		if (func_num_args() != (3 + $quantity)) {
			throw new ModbusException('Incorrect number of arguments', -4);
		}

		$request = pack('C2n2', $station, 0x0F, $startingAddress, $quantity);
		$request .= pack('C1C*', $quantity, ...array_slice(func_get_args(), 3));
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		return $response;
	}

	/**
	 * (0x10) Write Multiple registers
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @param int $startingAddress
	 * Starting Address (n1)
	 *
	 * @param int $quantity
	 * Quantity of Registers (n1)
	 *
	 * Registers Value (n*)
	 *
	 * @return string|false
	 * @throws ModbusException
	 */
	public function writeMultipleRegisters(int $station, int $startingAddress, int $quantity)
	{
		if (func_num_args() != (3 + $quantity)) {
			throw new ModbusException('Incorrect number of arguments', -4);
		}

		$request = pack('C2n2', $station, 0x10, $startingAddress, $quantity);
		$request .= pack('C1n*', 2 * $quantity, ...array_slice(func_get_args(), 3));
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		return $response;
	}

	/**
	 * (0x11) Report Server ID (Serial Line only)
	 *
	 * @param int $station
	 * Station Address (C1)
	 *
	 * @return string|false
	 * @throws Exception
	 */
	public function reportServerID(int $station = 0x00)
	{
		$request = pack('C2', $station, 0x11);
		$request .= $this->crc16($request);

		$response = $this->sendRequest($request);

		return $response;
	}

	/**
	 * @param string $request
	 *
	 * @return string|false
	 * @throws ModbusException
	 */
	public function sendRequest(string $request)
	{
		$this->port->send($request);
		$response = $this->port->read();

		if (strlen($response) < 4) {
			throw new ModbusException('Response lenght too short', -1, $request, $response);
		}

		$adu_request = unpack(self::MODBUS_ADU, $request);
		$adu_response = unpack(self::MODBUS_ERROR, $response);
		if ($adu_request['function'] != $adu_response['error']) {
			// Error code = Function code + 0x80
			if ($adu_response['error'] == ($adu_request['function'] + 0x80)) {
				throw new ModbusException(null, $adu_response['exception'], $request, $response);
			} else {
				throw new ModbusException('Illegal error code', -3, $request, $response);
			}
		}

		if (substr($response, -2) != $this->crc16(substr($response, 0, -2))) {
			throw new ModbusException('Error check fails', -2, $request, $response);
		}

		return $response;
	}

}
