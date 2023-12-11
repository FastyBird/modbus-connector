<?php declare(strict_types = 1);

/**
 * Transformer.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           02.08.22
 */

namespace FastyBird\Connector\Modbus\API;

use DateTimeInterface;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Connector\Modbus\ValueObjects;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Library\Metadata\ValueObjects as MetadataValueObjects;
use Nette;
use Nette\Utils;
use function array_filter;
use function array_reverse;
use function array_unique;
use function array_values;
use function count;
use function current;
use function floatval;
use function in_array;
use function intval;
use function is_bool;
use function is_numeric;
use function is_scalar;
use function pack;
use function strval;
use function unpack;

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

	private bool|null $machineUsingLittleEndian = null;

	/**
	 * @throws MetadataExceptions\InvalidState
	 */
	public function transformValueToDevice(
		MetadataTypes\DataType $dataType,
		// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
		MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|MetadataValueObjects\EquationFormat|null $format,
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayload|MetadataTypes\SwitchPayload|MetadataTypes\CoverPayload|null $value,
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
					static fn (string $item): bool => Utils\Strings::lower(
						strval(MetadataUtilities\ValueHelper::flattenValue($value)),
					) === $item,
				));

				if (count($filtered) === 1) {
					return new ValueObjects\DeviceData(
						MetadataUtilities\ValueHelper::flattenValue($value),
						MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					);
				}

				return null;
			} elseif ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
				$filtered = array_values(array_filter(
					$format->getItems(),
					static fn (array $item): bool => $item[0] !== null
							&& Utils\Strings::lower(strval($item[0]->getValue())) === Utils\Strings::lower(
								strval(MetadataUtilities\ValueHelper::flattenValue($value)),
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
				) || (
					$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_COVER)
					&& $value instanceof MetadataTypes\CoverPayload
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
		// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
		MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|MetadataValueObjects\EquationFormat|null $format,
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

	public function determineDeviceWriteDataType(
		MetadataTypes\DataType $dataType,
		// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
		MetadataValueObjects\StringEnumFormat|MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\CombinedEnumFormat|MetadataValueObjects\EquationFormat|null $format,
	): MetadataTypes\DataType
	{
		$deviceExpectedDataType = $dataType;

		if ($format instanceof MetadataValueObjects\CombinedEnumFormat) {
			$enumDataTypes = [];

			foreach ($format->getItems() as $enumItem) {
				if (
					count($enumItem) === 3
					&& $enumItem[2] instanceof MetadataValueObjects\CombinedEnumFormatItem
					&& $enumItem[2]->getDataType() !== null
				) {
					$enumDataTypes[] = $enumItem[2]->getDataType();
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

	/**
	 * @param array<int> $bytes
	 *
	 * @throws Exceptions\InvalidState
	 */
	public function unpackSignedInt(array $bytes, Types\ByteOrder $byteOrder): int|null
	{
		$bytes = array_values($bytes);

		if (count($bytes) === 2) {
			$value = $this->unpackNumber('s', $bytes, $byteOrder);

		} elseif (count($bytes) === 4) {
			$value = $this->unpackNumber('l', $bytes, $byteOrder);

		} else {
			return null;
		}

		if ($value !== null) {
			return intval($value);
		}

		return null;
	}

	/**
	 * @param array<int> $bytes
	 *
	 * @throws Exceptions\InvalidState
	 */
	public function unpackUnsignedInt(array $bytes, Types\ByteOrder $byteOrder): int|null
	{
		$bytes = array_values($bytes);

		if (count($bytes) === 2) {
			$value = $this->unpackNumber('S', $bytes, $byteOrder);

		} elseif (count($bytes) === 4) {
			$value = $this->unpackNumber('L', $bytes, $byteOrder);

		} else {
			return null;
		}

		if ($value !== null) {
			return intval($value);
		}

		return null;
	}

	/**
	 * @param array<int> $bytes
	 *
	 * @throws Exceptions\InvalidState
	 */
	public function unpackFloat(array $bytes, Types\ByteOrder $byteOrder): float|null
	{
		$bytes = array_values($bytes);

		if (count($bytes) === 4) {
			$value = $this->unpackNumber('f', $bytes, $byteOrder);

		} else {
			return null;
		}

		if ($value !== null) {
			return floatval($value);
		}

		return null;
	}

	/**
	 * @return array<int>|null
	 *
	 * @throws Exceptions\InvalidState
	 */
	public function packSignedInt(int $value, int $bytes, Types\ByteOrder $byteOrder): array|null
	{
		if ($bytes === 2) {
			return $this->packNumber('s', $value, $bytes, $byteOrder);
		} elseif ($bytes === 4) {
			return $this->packNumber('l', $value, $bytes, $byteOrder);
		}

		return null;
	}

	/**
	 * @return array<int>|null
	 *
	 * @throws Exceptions\InvalidState
	 */
	public function packUnsignedInt(int $value, int $bytes, Types\ByteOrder $byteOrder): array|null
	{
		if ($bytes === 2) {
			return $this->packNumber('S', $value, $bytes, $byteOrder);
		} elseif ($bytes === 4) {
			return $this->packNumber('L', $value, $bytes, $byteOrder);
		}

		return null;
	}

	/**
	 * @return array<int>|null
	 *
	 * @throws Exceptions\InvalidState
	 */
	public function packFloat(float $value, Types\ByteOrder $byteOrder): array|null
	{
		return $this->packNumber('f', $value, 4, $byteOrder);
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

	/**
	 * @param array<int> $bytes
	 *
	 * @throws Exceptions\InvalidState
	 */
	private function unpackNumber(string $format, array $bytes, Types\ByteOrder $byteOrder): int|float|null
	{
		if (count($bytes) === 2) {
			if (
				$byteOrder->equalsValue(Types\ByteOrder::BIG_SWAP)
				|| $byteOrder->equalsValue(Types\ByteOrder::BIG_LOW_WORD_FIRST)
			) {
				$byteOrder = Types\ByteOrder::get(Types\ByteOrder::BIG);
			} elseif (
				$byteOrder->equalsValue(Types\ByteOrder::LITTLE_SWAP)
				|| $byteOrder->equalsValue(Types\ByteOrder::LITTLE_LOW_WORD_FIRST)
			) {
				$byteOrder = Types\ByteOrder::get(Types\ByteOrder::LITTLE);
			}
		} elseif (count($bytes) === 4) {
			if (
				$byteOrder->equalsValue(Types\ByteOrder::BIG_SWAP)
				|| $byteOrder->equalsValue(Types\ByteOrder::LITTLE_SWAP)
			) {
				$bytes = [$bytes[1], $bytes[0], $bytes[3], $bytes[2]];

			} elseif (
				$byteOrder->equalsValue(Types\ByteOrder::BIG_LOW_WORD_FIRST)
				|| $byteOrder->equalsValue(Types\ByteOrder::LITTLE_LOW_WORD_FIRST)
			) {
				$bytes = [$bytes[2], $bytes[3], $bytes[0], $bytes[1]];
			}

			if (
				$byteOrder->equalsValue(Types\ByteOrder::BIG_SWAP)
				|| $byteOrder->equalsValue(Types\ByteOrder::BIG_LOW_WORD_FIRST)
			) {
				$byteOrder = Types\ByteOrder::get(Types\ByteOrder::BIG);
			} elseif (
				$byteOrder->equalsValue(Types\ByteOrder::LITTLE_SWAP)
				|| $byteOrder->equalsValue(Types\ByteOrder::LITTLE_LOW_WORD_FIRST)
			) {
				$byteOrder = Types\ByteOrder::get(Types\ByteOrder::LITTLE);
			}
		}

		if (
			(
				$this->isLittleEndian()
				&& $byteOrder->equalsValue(Types\ByteOrder::LITTLE)
			) || (
				!$this->isLittleEndian()
				&& $byteOrder->equalsValue(Types\ByteOrder::BIG)
			)
		) {
			// If machine is using same byte order as device
			$value = unpack($format, pack('C*', ...array_values($bytes)));

		} elseif (
			(
				!$this->isLittleEndian()
				&& $byteOrder->equalsValue(Types\ByteOrder::LITTLE)
			) || (
				$this->isLittleEndian()
				&& $byteOrder->equalsValue(Types\ByteOrder::BIG)
			)
		) {
			// If machine is using different byte order than device, do byte order swap
			$value = unpack($format, pack('C*', ...array_reverse(array_values($bytes))));

		} else {
			return null;
		}

		if ($value === false) {
			return null;
		}

		return current($value);
	}

	/**
	 * @return array<int>|null
	 *
	 * @throws Exceptions\InvalidState
	 */
	private function packNumber(string $format, int|float $value, int $bytes, Types\ByteOrder $byteOrder): array|null
	{
		$bytearray = unpack("C{$bytes}", pack($format, $value));

		if ($bytearray === false) {
			return null;
		}

		$bytearray = array_values($bytearray);

		// Check if machine is using little or big endian...
		if ($this->isLittleEndian()) {
			// If machine is using little, change byte order to be big
			$bytearray = array_reverse($bytearray);
		}

		// For all little byte orders, perform bytes order swap
		if (
			$byteOrder->equalsValue(Types\ByteOrder::LITTLE)
			|| $byteOrder->equalsValue(Types\ByteOrder::LITTLE_SWAP)
			|| $byteOrder->equalsValue(Types\ByteOrder::LITTLE_LOW_WORD_FIRST)
		) {
			$bytearray = array_reverse($bytearray);
		}

		if (
			$bytes === 4
			&& (
				$byteOrder->equalsValue(Types\ByteOrder::BIG_SWAP)
				|| $byteOrder->equalsValue(Types\ByteOrder::LITTLE_SWAP)
			)
		) {
			$bytearray = [$bytearray[1], $bytearray[0], $bytearray[3], $bytearray[2]];

		} elseif (
			$bytes === 4
			&& (
				$byteOrder->equalsValue(Types\ByteOrder::BIG_LOW_WORD_FIRST)
				|| $byteOrder->equalsValue(Types\ByteOrder::LITTLE_LOW_WORD_FIRST)
			)
		) {
			$bytearray = [$bytearray[2], $bytearray[3], $bytearray[0], $bytearray[1]];
		}

		return $bytearray;
	}

	/**
	 * Detect machine byte order configuration
	 *
	 * @throws Exceptions\InvalidState
	 */
	private function isLittleEndian(): bool
	{
		if ($this->machineUsingLittleEndian !== null) {
			return $this->machineUsingLittleEndian;
		}

		$testUnpacked = unpack('S', "\x01\x00");

		if ($testUnpacked === false) {
			throw new Exceptions\InvalidState('Machine endian order could not be determined');
		}

		$this->machineUsingLittleEndian = current($testUnpacked) === 1;

		return $this->machineUsingLittleEndian;
	}

}
