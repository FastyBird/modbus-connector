<?php declare(strict_types = 1);

/**
 * SerialDio.php
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
 * Serial interface using php-dio extension
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SerialDio extends Serial
{

	/**
	 * {@inheritDoc}
	 */
	public function open(string $mode = 'r+b'): void
	{
		parent::open($mode);

		error_clear_last();

		$this->resource = @dio_serial($this->port, $mode, $this->configuration->toArray());

		if (!is_resource($this->resource)) {
			$error = error_get_last();

			if (is_array($error)) {
				$error = new Exceptions\InvalidStateException($error['message'], 0);

				throw new Exceptions\InvalidStateException(
					sprintf('Unable to open the connection %s', $this->port),
					0,
					$error
				);
			}

			throw new Exceptions\InvalidStateException(sprintf('Unable to open the connection %s', $this->port));
		}

		if (!stream_set_blocking($this->resource, false)) {
			throw new Exceptions\InvalidStateException('Setting blocking error');
		}

		if (!stream_set_timeout($this->resource, 0, 2000)) {
			throw new Exceptions\InvalidStateException('Setting timeout error');
		}
	}

	/**
	 * Binds a named resource, specified by setDevice, to a raw stream
	 *
	 * @param string $mode The mode parameter specifies the type of access you require to the stream (as `fopen()`)
	 *
	 * @return void
	 */
	public function openRaw(string $mode = 'r+b'): void
	{
		parent::open($mode);

		error_clear_last();

		$this->resource = @dio_raw($this->port, $mode, $this->configuration->toArray());

		if (!is_resource($this->resource)) {
			$error = error_get_last();

			if (is_array($error)) {
				$error = new Exceptions\InvalidStateException($error['message'], 0);

				throw new Exceptions\InvalidStateException(
					sprintf('Unable to open the connection %s', $this->port),
					0,
					$error
				);
			}

			throw new Exceptions\InvalidStateException(sprintf('Unable to open the connection %s', $this->port));
		}
	}

}
