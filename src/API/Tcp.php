<?php declare(strict_types = 1);

/**
 * Tcp.php
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

use FastyBird\Connector\Modbus\API\Messages\Response\ReadAnalogInputs;
use FastyBird\Connector\Modbus\API\Messages\Response\ReadDigitalInputs;
use FastyBird\Connector\Modbus\API\Messages\Response\WriteCoil;
use FastyBird\Connector\Modbus\API\Messages\Response\WriteHoldingRegister;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Types;
use InvalidArgumentException;
use Nette;
use Random\RandomException;
use React\EventLoop;
use React\Promise;
use React\Socket;
use Throwable;
use function array_combine;
use function array_fill;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function current;
use function decbin;
use function pack;
use function random_int;
use function sprintf;
use function str_repeat;
use function str_split;
use function strlen;
use function strrev;
use function substr;
use function unpack;

/**
 * Modbus TCP API interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Tcp
{

	use Nette\SmartObject;

	private const MODBUS_ADU = 'n1transaction/n1protocol/n1length/C1station/C1function/C*data/';

	private const MODBUS_ERROR = 'n1transaction/n1protocol/n1length/C1station/C1error/C1exception/';

	private const PROTOCOL_ID = 0;

	private const MAX_TRANSACTION_ID = 0xFFFF; // 65535 as dec

	private const CONNECTION_TIMEOUT = 0.2;

	public function __construct(
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	/**
	 * (0x01) Read Coils
	 *
	 * @return Promise\PromiseInterface<ReadDigitalInputs>
	 *
	 * @throws InvalidArgumentException
	 * @throws RandomException
	 */
	public function readCoils(
		string $uri,
		int $station,
		int $startingAddress,
		int $quantity,
		int|null $transactionId = null,
		bool $raw = false,
	): Promise\PromiseInterface
	{
		return $this->readDigitalRegisters(
			$uri,
			Types\ModbusFunction::READ_COIL,
			$station,
			$startingAddress,
			$quantity,
			$transactionId,
			$raw,
		);
	}

	/**
	 * (0x02) Read Discrete Inputs
	 *
	 * @return Promise\PromiseInterface<ReadDigitalInputs>
	 *
	 * @throws InvalidArgumentException
	 * @throws RandomException
	 */
	public function readDiscreteInputs(
		string $uri,
		int $station,
		int $startingAddress,
		int $quantity,
		int|null $transactionId = null,
		bool $raw = false,
	): Promise\PromiseInterface
	{
		return $this->readDigitalRegisters(
			$uri,
			Types\ModbusFunction::READ_DISCRETE,
			$station,
			$startingAddress,
			$quantity,
			$transactionId,
			$raw,
		);
	}

	/**
	 * (0x03) Read Holding Registers
	 *
	 * @return Promise\PromiseInterface<ReadAnalogInputs>
	 *
	 * @throws InvalidArgumentException
	 * @throws RandomException
	 */
	public function readHoldingRegisters(
		string $uri,
		int $station,
		int $startingAddress,
		int $quantity,
		int|null $transactionId = null,
		bool $raw = false,
	): Promise\PromiseInterface
	{
		return $this->readAnalogRegisters(
			$uri,
			Types\ModbusFunction::READ_HOLDINGS_REGISTERS,
			$station,
			$startingAddress,
			$quantity,
			$transactionId,
			$raw,
		);
	}

	/**
	 * (0x04) Read Input Registers
	 *
	 * @return Promise\PromiseInterface<ReadAnalogInputs>
	 *
	 * @throws InvalidArgumentException
	 * @throws RandomException
	 */
	public function readInputRegisters(
		string $uri,
		int $station,
		int $startingAddress,
		int $quantity,
		int|null $transactionId = null,
		bool $raw = false,
	): Promise\PromiseInterface
	{
		return $this->readAnalogRegisters(
			$uri,
			Types\ModbusFunction::READ_INPUTS_REGISTERS,
			$station,
			$startingAddress,
			$quantity,
			$transactionId,
			$raw,
		);
	}

	/**
	 * (0x05) Write Single Coil
	 *
	 * @return Promise\PromiseInterface<WriteCoil>
	 *
	 * @throws InvalidArgumentException
	 * @throws RandomException
	 */
	public function writeSingleCoil(
		string $uri,
		int $station,
		int $coilAddress,
		bool $value,
		int|null $transactionId = null,
		bool $raw = false,
	): Promise\PromiseInterface
	{
		$functionCode = Types\ModbusFunction::WRITE_SINGLE_COIL;

		$deferred = new Promise\Deferred();

		if (!$this->validateTransactionId($transactionId)) {
			return Promise\reject(
				new Exceptions\InvalidArgument(sprintf('Transaction Id is out of range: %s', $transactionId)),
			);
		}

		$transactionId ??= random_int(1, self::MAX_TRANSACTION_ID);

		// Pack header (transform to binary)
		$request = pack(
			'n3C2n1',
			$transactionId,
			self::PROTOCOL_ID,
			6, // By default, for writing coil
			$station,
			$functionCode->value,
			$coilAddress,
		);
		// Pack value (transform to binary)
		$request .= pack('n1', $value ? 0xFF00 : 0x0000);

		$this->sendRequest($uri, $request)
			->then(static function (string $response) use ($deferred, $functionCode, $raw): void {
				if ($raw) {
					$deferred->resolve($response);

					return;
				}

				$header = unpack('n1transaction/n1protocol/n1length/C1station/C1function/n1address', $response);

				if ($header === false) {
					$deferred->reject(new Exceptions\ModbusTcp('Response header could not be parsed'));

					return;
				}

				$valueUnpacked = unpack('n1', substr($response, 10));

				if ($valueUnpacked === false) {
					$deferred->reject(new Exceptions\ModbusTcp('Response data could not be parsed'));

					return;
				}

				$deferred->resolve(new Messages\Response\WriteCoil(
					$header['station'],
					$functionCode,
					current($valueUnpacked) === 0xFF00,
				));
			})
			->catch(static function (Throwable $ex) use ($deferred): void {
				$deferred->reject($ex);
			});

		return $deferred->promise();
	}

	/**
	 * (0x06) Write Single Register
	 *
	 * @param array<int> $value
	 *
	 * @return Promise\PromiseInterface<WriteHoldingRegister>
	 *
	 * @throws InvalidArgumentException
	 */
	public function writeSingleHolding(
		string $uri,
		int $station,
		int $registerAddress,
		array $value,
		int|null $transactionId = null,
		bool $raw = false,
	): Promise\PromiseInterface
	{
		$deferred = new Promise\Deferred();

		if (!$this->validateTransactionId($transactionId)) {
			return Promise\reject(
				new Exceptions\InvalidArgument(sprintf('Transaction Id is out of range: %s', $transactionId)),
			);
		}

		$functionCode = count($value) === 2
			? Types\ModbusFunction::WRITE_SINGLE_HOLDING_REGISTER
			: Types\ModbusFunction::WRITE_MULTIPLE_HOLDINGS_REGISTERS;

		// Pack header (transform to binary)
		$request = pack(
			'n3C2n1',
			$transactionId,
			self::PROTOCOL_ID,
			6, // By default, for writing coil
			$station,
			$functionCode->value,
			$registerAddress,
		);

		if (count($value) === 2) {
			// Pack value (transform to binary)
			$request .= pack('C2', ...$value);

		} elseif (count($value) === 4) {
			$request .= pack('n1C1', 2, 4);
			// Pack value (transform to binary)
			$request .= pack('C4', ...$value);

		} else {
			return Promise\reject(new Exceptions\InvalidState('Value could not be converted to bytes'));
		}

		$this->sendRequest($uri, $request)
			->then(
				static function (string $response) use ($deferred, $functionCode, $raw): void {
					if ($raw) {
						$deferred->resolve($response);

						return;
					}

					$header = unpack('n1transaction/n1protocol/n1length/C1station/C1function/n1address', $response);

					if ($header === false) {
						$deferred->reject(new Exceptions\ModbusTcp('Response header could not be parsed'));

						return;
					}

					$deferred->resolve(new Messages\Response\WriteHoldingRegister(
						$header['station'],
						$functionCode,
					));
				},
			)
			->catch(static function (Throwable $ex) use ($deferred): void {
				$deferred->reject($ex);
			});

		return $deferred->promise();
	}

	/**
	 * @return Promise\PromiseInterface<ReadDigitalInputs>
	 *
	 * @throws InvalidArgumentException
	 * @throws RandomException
	 */
	private function readDigitalRegisters(
		string $uri,
		Types\ModbusFunction $functionCode,
		int $station,
		int $startingAddress,
		int $quantity,
		int|null $transactionId = null,
		bool $raw = false,
	): Promise\PromiseInterface
	{
		$deferred = new Promise\Deferred();

		if (!$this->validateTransactionId($transactionId)) {
			return Promise\reject(
				new Exceptions\InvalidArgument(sprintf('Transaction Id is out of range: %s', $transactionId)),
			);
		}

		$transactionId ??= random_int(1, self::MAX_TRANSACTION_ID);

		$request = pack(
			'n3C2n2',
			$transactionId,
			self::PROTOCOL_ID,
			6, // By default, for reading
			$station,
			$functionCode->value,
			$startingAddress,
			$quantity,
		);

		$this->sendRequest($uri, $request)
			->then(static function (string $response) use ($deferred, $functionCode, $startingAddress, $raw): void {
				if ($raw) {
					$deferred->resolve($response);

					return;
				}

				$header = unpack('n1transaction/n1protocol/n1length/C1station/C1function/C1count', $response);

				if ($header === false) {
					$deferred->reject(new Exceptions\ModbusTcp('Response header could not be parsed'));

					return;
				}

				$registersUnpacked = unpack('C*', substr($response, 9));

				if ($registersUnpacked === false) {
					$deferred->reject(new Exceptions\ModbusTcp('Response data could not be parsed'));

					return;
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

				$deferred->resolve(new Messages\Response\ReadDigitalInputs(
					$header['station'],
					$functionCode,
					$header['count'],
					array_combine(array_keys($addresses), array_values($bits)),
				));
			})
			->catch(static function (Throwable $ex) use ($deferred): void {
				$deferred->reject($ex);
			});

		return $deferred->promise();
	}

	/**
	 * @return Promise\PromiseInterface<ReadAnalogInputs>
	 *
	 * @throws InvalidArgumentException
	 * @throws RandomException
	 */
	private function readAnalogRegisters(
		string $uri,
		Types\ModbusFunction $functionCode,
		int $station,
		int $startingAddress,
		int $quantity,
		int|null $transactionId = null,
		bool $raw = false,
	): Promise\PromiseInterface
	{
		$deferred = new Promise\Deferred();

		if (!$this->validateTransactionId($transactionId)) {
			return Promise\reject(
				new Exceptions\InvalidArgument(sprintf('Transaction Id is out of range: %s', $transactionId)),
			);
		}

		$transactionId ??= random_int(1, self::MAX_TRANSACTION_ID);

		$request = pack(
			'n3C2n2',
			$transactionId,
			self::PROTOCOL_ID,
			6, // By default, for reading
			$station,
			$functionCode->value,
			$startingAddress,
			$quantity,
		);

		$this->sendRequest($uri, $request)
			->then(
				static function (string $response) use ($deferred, $functionCode, $raw): void {
					if ($raw) {
						$deferred->resolve($response);

						return;
					}

					$header = unpack('n1transaction/n1protocol/n1length/C1station/C1function/C1count', $response);

					if ($header === false) {
						$deferred->reject(new Exceptions\ModbusTcp('Response header could not be parsed'));

						return;
					}

					$registersUnpacked = unpack('C*', substr($response, 9));

					if ($registersUnpacked === false) {
						$deferred->reject(new Exceptions\ModbusTcp('Response data could not be parsed'));

						return;
					}

					$deferred->resolve(new Messages\Response\ReadAnalogInputs(
						$header['station'],
						$functionCode,
						$header['count'],
						$registersUnpacked,
					));
				},
			)
			->catch(static function (Throwable $ex) use ($deferred): void {
				$deferred->reject($ex);
			});

		return $deferred->promise();
	}

	/**
	 * @return Promise\PromiseInterface<string>
	 *
	 * @throws InvalidArgumentException
	 */
	private function sendRequest(
		string $uri,
		string $request,
	): Promise\PromiseInterface
	{
		$deferred = new Promise\Deferred();

		$connector = new Socket\Connector($this->eventLoop, [
			'dns' => false,
			'timeout' => self::CONNECTION_TIMEOUT,
		]);

		$connector->connect($uri)
			->then(function (Socket\ConnectionInterface $connection) use ($request, $deferred): void {
				$response = '';

				$connection->write($request);

				$timeout = $this->eventLoop->addTimer(1.0, static function () use ($deferred): void {
					$deferred->reject(new Exceptions\InvalidState('Device did not send response in time'));
				});

				// Wait for response event
				$connection->on(
					'data',
					function ($data) use ($connection, $deferred, $request, &$response, $timeout): void {
						// There are rare cases when MODBUS packet is received by multiple fragmented TCP packets, and it could
						// take PHP multiple reads from stream to get full packet. So we concatenate data and check if all that
						// we have received makes a complete modbus packet.
						$response .= $data;

						try {
							if (!$this->isCompleteLength($response)) {
								return;
							}
						} catch (Throwable $ex) {
							$connection->end();

							$deferred->reject($ex);

							$this->eventLoop->cancelTimer($timeout);

							return;
						}

						$connection->end();

						if (strlen($response) < 8) {
							$deferred->reject(
								new Exceptions\ModbusTcp('Response length too short', -1, $request, $response),
							);

							$this->eventLoop->cancelTimer($timeout);

							return;
						}

						$aduRequest = unpack(self::MODBUS_ADU, $request);

						if ($aduRequest === false) {
							$deferred->reject(new Exceptions\ModbusTcp('ADU could not be extracted from response'));

							$this->eventLoop->cancelTimer($timeout);

							return;
						}

						$aduResponse = unpack(self::MODBUS_ERROR, $response);

						if ($aduResponse === false) {
							$deferred->reject(new Exceptions\ModbusTcp('Error could not be extracted from response'));

							$this->eventLoop->cancelTimer($timeout);

							return;
						}

						if ($aduRequest['function'] !== $aduResponse['error']) {
							// Error code = Function code + 0x80
							if ($aduResponse['error'] === $aduRequest['function'] + 0x80) {
								$deferred->reject(
									new Exceptions\ModbusTcp(null, $aduResponse['exception'], $request, $response),
								);
							} else {
								$deferred->reject(
									new Exceptions\ModbusTcp('Illegal error code', -3, $request, $response),
								);
							}

							$this->eventLoop->cancelTimer($timeout);

							return;
						}

						$deferred->resolve($response);

						$this->eventLoop->cancelTimer($timeout);
					},
				);

				$connection->on('error', function (Throwable $ex) use ($connection, $deferred, $timeout): void {
					$connection->end();

					$deferred->reject($ex);

					$this->eventLoop->cancelTimer($timeout);
				});
			})
			->catch(static function (Throwable $ex) use ($deferred): void {
				$deferred->reject($ex);
			});

		return $deferred->promise();
	}

	/**
	 * @throws Exceptions\ModbusTcp
	 */
	private function isCompleteLength(string|null $binaryData): bool
	{
		if ($binaryData === null) {
			return false;
		}

		// Minimal amount is 9 bytes (header + function code + 1 byte of something ala error code)
		$length = strlen($binaryData);

		if ($length < 9) {
			return false;
		}

		$unpacked = unpack('n', $binaryData[4] . $binaryData[5]);

		if ($unpacked === false) {
			throw new Exceptions\ModbusTcp('Received packet could not be decoded');
		}

		// Modbus header 6 bytes are = transaction id + protocol id + length of PDU part
		// so adding these number is what complete packet would be
		$expectedLength = 6 + $unpacked[1];

		if ($length > $expectedLength) {
			throw new Exceptions\ModbusTcp('Received packet length has more bytes than expected');
		}

		return $length === $expectedLength;
	}

	private function validateTransactionId(int|null $transactionId): bool
	{
		return $transactionId === null || ($transactionId >= 0 && $transactionId <= self::MAX_TRANSACTION_ID);
	}

}
