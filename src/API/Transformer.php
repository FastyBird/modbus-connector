<?php declare(strict_types = 1);

/**
 * Transformer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 * @since          0.34.0
 *
 * @date           02.08.22
 */

namespace FastyBird\ModbusConnector\API;

use DateTimeInterface;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\Metadata\ValueObjects as MetadataValueObjects;
use FastyBird\ModbusConnector\ValueObjects;
use Nette;
use Nette\Utils;

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
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|null $format
	 * @param string|int|float|bool|null $value
	 *
	 * @return float|int|string|bool|MetadataTypes\SwitchPayloadType|null
	 */
	public function transformValueFromDevice(
		MetadataTypes\DataTypeType $dataType,
		MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|null $format,
		string|int|float|bool|null $value
	): float|int|string|bool|MetadataTypes\SwitchPayloadType|null {
		if ($value === null) {
			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN)) {
			return is_bool($value) ? $value : boolval($value);
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT)) {
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
			$dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_CHAR)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SHORT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_USHORT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_INT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UINT)
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

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_STRING)) {
			return strval($value);
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
			if ($format instanceof MetadataValueObjects\StringEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					function (string $item) use ($value): bool {
						return Utils\Strings::lower(strval($value)) === $item;
					}
				));

				if (count($filtered) === 1) {
					return MetadataTypes\SwitchPayloadType::isValidValue(strval($value)) ? MetadataTypes\SwitchPayloadType::get(strval($value)) : null;
				}

				return null;

			} elseif ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					function (array $item) use ($value): bool {
						return $item[1] !== null
							&& Utils\Strings::lower(strval($item[1]->getValue())) === Utils\Strings::lower(strval($value));
					}
				));

				if (
					count($filtered) === 1
					&& $filtered[0][0] instanceof MetadataValueObjects\CombinedEnumFormatItem
				) {
					return MetadataTypes\SwitchPayloadType::isValidValue(strval($filtered[0][0]->getValue())) ? MetadataTypes\SwitchPayloadType::get(strval($filtered[0][0]->getValue())) : null;
				}

				return null;
			}
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)) {
			if ($format instanceof MetadataValueObjects\StringEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					function (string $item) use ($value): bool {
						return Utils\Strings::lower(strval($value)) === $item;
					}
				));

				if (count($filtered) === 1) {
					return strval($value);
				}

				return null;

			} elseif ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					function (array $item) use ($value): bool {
						return $item[1] !== null
							&& Utils\Strings::lower(strval($item[1]->getValue())) === Utils\Strings::lower(strval($value));
					}
				));

				if (
					count($filtered) === 1
					&& $filtered[0][0] instanceof MetadataValueObjects\CombinedEnumFormatItem
				) {
					return strval($filtered[0][0]->getValue());
				}

				return null;
			}
		}

		return null;
	}

	/**
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|null $format
	 * @param bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	 *
	 * @return ValueObjects\DeviceDataValueObject|null
	 */
	public function transformValueToDevice(
		MetadataTypes\DataTypeType $dataType,
		MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|null $format,
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	): ?ValueObjects\DeviceDataValueObject {
		if ($value === null) {
			return null;
		}

        if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN)) {
			if (is_bool($value)) {
				return new ValueObjects\DeviceDataValueObject(
					$value === true ? 1 : 0,
					$dataType
				);
			}

			if (is_numeric($value) && in_array((int) $value, [0, 1], true)) {
				return new ValueObjects\DeviceDataValueObject(
					(int) $value === 1 ? 1 : 0,
					$dataType
				);
			}

			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT)) {
			if (is_numeric($value)) {
				return new ValueObjects\DeviceDataValueObject(
					(float) $value,
					$dataType
				);
			}

			return null;
		}

		if (
			$dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_CHAR)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SHORT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_USHORT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_INT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UINT)
		) {
			if (is_numeric($value)) {
				return new ValueObjects\DeviceDataValueObject(
					(int) $value,
					$dataType
				);
			}

			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_STRING)) {
			return new ValueObjects\DeviceDataValueObject(
				$value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : (string) $value,
				$dataType
			);
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
			if ($format instanceof MetadataValueObjects\StringEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					function (string $item) use ($value): bool {
						return Utils\Strings::lower(strval($value)) === $item;
					}
				));

				if (count($filtered) === 1) {
					return new ValueObjects\DeviceDataValueObject(
						strval($value),
						MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING)
					);
				}

				return null;

			} elseif ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					function (array $item) use ($value): bool {
						return $item[0] !== null
							&& Utils\Strings::lower(strval($item[0]->getValue())) === Utils\Strings::lower(strval($value));
					}
				));

				if (
					count($filtered) === 1
					&& $filtered[0][2] instanceof MetadataValueObjects\CombinedEnumFormatItem
				) {
					return new ValueObjects\DeviceDataValueObject(
						is_scalar($filtered[0][2]->getValue()) ? $filtered[0][2]->getValue() : strval($filtered[0][2]->getValue()),
						$this->shortDataTypeToLong($filtered[0][2]->getDataType())
					);
				}

				return null;
			}

			if ($value instanceof MetadataTypes\SwitchPayloadType) {
				return new ValueObjects\DeviceDataValueObject(
					$value->equalsValue(MetadataTypes\SwitchPayloadType::PAYLOAD_ON) ? 1 : 0,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR)
				);
			}

			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)) {
			if ($format instanceof MetadataValueObjects\StringEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					function (string $item) use ($value): bool {
						return Utils\Strings::lower(strval($value)) === $item;
					}
				));

				if (count($filtered) === 1) {
					return new ValueObjects\DeviceDataValueObject(
						strval($value),
						MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING)
					);
				}

				return null;

			} elseif ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					function (array $item) use ($value): bool {
						return $item[0] !== null
							&& Utils\Strings::lower(strval($item[0]->getValue())) === Utils\Strings::lower(strval($value));
					}
				));

				if (
					count($filtered) === 1
					&& $filtered[0][2] instanceof MetadataValueObjects\CombinedEnumFormatItem
				) {
					return new ValueObjects\DeviceDataValueObject(
						is_scalar($filtered[0][2]->getValue()) ? $filtered[0][2]->getValue() : strval($filtered[0][2]->getValue()),
						$this->shortDataTypeToLong($filtered[0][2]->getDataType())
					);
				}

				return null;
			}
		}

		return null;
	}

	/**
	 * @param MetadataTypes\DataTypeShortType|null $dataType
	 *
	 * @return MetadataTypes\DataTypeType|null
	 */
	private function shortDataTypeToLong(?MetadataTypes\DataTypeShortType $dataType): ?MetadataTypes\DataTypeType
	{
		if ($dataType === null) {
			return null;
		}

		return match ($dataType->getValue()) {
			MetadataTypes\DataTypeShortType::DATA_TYPE_CHAR => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_CHAR),
			MetadataTypes\DataTypeShortType::DATA_TYPE_UCHAR => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR),
			MetadataTypes\DataTypeShortType::DATA_TYPE_SHORT => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_SHORT),
			MetadataTypes\DataTypeShortType::DATA_TYPE_USHORT => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_USHORT),
			MetadataTypes\DataTypeShortType::DATA_TYPE_INT => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_INT),
			MetadataTypes\DataTypeShortType::DATA_TYPE_UINT => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UINT),
			MetadataTypes\DataTypeShortType::DATA_TYPE_FLOAT => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT),
			MetadataTypes\DataTypeShortType::DATA_TYPE_BOOLEAN => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN),
			MetadataTypes\DataTypeShortType::DATA_TYPE_STRING => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
			default => null,
		};
	}

}
