<?php declare(strict_types = 1);

/**
 * ModbusConnector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           07.12.21
 */

namespace FastyBird\ModbusConnector\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use IPub\DoctrineCrud\Mapping\Annotation as IPubDoctrine;

/**
 * @ORM\Entity
 */
class ModbusConnector extends DevicesModuleEntities\Connectors\Connector implements IModbusConnector
{

	public const CONNECTOR_TYPE = 'modbus';

	/**
	 * @var string|null
	 * @IPubDoctrine\Crud(is="writable")
	 */
	protected ?string $interface = null;

	/**
	 * @var int|null
	 * @IPubDoctrine\Crud(is="writable")
	 */
	protected ?int $baudRate = null;

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDiscriminatorName(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getInterface(): string
	{
		$interface = $this->getParam('interface', '/dev/ttyAMA0');

		return $interface ?? '/dev/ttyAMA0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function setinterface(?string $interface): void
	{
		$this->setParam('interface', $interface);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBaudRate(): int
	{
		$baudRate = $this->getParam('baud_rate', 9600);

		return $baudRate === null ? 9600 : intval($baudRate);
	}

	/**
	 * {@inheritDoc}
	 */
	public function setBaudRate(?int $baudRate): void
	{
		$this->setParam('baud_rate', $baudRate);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'interface' => $this->getInterface(),
			'baud_rate' => $this->getBaudRate(),
		]);
	}

}
