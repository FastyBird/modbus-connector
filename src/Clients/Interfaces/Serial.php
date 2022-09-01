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

use FastyBird\ModbusConnector\Exceptions;

/**
 * Base serial interface
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Serial
{

	/** @var mixed */
	protected mixed $resource = null;

	/** @var string */
	protected string $port;

	/** @var Configuration */
	protected Configuration $configuration;

	/**
	 * @param string $port
	 * @param Configuration $configuration
	 */
	public function __construct(
		string $port,
		Configuration $configuration
	) {
		$this->port = $port;
		$this->configuration = $configuration;

		register_shutdown_function([$this, 'close']);
	}

	/**
	 * Binds a named resource, specified by setDevice, to a stream
	 *
	 * @param string $mode The mode parameter specifies the type of access you require to the stream (as `fopen()`)
	 *
	 * @return void
	 */
	public function open(string $mode = 'r+b'): void
	{
		if (is_resource($this->resource)) {
			throw new Exceptions\InvalidState('The connection is already opened');
		}

		if (!preg_match('~^[raw]\+?b?$~', $mode)) {
			throw new Exceptions\InvalidState(sprintf('Invalid opening mode: %s. Use fopen() modes.', $mode));
		}
	}

	/**
	 * @return void
	 */
	public function close(): void
	{
		if (is_resource($this->resource)) {
			if (!fclose($this->resource)) {
				throw new Exceptions\InvalidState(sprintf('Unable to close the connection %s', $this->port));
			}
		}

		$this->resource = null;
	}

	/**
	 * Writes data to the serial stream
	 *
	 * @param string $data
	 *
	 * @return false|int Returns the number of bytes written, or `false` on error
	 */
	public function send(string $data): false|int
	{
		if (!is_resource($this->resource)) {
			throw new Exceptions\InvalidState('Connection must be opened to write it');
		}

		return fwrite($this->resource, $data);
	}

	/**
	 * Reads remainder of the serial stream into a string
	 *
	 * @param int $length The maximum bytes to read. Defaults to -1 (read all the remaining buffer)
	 * @param int $offset Seek to the specified offset before reading. If this number is negative,no seeking will occur and reading will start from the current position
	 *
	 * @return false|string Returns a received data or `false` on failure
	 */
	public function read(int $length = -1, int $offset = -1): false|string
	{
		if (!is_resource($this->resource)) {
			throw new Exceptions\InvalidState('Connection must be opened to read it');
		}

		return stream_get_contents($this->resource, $length, $offset);
	}

}
