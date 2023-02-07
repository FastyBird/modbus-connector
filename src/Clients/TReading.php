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

use FastyBird\Connector\Modbus;
use FastyBird\Connector\Modbus\Entities;
use function usort;

trait TReading
{

	/**
	 * @param array<Entities\Clients\ReadAddress> $addresses
	 *
	 * @return array<Entities\Clients\ReadRequest>
	 */
	public function split(array $addresses): array
	{
		$result = [];

		// Sort by address and size to help chunking
		usort($addresses, static function (Entities\Clients\ReadAddress $a, Entities\Clients\ReadAddress $b) {
			$aAddr = $a->getAddress();
			$bAddr = $b->getAddress();

			if ($aAddr === $bAddr) {
				$sizeCmp = $a->getSize() <=> $b->getSize();

				return $sizeCmp !== 0
					? $sizeCmp
					: $a->getChannel()->getIdentifier() <=> $b->getChannel()->getIdentifier();
			}

			return $aAddr <=> $bAddr;
		});

		$startAddress = null;
		$quantity = 0;
		$chunk = [];
		$maxAvailableRegister = null;

		foreach ($addresses as $currentAddress) {
			$currentStartAddress = $currentAddress->getAddress();

			if ($startAddress === null) {
				$startAddress = $currentStartAddress;
			}

			$nextAvailableRegister = $currentStartAddress + $currentAddress->getSize();

			// In case next address is smaller than previous address with its size
			// we need to make sure that quantity does not change as those addresses overlap
			if ($maxAvailableRegister === null || $nextAvailableRegister > $maxAvailableRegister) {
				$maxAvailableRegister = $nextAvailableRegister;
			} elseif ($nextAvailableRegister < $maxAvailableRegister) {
				$nextAvailableRegister = $maxAvailableRegister;
			}

			$previousQuantity = $quantity;
			$quantity = $nextAvailableRegister - $startAddress;

			$maxAddressesPerModbusRequest = (
				$currentAddress instanceof Entities\Clients\ReadCoilAddress
				|| $currentAddress instanceof Entities\Clients\ReadDiscreteInputAddress
			)
				? Modbus\Constants::MAX_DISCRETE_REGISTERS_PER_MODBUS_REQUEST
				: Modbus\Constants::MAX_ANALOG_REGISTERS_PER_MODBUS_REQUEST;

			if ($quantity >= $maxAddressesPerModbusRequest) {
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

				$startAddress = $currentStartAddress;
				$quantity = $currentAddress->getSize();
				$chunk = [];
				$maxAvailableRegister = null;
			}

			$chunk[] = $currentAddress;
		}

		if ($chunk !== []) {
			if ($chunk[0] instanceof Entities\Clients\ReadCoilAddress) {
				$result[] = new Entities\Clients\ReadCoilsRequest($chunk, $startAddress, $quantity);

			} elseif ($chunk[0] instanceof Entities\Clients\ReadDiscreteInputAddress) {
				$result[] = new Entities\Clients\ReadDiscreteInputsRequest($chunk, $startAddress, $quantity);

			} elseif ($chunk[0] instanceof Entities\Clients\ReadHoldingRegisterAddress) {
				$result[] = new Entities\Clients\ReadHoldingsRegistersRequest($chunk, $startAddress, $quantity);

			} elseif ($chunk[0] instanceof Entities\Clients\ReadInputRegisterAddress) {
				$result[] = new Entities\Clients\ReadInputsRegistersRequest($chunk, $startAddress, $quantity);
			}
		}

		return $result;
	}

}
