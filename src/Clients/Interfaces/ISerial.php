<?php declare(strict_types = 1);

/**
 * SerialLinux.php
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

/**
 * Serial interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface ISerial
{

	/**
	 * Binds a named resource, specified by setDevice, to a stream
	 *
	 * @param string $mode The mode parameter specifies the type of access you require to the stream (as `fopen()`)
	 *
	 * @return void
	 */
	public function open(string $mode = 'r+b'): void;

	/**
	 * @return void
	 */
	public function close(): void;

	/**
	 * Writes data to the serial stream
	 *
	 * @param string $data
	 *
	 * @return false|int Returns the number of bytes written, or `false` on error
	 */
	public function send(string $data): false|int;

	/**
	 * Reads remainder of the serial stream into a string
	 *
	 * @param int $length The maximum bytes to read. Defaults to -1 (read all the remaining buffer)
	 * @param int $offset Seek to the specified offset before reading. If this number is negative,no seeking will occur and reading will start from the current position
	 *
	 * @return false|string Returns a received data or `false` on failure
	 */
	public function read(int $length = -1, int $offset = -1): false|string;

}
