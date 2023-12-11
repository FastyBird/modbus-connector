<?php declare(strict_types = 1);

/**
 * TReading.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ModbusConnector!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           31.07.22
 */

namespace FastyBird\Connector\Modbus\Clients;

use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Entities;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Helpers;
use FastyBird\Connector\Modbus\Queue;
use FastyBird\Connector\Modbus\Types;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use function array_splice;
use function usort;

/**
 * @property-read API\Transformer $transformer
 * @property-read Helpers\Entity $entityHelper
 * @property-read Helpers\Device $deviceHelper
 * @property-read Queue\Queue $queue
 * @property-read DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository
 */
trait TReading
{

	/**
	 * @param array<Entities\Clients\ReadAddress> $addresses
	 *
	 * @return array<Entities\Clients\ReadRequest>
	 */
	private function split(array $addresses, int $maxAddressesPerModbusRequest): array
	{
		if ($addresses === []) {
			return [];
		}

		$result = [];

		// Sort by address to help chunking
		usort(
			$addresses,
			static fn (Entities\Clients\ReadAddress $a, Entities\Clients\ReadAddress $b) => $a->getAddress() <=> $b->getAddress()
		);

		$startAddress = null;
		$previousAddress = null;
		$quantity = 0;
		$chunk = [];
		$maxAvailableAddress = null;

		foreach ($addresses as $currentAddress) {
			if ($startAddress === null) {
				$startAddress = $currentAddress->getAddress();
			}

			$nextAvailableAddress = $currentAddress->getAddress() + $currentAddress->getSize();

			// In case next address is smaller than previous address with its size
			// we need to make sure that quantity does not change as those addresses overlap
			if ($maxAvailableAddress === null || $nextAvailableAddress > $maxAvailableAddress) {
				$maxAvailableAddress = $nextAvailableAddress;
			}

			$previousQuantity = $quantity;
			$quantity += $currentAddress->getSize();

			if (
				$quantity >= $maxAddressesPerModbusRequest
				|| ($previousAddress !== null && ($currentAddress->getAddress() - $previousAddress->getAddress()) > $previousAddress->getSize())
			) {
				if ($currentAddress instanceof Entities\Clients\ReadCoilAddress) {
					$result[] = new Entities\Clients\ReadCoilsRequest($chunk, $startAddress, $previousQuantity);

				} elseif ($currentAddress instanceof Entities\Clients\ReadDiscreteInputAddress) {
					$result[] = new Entities\Clients\ReadDiscreteInputsRequest(
						$chunk,
						$startAddress,
						$previousQuantity,
					);

				} elseif ($currentAddress instanceof Entities\Clients\ReadHoldingRegisterAddress) {
					$result[] = new Entities\Clients\ReadHoldingsRegistersRequest(
						$chunk,
						$startAddress,
						$previousQuantity,
					);

				} elseif ($currentAddress instanceof Entities\Clients\ReadInputRegisterAddress) {
					$result[] = new Entities\Clients\ReadInputsRegistersRequest(
						$chunk,
						$startAddress,
						$previousQuantity,
					);
				}

				$startAddress = $currentAddress->getAddress();
				$quantity = $currentAddress->getSize();
				$chunk = [];
				$maxAvailableAddress = null;
			}

			$previousAddress = $currentAddress;

			$chunk[] = $currentAddress;
		}

		if ($chunk[0] instanceof Entities\Clients\ReadCoilAddress) {
			$result[] = new Entities\Clients\ReadCoilsRequest($chunk, $startAddress, $quantity);

		} elseif ($chunk[0] instanceof Entities\Clients\ReadDiscreteInputAddress) {
			$result[] = new Entities\Clients\ReadDiscreteInputsRequest($chunk, $startAddress, $quantity);

		} elseif ($chunk[0] instanceof Entities\Clients\ReadHoldingRegisterAddress) {
			$result[] = new Entities\Clients\ReadHoldingsRegistersRequest($chunk, $startAddress, $quantity);

		} elseif ($chunk[0] instanceof Entities\Clients\ReadInputRegisterAddress) {
			$result[] = new Entities\Clients\ReadInputsRegistersRequest($chunk, $startAddress, $quantity);
		}

		return $result;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processDigitalRegistersResponse(
		Entities\Clients\ReadRequest $request,
		Entities\API\ReadDigitalInputs $response,
		MetadataDocuments\DevicesModule\Device $device,
	): void
	{
		foreach ($response->getRegisters() as $address => $value) {
			if ($request instanceof Entities\Clients\ReadCoilsRequest) {
				$channel = $this->deviceHelper->findChannelByType(
					$device,
					$address,
					Types\ChannelType::get(Types\ChannelType::COIL),
				);

			} elseif ($request instanceof Entities\Clients\ReadDiscreteInputsRequest) {
				$channel = $this->deviceHelper->findChannelByType(
					$device,
					$address,
					Types\ChannelType::get(Types\ChannelType::DISCRETE_INPUT),
				);

			} else {
				continue;
			}

			if ($channel === null) {
				throw new Exceptions\InvalidState(
					'Register could not be loaded. Received data could not be handled',
				);
			}

			$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelDynamicProperties();
			$findChannelPropertyQuery->forChannel($channel);
			$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

			$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
				$findChannelPropertyQuery,
				MetadataDocuments\DevicesModule\ChannelDynamicProperty::class,
			);

			if (!$property instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
				throw new Exceptions\InvalidState(
					'Register value storage could not be loaded. Received data could not be handled',
				);
			}

			$this->queue->append(
				$this->entityHelper->create(
					Entities\Messages\StoreChannelPropertyState::class,
					[
						'connector' => $device->getConnector(),
						'device' => $device->getId(),
						'channel' => $channel->getId(),
						'property' => $property->getId(),
						'value' => $value,
					],
				),
			);
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processAnalogRegistersResponse(
		Entities\Clients\ReadRequest $request,
		Entities\API\ReadAnalogInputs $response,
		MetadataDocuments\DevicesModule\Device $device,
	): void
	{
		$registersBytes = $response->getRegisters();

		foreach ($request->getAddresses() as $requestAddress) {
			if ($request instanceof Entities\Clients\ReadHoldingsRegistersRequest) {
				$channel = $this->deviceHelper->findChannelByType(
					$device,
					$requestAddress->getAddress(),
					Types\ChannelType::get(Types\ChannelType::HOLDING_REGISTER),
				);
			} elseif ($request instanceof Entities\Clients\ReadInputsRegistersRequest) {
				$channel = $this->deviceHelper->findChannelByType(
					$device,
					$requestAddress->getAddress(),
					Types\ChannelType::get(Types\ChannelType::INPUT_REGISTER),
				);
			} else {
				continue;
			}

			if ($channel === null) {
				throw new Exceptions\InvalidState(
					'Register could not be loaded. Received data could not be handled',
				);
			}

			$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelDynamicProperties();
			$findChannelPropertyQuery->forChannel($channel);
			$findChannelPropertyQuery->byIdentifier(Types\ChannelPropertyIdentifier::VALUE);

			$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
				$findChannelPropertyQuery,
				MetadataDocuments\DevicesModule\ChannelDynamicProperty::class,
			);

			if (!$property instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
				throw new Exceptions\InvalidState(
					'Register value storage could not be loaded. Received data could not be handled',
				);
			}

			$deviceExpectedDataType = $this->transformer->determineDeviceReadDataType(
				$property->getDataType(),
				$property->getFormat(),
			);

			$registerBytes = [];

			if (
				$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
			) {
				$registerBytes = array_splice($registersBytes, 0, 2);
			} elseif (
				$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)
			) {
				$registerBytes = array_splice($registersBytes, 0, 4);
			}

			$value = null;

			if (
				$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
			) {
				$value = $this->transformer->unpackSignedInt(
					$registerBytes,
					$this->deviceHelper->getByteOrder($device),
				);
			} elseif (
				$deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
				|| $deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
			) {
				$value = $this->transformer->unpackUnsignedInt(
					$registerBytes,
					$this->deviceHelper->getByteOrder($device),
				);
			} elseif ($deviceExpectedDataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
				$value = $this->transformer->unpackFloat($registerBytes, $this->deviceHelper->getByteOrder($device));
			}

			$this->queue->append(
				$this->entityHelper->create(
					Entities\Messages\StoreChannelPropertyState::class,
					[
						'connector' => $device->getConnector(),
						'device' => $device->getId(),
						'channel' => $channel->getId(),
						'property' => $property->getId(),
						'value' => $value,
					],
				),
			);
		}
	}

}
