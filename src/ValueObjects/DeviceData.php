<?php declare(strict_types = 1);

/**
 * DeviceData.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     ValueObjects
 * @since          1.0.0
 *
 * @date           20.08.22
 */

namespace FastyBird\Connector\Modbus\ValueObjects;

use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;

/**
 * Data to be sent to device
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     ValueObjects
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DeviceData
{

	use Nette\SmartObject;

	private MetadataTypes\DataType $dataType;

	public function __construct(
		private readonly string|int|float|bool $value,
		MetadataTypes\DataType|null $dataType,
	)
	{
		$this->dataType = $dataType ?? MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING);
	}

	public function getValue(): float|bool|int|string
	{
		return $this->value;
	}

	public function getDataType(): MetadataTypes\DataType
	{
		return $this->dataType;
	}

}
