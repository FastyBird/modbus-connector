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
abstract class Serial implements ISerial
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
	 * {@inheritDoc}
	 */
	public function open(string $mode = 'r+b'): void
	{
		if (is_resource($this->resource)) {
			throw new Exceptions\InvalidStateException('The connection is already opened');
		}

		if (!preg_match('~^[raw]\+?b?$~', $mode)) {
			throw new Exceptions\InvalidStateException(sprintf('Invalid opening mode: %s. Use fopen() modes.', $mode));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function close(): void
	{
		if (is_resource($this->resource)) {
			if (!fclose($this->resource)) {
				throw new Exceptions\InvalidStateException(sprintf('Unable to close the connection %s', $this->port));
			}
		}

		$this->resource = null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function send(string $data): false|int
	{
		if (!is_resource($this->resource)) {
			throw new Exceptions\InvalidStateException('Connection must be opened to write it');
		}

		return fwrite($this->resource, $data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function read(int $length = -1, int $offset = -1): false|string
	{
		if (!is_resource($this->resource)) {
			throw new Exceptions\InvalidStateException('Connection must be opened to read it');
		}

		return stream_get_contents($this->resource, $length, $offset);
	}

}
