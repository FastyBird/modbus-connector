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
	 * @param string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|null>|null $format
	 * @param string|int|float|bool|null $value
	 *
	 * @return float|int|string|bool|MetadataTypes\SwitchPayloadType|null
	 */
	public function transformValueFromDevice(
		MetadataTypes\DataTypeType $dataType,
		?array $format,
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

			if (is_array($format) && count($format) === 2) {
				[$minValue, $maxValue] = $format + [null, null];

				if ($minValue !== null && floatval($minValue) >= $floatValue) {
					return null;
				}

				if ($maxValue !== null && floatval($maxValue) <= $floatValue) {
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

			if (is_array($format) && count($format) === 2) {
				[$minValue, $maxValue] = $format + [null, null];

				if ($minValue !== null && intval($minValue) >= $intValue) {
					return null;
				}

				if ($maxValue !== null && intval($maxValue) <= $intValue) {
					return null;
				}
			}

			return $intValue;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_STRING)) {
			return strval($value);
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
			if (is_array($format)) {
				$filteredFormat = array_values(array_filter($format, function ($item) use ($value): bool {
					return ((is_array($item) || is_string($item))) && $this->filterEnumFormat($item, $value);
				}));

				if (
					count($filteredFormat) === 1
					&& is_array($filteredFormat[0])
					&& count($filteredFormat[0]) === 3
					&& Utils\Strings::lower(strval($filteredFormat[0][1])) === Utils\Strings::lower(strval($value))
				) {
					return MetadataTypes\SwitchPayloadType::isValidValue(strval($filteredFormat[0][0])) ? MetadataTypes\SwitchPayloadType::get(strval($filteredFormat[0][0])) : null;

				} elseif (
					count($filteredFormat) === 1
					&& !is_array($filteredFormat[0])
				) {
					return MetadataTypes\SwitchPayloadType::isValidValue(strval($filteredFormat[0])) ? MetadataTypes\SwitchPayloadType::get(strval($filteredFormat[0])) : null;
				}

				return null;
			}
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)) {
			if (is_array($format)) {
				$filteredFormat = array_values(array_filter($format, function ($item) use ($value): bool {
					return ((is_array($item) || is_string($item))) && $this->filterEnumFormat($item, $value);
				}));

				if (
					count($filteredFormat) === 1
					&& is_array($filteredFormat[0])
					&& count($filteredFormat[0]) === 3
					&& Utils\Strings::lower(strval($filteredFormat[0][1])) === Utils\Strings::lower(strval($value))
				) {
					return strval($filteredFormat[0][0]);

				} elseif (
					count($filteredFormat) === 1
					&& !is_array($filteredFormat[0])
				) {
					return strval($filteredFormat[0]);
				}

				return null;
			}
		}

		return null;
	}

	/**
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param Array<int, string>|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|null $format
	 * @param bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	 *
	 * @return string|int|float|null
	 */
	public function transformValueToDevice(
		MetadataTypes\DataTypeType $dataType,
		?array $format,
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	): string|int|float|null {
		if ($value === null) {
			return null;
		}

        if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN)) {
			if (is_bool($value)) {
				return $value === true ? 1 : 0;
			}

			if (is_numeric($value)) {
				return in_array((int) $value, [0, 1], true) ? (int) $value : null;
			}

			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT)) {
			if (is_numeric($value)) {
				return (float) $value;
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
				return (int) $value;
			}

			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_STRING)) {
			return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : (string) $value;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
			if (is_array($format)) {
				$filteredFormat = array_values(array_filter($format, function ($item) use ($value): bool {
					return ((is_array($item) || is_string($item))) && $this->filterEnumFormat($item, $value);
				}));

				if (
					count($filteredFormat) === 1
					&& is_array($filteredFormat[0])
					&& count($filteredFormat[0]) === 3
					&& Utils\Strings::lower(strval($filteredFormat[0][0])) === Utils\Strings::lower(strval($value))
				) {
					return (int) $filteredFormat[0][2];

				} elseif (
					count($filteredFormat) === 1
					&& !is_array($filteredFormat[0])
				) {
					return (int) $filteredFormat[0];

				}

				return null;
			}

			if ($value instanceof MetadataTypes\SwitchPayloadType) {
				return $value->equalsValue(MetadataTypes\SwitchPayloadType::PAYLOAD_ON) ? 1 : 0;
			}

			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)) {
			if (is_array($format)) {
				$filteredFormat = array_values(array_filter($format, function ($item) use ($value): bool {
					return ((is_array($item) || is_string($item))) && $this->filterEnumFormat($item, $value);
				}));

				if (
					count($filteredFormat) === 1
					&& is_array($filteredFormat[0])
					&& count($filteredFormat[0]) === 3
					&& Utils\Strings::lower(strval($filteredFormat[0][0])) === Utils\Strings::lower(strval($value))
				) {
					$enumValue = $filteredFormat[0][2];

				} elseif (
					count($filteredFormat) === 1
					&& !is_array($filteredFormat[0])
				) {
					$enumValue = $filteredFormat[0];

				} else {
					return null;
				}

				if (is_numeric($enumValue)) {
					if ((string) (float) $enumValue === (string) $enumValue) {
						return (float) $enumValue;
					}

					return (int) $enumValue;
				}

				return (string) $enumValue;
			}
		}

		return null;
	}

	/**
	 * @param string|Array<int, string|null> $item
	 * @param int|float|string|bool|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType $value
	 *
	 * @return bool
	 */
	private function filterEnumFormat(
		string|array $item,
		int|float|string|bool|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType $value
	): bool {
		if (is_array($item)) {
			if (count($item) !== 3) {
				return false;
			}

			return Utils\Strings::lower(strval($value)) === Utils\Strings::lower(strval($item[0]))
				|| Utils\Strings::lower(strval($value)) === Utils\Strings::lower(strval($item[1]))
				|| Utils\Strings::lower(strval($value)) === Utils\Strings::lower(strval($item[2]));
		}

		return Utils\Strings::lower(strval($value)) === Utils\Strings::lower($item);
	}

}
