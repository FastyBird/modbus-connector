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

namespace FastyBird\Connector\Modbus\Clients\Interfaces;

use FastyBird\Connector\Modbus\Exceptions;
use function php_uname;
use function preg_replace;

/**
 * Serial interface using file stream
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SerialFile extends Serial
{

	private SerialDarwin|SerialLinux|SerialWindows $serialFile;

	/**
	 * @throws Exceptions\InvalidState
	 */
	public function __construct(string $port, Configuration $configuration)
	{
		parent::__construct($port, $configuration);

		$osName = preg_replace('~^.*(Linux|Darwing|Windows).*$~', '$1', php_uname());

		$this->serialFile = match ($osName) {
			'Windows' => new SerialWindows($port, $configuration),
			'Darwing' => new SerialDarwin($port, $configuration),
			'Linux' => new SerialLinux($port, $configuration),
			default => throw new Exceptions\InvalidState('Unsupported operating system'),
		};
	}

	/**
	 * Binds a named resource, specified by setDevice, to a stream
	 *
	 * @param string $mode The mode parameter specifies the type of access you require to the stream (as `fopen()`)
	 *
	 * @throws Exceptions\InvalidState
	 */
	public function open(string $mode = 'r+b'): void
	{
		$this->serialFile->open($mode);
	}

	public function close(): void
	{
		$this->serialFile->close();
	}

	/**
	 * Writes data to the serial stream
	 *
	 * @return false|int Returns the number of bytes written, or `false` on error
	 */
	public function send(string $data): false|int
	{
		return $this->serialFile->send($data);
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
		return $this->serialFile->read($length, $offset);
	}

}
