<?php declare(strict_types = 1);

/**
 * ModbusRtu.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Exceptions
 * @since          0.34.0
 *
 * @date           31.07.22
 */

namespace FastyBird\ModbusConnector\Exceptions;

use Exception as PhpException;
use Throwable;
use function array_key_exists;
use function bin2hex;
use function sprintf;

/**
 * Modbus RTU communication exception
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Exceptions
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ModbusRtu extends PhpException implements Exception
{

	public const EXCEPTION_CODES = [
		0x00 => 'Undefined failure code',
		0x01 => 'Illegal function',
		0x02 => 'Illegal data address',
		0x03 => 'Illegal data value',
		0x04 => 'Server device failure',
		0x05 => 'Acknowledge',
		0x06 => 'Server device busy',
		0x08 => 'Memory parity error',
		0x0A => 'Gateway path unavailable',
		0x0B => 'Gateway target device failed to respond',
	];

	protected string|null $request = null;

	protected string|null $response = null;

	public function __construct(
		string|null $message = null,
		int $code = 0,
		string|null $request = null,
		string|null $response = null,
		private readonly Throwable|null $previous = null,
	)
	{
		$this->request = $request !== null ? bin2hex($request) : null;
		$this->response = $response !== null ? bin2hex($response) : null;

		if ($message === null && $code !== 0) {
			$message = array_key_exists($code, self::EXCEPTION_CODES)
				? self::EXCEPTION_CODES[$code]
				: self::EXCEPTION_CODES[0x00];
		}

		parent::__construct($message ?? '', $code, $previous);
	}

	public function getRequest(): string|null
	{
		return $this->request;
	}

	public function getResponse(): string|null
	{
		return $this->response;
	}

	public function __toString(): string
	{
		$output = '';

		if ($this->previous) {
			$output .= $this->previous . "\n" . 'Next ';
		}

		$output .= sprintf(
			'%s: %s in %s:%s',
			self::class,
			$this->message,
			$this->file,
			$this->line,
		) . "\n";

		if ($this->request !== null) {
			$output .= 'Request: "' . $this->request . '"' . "\n";
		}

		if ($this->response !== null) {
			$output .= 'Response: "' . $this->response . '"' . "\n";
		}

		$trace = $this->getTraceAsString();
		if ($trace) {
			$output .= 'Stack trace:' . "\n" . $trace . "\n";
		}

		return $output;
	}

}
