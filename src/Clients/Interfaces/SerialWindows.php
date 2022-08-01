<?php declare(strict_types = 1);

/**
 * SerialWindows.php
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
 * Serial interface using Windows file stream
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SerialWindows extends Serial
{

	/**
	 * {@inheritDoc}
	 */
	public function open(string $mode = 'r+b'): void
	{
		parent::open($mode);

		$this->setPortOptions();

		error_clear_last();

		$this->resource = @fopen($this->port, $mode);

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
	}

	/**
	 * Sets and prepare the port for connection
	 *
	 * @return void
	 */
	protected function setPortOptions(): void
	{
		$params = ['device' => $this->port] + $this->configuration->toArray();
		unset($params['is_canonical']);

		$paramsFormats = [
			'parity'       => [0 => 'n', 1 => 'o', 2 => 'e'],
			'flow_control' => [0 => 'off', 1 => 'on'],
		];

		foreach ($paramsFormats as $param => $values) {
			$params[$param] = $values[$params[$param]];
		}

		$command = 'mode %s baud=%s data=%s stop=%s parity=%s xon=%s';
		$command = sprintf($command, ...array_values($params));

		$message = exec($command, $output, $resultCode);

		if ($resultCode) {
			throw new Exceptions\InvalidStateException(utf8_encode(strval($message)), $resultCode);
		}
	}

}
