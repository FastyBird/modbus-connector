<?php declare(strict_types = 1);

/**
 * Transformer.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 * @since          0.34.0
 *
 * @date           02.08.22
 */

namespace FastyBird\Connector\Modbus\API;

use DateTimeInterface;
use FastyBird\Connector\Modbus\ValueObjects;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\ValueObjects as MetadataValueObjects;
use Nette;
use Nette\Utils;
use function array_filter;
use function array_unique;
use function array_values;
use function boolval;
use function count;
use function floatval;
use function in_array;
use function intval;
use function is_bool;
use function is_numeric;
use function is_scalar;
use function strval;

/**
 * Value transformers
 *
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Transformer
{

	use Nette\SmartObject;

	/**
	 * @throws MetadataExceptions\InvalidState
	 */
	public function transformValueFromDevice(
		MetadataTypes\DataType $dataType,
		MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|null $format,
		string|int|float|bool|null $value,
	): float|int|string|bool|MetadataTypes\SwitchPayload|MetadataTypes\ButtonPayload|null
	{
		if ($value === null) {
			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
			return is_bool($value) ? $value : boolval($value);
		}

		if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
			$floatValue = floatval($value);

			if ($format instanceof MetadataValueObjects\NumberRangeFormat) {
				if ($format->getMin() !== null && $format->getMin() >= $floatValue) {
					return null;
				}

				if ($format->getMax() !== null && $format->getMax() <= $floatValue) {
					return null;
				}
			}

			return $floatValue;
		}

		if (
			$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
		) {
			$intValue = intval($value);

			if ($format instanceof MetadataValueObjects\NumberRangeFormat) {
				if ($format->getMin() !== null && $format->getMin() >= $intValue) {
					return null;
				}

				if ($format->getMax() !== null && $format->getMax() <= $intValue) {
					return null;
				}
			}

			return $intValue;
		}

		if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_STRING)) {
			return strval($value);
		}

		if (
			$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_ENUM)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
		) {
			if ($format instanceof MetadataValueObjects\StringEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					static fn (string $item): bool => Utils\Strings::lower(strval($value)) === $item,
				));

				if (count($filtered) === 1) {
					if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)) {
						return MetadataTypes\SwitchPayload::isValidValue(strval($value))
							? MetadataTypes\SwitchPayload::get(
								strval($value),
							)
							: null;
					} elseif ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)) {
						return MetadataTypes\ButtonPayload::isValidValue(strval($value))
							? MetadataTypes\ButtonPayload::get(
								strval($value),
							)
							: null;
					} else {
						return strval($value);
					}
				}

				return null;
			} elseif ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					static fn (array $item): bool => $item[1] !== null
							&& Utils\Strings::lower(strval($item[1]->getValue())) === Utils\Strings::lower(
								strval($value),
							),
				));

				if (
					count($filtered) === 1
					&& $filtered[0][0] instanceof MetadataValueObjects\CombinedEnumFormatItem
				) {
					if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)) {
						return MetadataTypes\SwitchPayload::isValidValue(strval($filtered[0][0]->getValue()))
							? MetadataTypes\SwitchPayload::get(
								strval($filtered[0][0]->getValue()),
							)
							: null;
					} elseif ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)) {
						return MetadataTypes\ButtonPayload::isValidValue(strval($filtered[0][0]->getValue()))
							? MetadataTypes\ButtonPayload::get(
								strval($filtered[0][0]->getValue()),
							)
							: null;
					} else {
						return strval($filtered[0][0]->getValue());
					}
				}

				return null;
			}
		}

		return null;
	}

	/**
	 * @throws MetadataExceptions\InvalidState
	 */
	public function transformValueToDevice(
		MetadataTypes\DataType $dataType,
		MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|null $format,
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayload|MetadataTypes\SwitchPayload|null $value,
	): ValueObjects\DeviceData|null
	{
		if ($value === null) {
			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
			if (is_bool($value)) {
				return new ValueObjects\DeviceData($value, $dataType);
			}

			if (is_numeric($value) && in_array((int) $value, [0, 1], true)) {
				return new ValueObjects\DeviceData(
					(int) $value === 1,
					$dataType,
				);
			}

			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
			if (is_numeric($value)) {
				return new ValueObjects\DeviceData((float) $value, $dataType);
			}

			return null;
		}

		if (
			$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
		) {
			if (is_numeric($value)) {
				return new ValueObjects\DeviceData((int) $value, $dataType);
			}

			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_STRING)) {
			return new ValueObjects\DeviceData(
				$value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : (string) $value,
				$dataType,
			);
		}

		if (
			$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_ENUM)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
			|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
		) {
			if ($format instanceof MetadataValueObjects\StringEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					static fn (string $item): bool => Utils\Strings::lower(strval($value)) === $item,
				));

				if (count($filtered) === 1) {
					return new ValueObjects\DeviceData(
						strval($value),
						MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					);
				}

				return null;
			} elseif ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					static fn (array $item): bool => $item[0] !== null
							&& Utils\Strings::lower(strval($item[0]->getValue())) === Utils\Strings::lower(
								strval($value),
							),
				));

				if (
					count($filtered) === 1
					&& $filtered[0][2] instanceof MetadataValueObjects\CombinedEnumFormatItem
				) {
					return new ValueObjects\DeviceData(
						is_scalar($filtered[0][2]->getValue()) ? $filtered[0][2]->getValue() : strval(
							$filtered[0][2]->getValue(),
						),
						$this->shortDataTypeToLong($filtered[0][2]->getDataType()),
					);
				}

				return null;
			}

			if (
				(
					$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
					&& $value instanceof MetadataTypes\SwitchPayload
				) || (
					$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
					&& $value instanceof MetadataTypes\ButtonPayload
				)
			) {
				return new ValueObjects\DeviceData(
					strval($value->getValue()),
					MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
				);
			}
		}

		return null;
	}

	public function determineDeviceReadDataType(
		MetadataTypes\DataType $dataType,
		MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|null $format,
	): MetadataTypes\DataType
	{
		$deviceExpectedDataType = $dataType;

		if ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
			$enumDataTypes = [];

			foreach ($format->getItems() as $enumItem) {
				if (
					count($enumItem) === 3
					&& $enumItem[1] instanceof MetadataValueObjects\CombinedEnumFormatItem
					&& $enumItem[1]->getDataType() !== null
				) {
					$enumDataTypes[] = $enumItem[1]->getDataType();
				}
			}

			$enumDataTypes = array_unique($enumDataTypes);

			if (count($enumDataTypes) === 1) {
				$enumDataType = $this->shortDataTypeToLong($enumDataTypes[0]);

				if ($enumDataType instanceof MetadataTypes\DataType) {
					$deviceExpectedDataType = $enumDataType;
				}
			}
		}

		return $deviceExpectedDataType;
	}

	private function shortDataTypeToLong(MetadataTypes\DataTypeShort|null $dataType): MetadataTypes\DataType|null
	{
		if ($dataType === null) {
			return null;
		}

		return match ($dataType->getValue()) {
			MetadataTypes\DataTypeShort::DATA_TYPE_CHAR => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_CHAR,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_UCHAR => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_UCHAR,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_SHORT => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_SHORT,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_USHORT => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_USHORT,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_INT => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_INT,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_UINT => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_UINT,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_FLOAT => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_FLOAT,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_BOOLEAN => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_BOOLEAN,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_STRING => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_STRING,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_SWITCH => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_SWITCH,
			),
			MetadataTypes\DataTypeShort::DATA_TYPE_BUTTON => MetadataTypes\DataType::get(
				MetadataTypes\DataType::DATA_TYPE_BUTTON,
			),
			default => null,
		};
	}

}
