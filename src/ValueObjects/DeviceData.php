<?php declare(strict_types = 1);

/**
 * DeviceData.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     ValueObjects
 * @since          0.34.0
 *
 * @date           20.08.22
 */

namespace FastyBird\ModbusConnector\ValueObjects;

use FastyBird\Metadata\Types as MetadataTypes;
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

	/** @var MetadataTypes\DataTypeType */
	private MetadataTypes\DataTypeType $dataType;

	/** @var string|int|float|bool */
	private string|int|float|bool $value;

	/**
	 * @param string|int|float|bool $value
	 * @param MetadataTypes\DataTypeType|null $dataType
	 */
	public function __construct(
		string|int|float|bool $value,
		?MetadataTypes\DataTypeType $dataType
	) {
		$this->value = $value;
		$this->dataType = $dataType ?: MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING);
	}

	/**
	 * @return float|bool|int|string
	 */
	public function getValue(): float|bool|int|string
	{
		return $this->value;
	}

	/**
	 * @return MetadataTypes\DataTypeType
	 */
	public function getDataType(): MetadataTypes\DataTypeType
	{
		return $this->dataType;
	}

}
