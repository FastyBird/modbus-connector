<?php declare(strict_types = 1);

/**
 * SerialLinux.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\API\Interfaces;

use FastyBird\Connector\Modbus\Exceptions;
use function array_values;
use function boolval;
use function error_clear_last;
use function error_get_last;
use function exec;
use function fopen;
use function is_array;
use function is_resource;
use function mb_convert_encoding;
use function sprintf;
use function stream_set_blocking;

/**
 * Serial interface using Linux file stream
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SerialLinux extends Serial
{

	/**
	 * @throws Exceptions\InvalidState
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
				$error = new Exceptions\InvalidState($error['message'], 0);

				throw new Exceptions\InvalidState(
					sprintf('Unable to open the connection %s', $this->port),
					0,
					$error,
				);
			}

			throw new Exceptions\InvalidState(sprintf('Unable to open the connection %s', $this->port));
		}

		if (!stream_set_blocking($this->resource, false)) {
			throw new Exceptions\InvalidState('Setting blocking error');
		}
	}

	/**
	 * Sets and prepare the port for connection
	 *
	 * @throws Exceptions\InvalidState
	 */
	protected function setPortOptions(): void
	{
		$params = ['device' => $this->port] + $this->configuration->toArray();
		unset($params['is_canonical']);

		$paramsFormats = [
			'stop_bits' => [1 => '-cstopb', 2 => 'cstopb'],
			'parity' => [0 => '-parenb', 1 => 'parenb parodd', 2 => 'parenb -parodd'],
			'flow_control' => [0 => 'clocal -crtscts -ixon -ixoff', 1 => '-clocal -crtscts ixon ixoff'],
		];

		foreach ($paramsFormats as $param => $values) {
			$params[$param] = $values[$params[$param]];
		}

		$command = 'stty -F %s %s cs%s %s %s %s';
		$command = sprintf($command, ...array_values($params));

		$message = exec($command, $output, $resultCode);

		if (boolval($resultCode)) {
			throw new Exceptions\InvalidState(
				mb_convert_encoding((string) $message, 'UTF-8', 'ISO-8859-1'),
				$resultCode,
			);
		}
	}

}
