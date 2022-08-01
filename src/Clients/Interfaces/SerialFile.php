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

use FastyBird\ModbusConnector\Exceptions;

/**
 * Serial interface using file stream
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SerialFile implements ISerial
{

	/** @var SerialDarwin|SerialLinux|SerialWindows */
	private SerialDarwin|SerialLinux|SerialWindows $serialFile;

	/**
	 * @param string $port
	 * @param Configuration $configuration
	 */
	public function __construct(
		string $port,
		Configuration $configuration
	) {
		$osName = preg_replace('~^.*(Linux|Darwing|Windows).*$~', '$1', php_uname());

		$this->serialFile = match ($osName) {
			'Windows' => new SerialWindows($port, $configuration),
			'Darwing' => new SerialDarwin($port, $configuration),
			'Linux' => new SerialLinux($port, $configuration),
			default => throw new Exceptions\InvalidStateException('Unsupported operating system'),
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function open(string $mode = 'r+b'): void
	{
		$this->serialFile->open($mode);
	}

	/**
	 * {@inheritDoc}
	 */
	public function close(): void
	{
		$this->serialFile->close();
	}

	/**
	 * {@inheritDoc}
	 */
	public function send(string $data): false|int
	{
		return $this->serialFile->send($data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function read(int $length = -1, int $offset = -1): false|string
	{
		return $this->serialFile->read($length, $offset);
	}

}
