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

use Exception;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ModbusConnector\Clients;
use FastyBird\ModbusConnector\Exceptions;
use FastyBird\ModbusConnector\Helpers;
use FastyBird\ModbusConnector\Types;
use Nette\Utils;
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

	private const MODBUS_ADU = 'C1station/C1function/C*data/';
	private const MODBUS_ERROR = 'C1station/C1error/C1exception/';

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Interfaces\ISerial|null  */
	private ?Clients\Interfaces\ISerial $interface;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/** @var Helpers\ConnectorHelper */
	private Helpers\ConnectorHelper $connectorHelper;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Helpers\ConnectorHelper $connectorHelper
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Helpers\ConnectorHelper $connectorHelper,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;
		$this->connectorHelper = $connectorHelper;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConnected(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$configuration = new Clients\Interfaces\Configuration(
			Types\BaudRateType::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifierType::get(
					Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BAUD_RATE
				)
			)),
			Types\ByteSizeType::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifierType::get(
					Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_BYTE_SIZE
				)
			)),
			Types\StopBitsType::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifierType::get(
					Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_STOP_BITS
				)
			)),
			Types\ParityType::get($this->connectorHelper->getConfiguration(
				$this->connector->getId(),
				Types\ConnectorPropertyIdentifierType::get(
					Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_PARITY
				)
			)),
			false,
			false
		);

		$useDio = false;

		foreach (get_loaded_extensions() as $extension) {
			if (Utils\Strings::contains('dio', $extension)) {
				$useDio = true;

				break;
			}
		}

		if ($useDio) {
			$this->interface = new Clients\Interfaces\SerialDio(
				(string) $this->connectorHelper->getConfiguration(
					$this->connector->getId(),
					Types\ConnectorPropertyIdentifierType::get(
						Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_INTERFACE
					)
				),
				$configuration
			);

		} else {
			$this->interface = new Clients\Interfaces\SerialFile(
				(string) $this->connectorHelper->getConfiguration(
					$this->connector->getId(),
					Types\ConnectorPropertyIdentifierType::get(
						Types\ConnectorPropertyIdentifierType::IDENTIFIER_RTU_INTERFACE
					)
				),
				$configuration
			);
		}

		$this->interface->open();

		$this->eventLoop->addPeriodicTimer(
			3,
			function (): void {
				try {
					var_dump('READING...');
					var_dump($this->readHoldingRegisters(2, 1, 5));
				} catch (Exceptions\ModbusRtuException $ex) {
					var_dump($ex->getMessage());
				}
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		$this->interface?->close();
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
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of coils (n1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|array>|string|false
	 * [
	 *    'station'  => $station,
	 *    'function' => 0x01,
	 *    'count'    => $count,
	 *    'status'   => [],
	 * ]
	 *
	 * @throws Exception
	 */
	private function readCoils(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x01, $startingAddress, $quantity);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count', $response);

			if ($unpacked === false) {
				return false;
			}

			$statusUnpacked = unpack('C*', substr($response, 3, -2));

			if ($statusUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['status' => array_values($statusUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x02) Read Discrete Inputs
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Inputs (n1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|array>|string|false
	 * [
	 *    'station'  => $station,
	 *    'function' => 0x02,
	 *    'count'    => $count,
	 *    'status'   => [],
	 * ]
	 *
	 * @throws Exception
	 */
	private function readDiscreteInputs(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x02, $startingAddress, $quantity);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count', $response);

			if ($unpacked === false) {
				return false;
			}

			$statusUnpacked = unpack('C*', substr($response, 3, -2));

			if ($statusUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['status' => array_values($statusUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x03) Read Holding Registers
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Registers (n1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|array>|string|false
	 * [
	 *    'station'   => $station,
	 *    'function'  => 0x01,
	 *    'count'     => $count,
	 *    'registers' => [],
	 * ]
	 *
	 * @throws Exception
	 */
	private function readHoldingRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x03, $startingAddress, $quantity);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count', $response);

			if ($unpacked === false) {
				return false;
			}

			$registersUnpacked = unpack('n*', substr($response, 3, -2));

			if ($registersUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['registers' => array_values($registersUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x04) Read Input Registers
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Input Registers
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|array>|string|false
	 *
	 * @throws Exception
	 */
	private function readInputRegisters(
		int $station,
		int $startingAddress,
		int $quantity,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x04, $startingAddress, $quantity);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count', $response);

			if ($unpacked === false) {
				return false;
			}

			$registersUnpacked = unpack('n*', substr($response, 3, -2));

			if ($registersUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['registers' => array_values($registersUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x05) Write Single Coil
	 *
	 * @param int $station Station Address (C1)
	 * @param int $outputAddress Output Address (n1)
	 * @param int $value Output Value (n1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|float>|string|false
	 *
	 * @throws Exception
	 */
	private function writeSingleCoil(
		int $station,
		int $outputAddress,
		int $value,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x05, $outputAddress, $value);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$response = unpack('C1station/C1function/n1address/n1value', $response);
		}

		return $response;
	}

	/**
	 * (0x06) Write Single Register
	 *
	 * @param int $station Station Address (C1)
	 * @param int $registerAddress Register Address (n1)
	 * @param int $value Register Value (n1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int|float>|string|false
	 *
	 * @throws Exception
	 */
	private function writeSingleRegister(
		int $station,
		int $registerAddress,
		int $value,
		bool $raw = false
	): string|array|false {
		$request = pack('C2n2', $station, 0x06, $registerAddress, $value);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$response = unpack('C1station/C1function/n1address/n1value', $response);
		}

		return $response;
	}

	/**
	 * (0x07) Read Exception Status (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exception
	 */
	private function readExceptionStatus(
		int $station,
		bool $raw = false
	): string|array|false {
		$request = pack('C2', $station, 0x07);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$response = unpack('C1station/C1function/C1data', $response);
		}

		return $response;
	}

	/**
	 * (0x08) Diagnostics (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 * @param int $subFunction Sub-function (n1)
	 *
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function diagnostics(
		int $station,
		int $subFunction
	): string|false {
		if (func_num_args() < 3) {
			throw new Exceptions\ModbusRtuException('Incorrect number of arguments', -4);
		}

		$request = pack('C2n1', $station, 0x08, $subFunction);
		$request .= pack('n*', ...array_slice(func_get_args(), 2));

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$request .= $crc;

		return $this->sendRequest($request);
	}

	/**
	 * (0x0B) Get Comm Event Counter (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exception
	 */
	private function getCommEventCounter(
		int $station,
		bool $raw = false
	): string|array|false {
		$request = pack('C2', $station, 0x0B);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$response = unpack('C1station/C1function/n1status/n1eventcount', $response);
		}

		return $response;
	}

	/**
	 * (0x0C) Get Comm Event Log (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 * @param bool $raw
	 *
	 * @return Array<string, string|int>|string|false
	 *
	 * @throws Exception
	 */
	private function getCommEventLog(
		int $station,
		bool $raw = false
	): string|array|false {
		$request = pack('C2', $station, 0x0C);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		$response = $this->sendRequest($request);

		if ($response === false) {
			return false;
		}

		if ($raw === false) {
			$unpacked = unpack('C1station/C1function/C1count/n1status/n1eventcount/n1messagecount', $response);

			if ($unpacked === false) {
				return false;
			}

			$eventsUnpacked = unpack('C*', substr($response, 9, -2));

			if ($eventsUnpacked === false) {
				return false;
			}

			$response = $unpacked + ['events' => array_values($eventsUnpacked)];
		}

		return $response;
	}

	/**
	 * (0x0F) Write Multiple Coils
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Outputs (n1)
	 *
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function writeMultipleCoils(
		int $station,
		int $startingAddress,
		int $quantity
	): string|false {
		if (func_num_args() !== (3 + $quantity)) {
			throw new Exceptions\ModbusRtuException('Incorrect number of arguments', -4);
		}

		$request = pack('C2n2', $station, 0x0F, $startingAddress, $quantity);
		$request .= pack('C1C*', $quantity, ...array_slice(func_get_args(), 3));

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		return $this->sendRequest($request);
	}

	/**
	 * (0x10) Write Multiple registers
	 *
	 * @param int $station Station Address (C1)
	 * @param int $startingAddress Starting Address (n1)
	 * @param int $quantity Quantity of Registers (n1)
	 *
	 * Registers Value (n*)
	 *
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function writeMultipleRegisters(
		int $station,
		int $startingAddress,
		int $quantity
	): string|false {
		if (func_num_args() !== (3 + $quantity)) {
			throw new Exceptions\ModbusRtuException('Incorrect number of arguments', -4);
		}

		$request = pack('C2n2', $station, 0x10, $startingAddress, $quantity);
		$request .= pack('C1n*', 2 * $quantity, ...array_slice(func_get_args(), 3));

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		return $this->sendRequest($request);
	}

	/**
	 * (0x11) Report Server ID (Serial Line only)
	 *
	 * @param int $station Station Address (C1)
	 *
	 * @return string|false
	 *
	 * @throws Exception
	 */
	private function reportServerId(
		int $station = 0x00
	): string|false {
		$request = pack('C2', $station, 0x11);

		$crc = $this->crc16($request);

		if ($crc === false) {
			return false;
		}

		return $this->sendRequest($request);
	}

	/**
	 * @param string $request
	 *
	 * @return string|false
	 *
	 * @throws Exceptions\ModbusRtuException
	 */
	private function sendRequest(
		string $request
	): string|false {
		if ($this->interface === null) {
			throw new Exceptions\RuntimeException('Connection is not established');
		}

		$this->interface->send($request);

		usleep((int) (0.1 * 1000000));

		$response = $this->interface->read();

		if ($response === false) {
			return false;
		}

		if (strlen($response) < 4) {
			throw new Exceptions\ModbusRtuException('Response length too short', -1, $request, $response);
		}

		$aduRequest = unpack(self::MODBUS_ADU, $request);

		if ($aduRequest === false) {
			return false;
		}

		$aduResponse = unpack(self::MODBUS_ERROR, $response);

		if ($aduResponse === false) {
			return false;
		}

		if ($aduRequest['function'] !== $aduResponse['error']) {
			// Error code = Function code + 0x80
			if ($aduResponse['error'] === ($aduRequest['function'] + 0x80)) {
				throw new Exceptions\ModbusRtuException(null, $aduResponse['exception'], $request, $response);
			} else {
				throw new Exceptions\ModbusRtuException('Illegal error code', -3, $request, $response);
			}
		}

		if (substr($response, -2) !== $this->crc16(substr($response, 0, -2))) {
			throw new Exceptions\ModbusRtuException('Error check fails', -2, $request, $response);
		}

		return $response;
	}

	/**
	 * @param string $data
	 *
	 * @return string|false
	 */
	private function crc16(string $data): string|false
	{
		$crc = 0xFFFF;

		$bytes = unpack('C*', $data);

		if ($bytes === false) {
			return false;
		}

		foreach ($bytes as $byte) {
			$crc ^= $byte;

			for ($j = 8; $j; $j--) {
				$crc = ($crc >> 1) ^ (($crc & 0x0001) * 0xA001);
			}
		}

		return pack('v1', $crc);
	}

}
