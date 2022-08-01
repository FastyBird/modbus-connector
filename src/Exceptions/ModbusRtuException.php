<?php declare(strict_types = 1);

/**
 * ModbusRtuException.php
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

use Exception;
use Throwable;

/**
 * Modbus RTU communication exception
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Exceptions
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ModbusRtuException extends Exception implements IException
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

	/** @var Throwable|null */
	private ?Throwable $previous;

	/** @var string|null */
	protected ?string $request = null;

	/** @var string|null */
	protected ?string $response = null;

	/**
	 * @param string|null $message
	 * @param int $code
	 * @param string|null $request
	 * @param string|null $response
	 * @param Throwable|null $previous
	 */
	public function __construct(
		?string $message = null,
		int $code = 0,
		?string $request = null,
		?string $response = null,
		?Throwable $previous = null
	) {
		$this->previous = $previous;
		$this->request = $request !== null ? bin2hex($request) : null;
		$this->response = $response !== null ? bin2hex($response) : null;

		if (($message === null || $message === '') && $code !== 0) {
			$message = empty(self::EXCEPTION_CODES[$code]) ? self::EXCEPTION_CODES[0x00] : self::EXCEPTION_CODES[$code];
		}

		parent::__construct($message ?? '', $code, $previous);
	}

	/**
	 * @return string|null
	 */
	public function getRequest(): ?string
	{
		return $this->request;
	}

	/**
	 * @return string|null
	 */
	public function getResponse(): ?string
	{
		return $this->response;
	}

	/**
	 * @return string
	 */
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
			$this->line
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
