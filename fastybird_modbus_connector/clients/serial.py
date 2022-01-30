#!/usr/bin/python3

#     Copyright 2021. FastyBird s.r.o.
#
#     Licensed under the Apache License, Version 2.0 (the "License");
#     you may not use this file except in compliance with the License.
#     You may obtain a copy of the License at
#
#         http://www.apache.org/licenses/LICENSE-2.0
#
#     Unless required by applicable law or agreed to in writing, software
#     distributed under the License is distributed on an "AS IS" BASIS,
#     WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#     See the License for the specific language governing permissions and
#     limitations under the License.

"""
Modbus connector clients module serial client
"""

# Python base dependencies
import logging
import time
from typing import List, Optional, Union

# Library dependencies
import minimalmodbus
import serial
from fastybird_metadata.devices_module import ConnectionState
from fastybird_metadata.types import DataType

# Library libs
from fastybird_modbus_connector.clients.base import IClient
from fastybird_modbus_connector.exceptions import InvalidStateException
from fastybird_modbus_connector.logger import Logger
from fastybird_modbus_connector.registry.model import DevicesRegistry, RegistersRegistry
from fastybird_modbus_connector.registry.records import (
    CoilRegister,
    DeviceRecord,
    HoldingRegister,
    RegisterRecord,
)
from fastybird_modbus_connector.types import ModbusCommand, RegisterType


class SerialClient(IClient):  # pylint: disable=too-few-public-methods
    """
    Serial client

    @package        FastyBird:ModbusConnector!
    @module         clients

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __processed_devices: List[str] = []

    __instrument: minimalmodbus.Instrument

    __devices_registry: DevicesRegistry
    __registers_registry: RegistersRegistry

    __logger: Union[Logger, logging.Logger]

    __SERIAL_BAUD_RATE: int = 9600
    __SERIAL_INTERFACE: str = "/dev/ttyAMA0"

    __MAX_TRANSMIT_ATTEMPTS: int = 5  # Maximum count of sending packets before client mark device as lost

    __COMMUNICATION_DELAY: float = 0.5  # Waiting delay before another communication with device is made
    __LOST_DELAY: float = 15.0  # Waiting delay before another communication with device after device was lost

    __MAX_READABLE_REGISTERS_COUNT: int = 125

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        client_baud_rate: Optional[int],
        client_interface: Optional[str],
        devices_registry: DevicesRegistry,
        registers_registry: RegistersRegistry,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
    ) -> None:
        self.__devices_registry = devices_registry
        self.__registers_registry = registers_registry

        self.__logger = logger

        self.__instrument = minimalmodbus.Instrument(
            port=client_interface if client_interface is not None else self.__SERIAL_INTERFACE,
            slaveaddress=0,
            mode=minimalmodbus.MODE_RTU,
            debug=True,
        )

        self.__instrument.serial.baudrate = (
            client_baud_rate if client_baud_rate is not None else self.__SERIAL_BAUD_RATE
        )
        self.__instrument.serial.bytesize = 8
        self.__instrument.serial.parity = serial.PARITY_NONE
        self.__instrument.serial.stopbits = 1
        self.__instrument.serial.timeout = 0.2

    # -----------------------------------------------------------------------------

    def handle(self) -> None:
        """Process Modbus requests"""
        for device in self.__devices_registry:
            if device.id.__str__() not in self.__processed_devices and device.enabled:
                self.__processed_devices.append(device.id.__str__())

                # Maximum communication attempts was reached, device is now marked as lost
                if device.transmit_attempts >= self.__MAX_TRANSMIT_ATTEMPTS:
                    self.__devices_registry.reset_communication(device=device)

                    if device.is_lost:
                        self.__logger.debug(
                            "Device with address: %s is still lost",
                            self.__devices_registry.get_address(device=device),
                            extra={
                                "device": {
                                    "id": device.id.__str__(),
                                },
                            },
                        )

                    else:
                        self.__logger.debug(
                            "Device with address: %s is lost",
                            self.__devices_registry.get_address(device=device),
                            extra={
                                "device": {
                                    "id": device.id.__str__(),
                                },
                            },
                        )

                        self.__devices_registry.set_state(device=device, state=ConnectionState.LOST)

                    continue

                if device.is_lost and time.time() - device.last_packet_timestamp < self.__LOST_DELAY:
                    # Device is lost, let wait for some time before another try to communicate
                    continue

                # Check for delay between reading
                if time.time() - device.last_packet_timestamp >= self.__COMMUNICATION_DELAY:
                    if self.__write_register_handler(device_record=device):
                        continue

                    if time.time() - device.get_last_register_reading_timestamp() >= device.sampling_time:
                        if self.__read_registers_handler(device_record=device):
                            continue

    # -----------------------------------------------------------------------------

    def __write_register_handler(self, device_record: DeviceRecord) -> bool:
        """Write value to device register"""
        for register_type in (RegisterType.COIL, RegisterType.HOLDING):
            if self.__write_single_register_handler(device_record=device_record, register_type=register_type):
                return True

        return False

    # -----------------------------------------------------------------------------

    def __read_registers_handler(self, device_record: DeviceRecord) -> bool:
        """Read value from device register"""
        reading_address, reading_register_type = device_record.get_reading_register()

        for register_type in RegisterType:
            if len(
                self.__registers_registry.get_all_for_device(device_id=device_record.id, register_type=register_type)
            ) > 0 and (reading_register_type == register_type or reading_register_type is None):
                return self.__read_multiple_registers(
                    device_record=device_record,
                    register_type=register_type,
                    start_address=reading_address,
                )

        return False

    # -----------------------------------------------------------------------------

    def __write_single_register_handler(self, device_record: DeviceRecord, register_type: RegisterType) -> bool:
        registers = self.__registers_registry.get_all_for_device(
            device_id=device_record.id,
            register_type=register_type,
        )

        for register in registers:
            if register.expected_value is not None and register.expected_pending is None:
                if self.__write_value_to_single_register(
                    device_record=device_record,
                    register_record=register,
                    write_value=register.expected_value,
                ):
                    self.__registers_registry.set_expected_pending(register=register, timestamp=time.time())

                    return True

        return False

    # -----------------------------------------------------------------------------

    def __write_value_to_single_register(  # pylint: disable=too-many-branches
        self,
        device_record: DeviceRecord,
        register_record: RegisterRecord,
        write_value: Union[int, float, bool],
    ) -> bool:
        device_address = self.__devices_registry.get_address(device=device_record)

        if device_address is None:
            self.__devices_registry.disable(device=device_record)

            return False

        if register_record.data_type in (
            DataType.CHAR,
            DataType.SHORT,
            DataType.INT,
            DataType.UCHAR,
            DataType.USHORT,
            DataType.UINT,
            DataType.FLOAT,
            DataType.BOOLEAN,
        ) and isinstance(write_value, (int, float, bool)):
            self.__instrument.address = device_address

            try:
                transformed_value: Union[int, float] = (
                    (1 if write_value is True else 0) if isinstance(write_value, bool) else write_value
                )

                if isinstance(register_record, CoilRegister):
                    if isinstance(transformed_value, int):
                        self.__instrument.write_bit(
                            registeraddress=register_record.address,
                            value=transformed_value,
                            functioncode=ModbusCommand.WRITE_SINGLE_COIL.value,
                        )

                    else:
                        self.__logger.error(
                            "Register value could not be writen. Only boolean values could be writen.",
                            extra={
                                "device": {
                                    "id": device_record.id.__str__(),
                                },
                                "register": {
                                    "id": register_record.id.__str__(),
                                    "address": register_record.address,
                                },
                            },
                        )

                        # Reset expected value for register
                        self.__registers_registry.set_expected_value(register=register_record, value=None)

                        return True

                elif isinstance(register_record, HoldingRegister):
                    if isinstance(transformed_value, float):
                        self.__instrument.write_float(
                            registeraddress=register_record.address,
                            value=transformed_value,
                        )

                    elif transformed_value.bit_length() == 32:
                        self.__instrument.write_long(
                            registeraddress=register_record.address,
                            value=transformed_value,
                            signed=(register_record.data_type in (DataType.CHAR, DataType.SHORT, DataType.INT)),
                        )

                    else:
                        self.__instrument.write_register(
                            registeraddress=register_record.address,
                            value=transformed_value,
                            functioncode=ModbusCommand.WRITE_SINGLE_REGISTER.value,
                            signed=(register_record.data_type in (DataType.CHAR, DataType.SHORT, DataType.INT)),
                        )

                else:
                    raise InvalidStateException("Trying to write to not writable register")

            except minimalmodbus.NoResponseError:
                # No response from slave, try to resend command
                pass

            except minimalmodbus.ModbusException as ex:
                self.__logger.error(
                    "Something went wrong and register value can not be writen.",
                    extra={
                        "device": {
                            "id": device_record.id.__str__(),
                        },
                        "register": {
                            "id": register_record.id.__str__(),
                            "address": register_record.address,
                        },
                    },
                )

                self.__logger.exception(ex)

                return False

        else:
            self.__logger.error(
                "Trying to write unsupported data type: %s for register",
                register_record.data_type,
                extra={
                    "device": {
                        "id": device_record.id.__str__(),
                    },
                    "register": {
                        "id": register_record.id.__str__(),
                        "address": register_record.address,
                    },
                },
            )

        return True

    # -----------------------------------------------------------------------------

    def __read_multiple_registers(
        self,
        device_record: DeviceRecord,
        register_type: RegisterType,
        start_address: Optional[int],
    ) -> bool:
        device_address = self.__devices_registry.get_address(device=device_record)

        if device_address is None:
            self.__devices_registry.disable(device=device_record)

            return False

        register_size = len(
            self.__registers_registry.get_all_for_device(device_id=device_record.id, register_type=register_type)
        )

        if start_address is None:
            start_address = 0

        # Calculate reading address based on maximum reading length and start address
        # e.g. start_address = 0 and __MAX_READABLE_REGISTERS_COUNT = 3 => max_readable_addresses = 2
        # e.g. start_address = 3 and __MAX_READABLE_REGISTERS_COUNT = 3 => max_readable_addresses = 5
        # e.g. start_address = 0 and __MAX_READABLE_REGISTERS_COUNT = 8 => max_readable_addresses = 7
        max_readable_addresses = start_address + self.__MAX_READABLE_REGISTERS_COUNT - 1

        if (max_readable_addresses + 1) >= register_size:
            if start_address == 0:
                read_length = register_size
                next_address = start_address + read_length

            else:
                read_length = register_size - start_address
                next_address = start_address + read_length

        else:
            read_length = self.__MAX_READABLE_REGISTERS_COUNT
            next_address = start_address + read_length

        # Validate registers reading length
        if read_length <= 0:
            return False

        self.__instrument.address = device_address

        try:
            if register_type == RegisterType.DISCRETE:
                result = self.__instrument.read_bits(
                    registeraddress=start_address,
                    number_of_bits=read_length,
                    functioncode=ModbusCommand.READ_DISCRETE_INPUTS.value,
                )

                self.__write_registers_received_values(
                    device_record=device_record,
                    register_type=RegisterType.DISCRETE,
                    start_address=start_address,
                    values=result,
                )

            elif register_type == RegisterType.COIL:
                result = self.__instrument.read_bits(
                    registeraddress=start_address,
                    number_of_bits=read_length,
                    functioncode=ModbusCommand.READ_COILS.value,
                )

                self.__write_registers_received_values(
                    device_record=device_record,
                    register_type=RegisterType.COIL,
                    start_address=start_address,
                    values=result,
                )

            elif register_type == RegisterType.INPUT:
                result = self.__instrument.read_registers(
                    registeraddress=start_address,
                    number_of_registers=register_size,
                    functioncode=ModbusCommand.READ_INPUT_REGISTERS.value,
                )

                self.__write_registers_received_values(
                    device_record=device_record,
                    register_type=RegisterType.INPUT,
                    start_address=start_address,
                    values=result,
                )

            elif register_type == RegisterType.HOLDING:
                result = self.__instrument.read_registers(
                    registeraddress=start_address,
                    number_of_registers=register_size,
                    functioncode=ModbusCommand.READ_HOLDING_REGISTERS.value,
                )

                self.__write_registers_received_values(
                    device_record=device_record,
                    register_type=RegisterType.HOLDING,
                    start_address=start_address,
                    values=result,
                )

        except minimalmodbus.ModbusException as ex:
            self.__logger.error(
                "Something went wrong and registers values cannot be read.",
                extra={
                    "device": {
                        "id": device_record.id.__str__(),
                    },
                },
            )

            self.__logger.exception(ex)

            return False

        # Update reading pointer
        device = self.__devices_registry.set_reading_register(
            device=device_record,
            register_address=next_address,
            register_type=register_type,
        )

        # Check pointer against to registers size
        if (next_address + 1) > register_size:
            self.__update_reading_pointer(device=device)

        return True

    # -----------------------------------------------------------------------------

    def __write_registers_received_values(
        self,
        device_record: DeviceRecord,
        register_type: RegisterType,
        start_address: int,
        values: List[int],
    ) -> None:
        register_address = start_address

        for value in values:
            register_record = self.__registers_registry.get_by_address(
                device_id=device_record.id,
                register_type=register_type,
                register_address=register_address,
            )

            if register_record is not None:
                self.__registers_registry.set_actual_value(register=register_record, value=value)

            register_address += 1

    # -----------------------------------------------------------------------------

    def __update_reading_pointer(self, device: DeviceRecord) -> None:
        _, reading_register_type = device.get_reading_register()

        if reading_register_type is not None:
            if reading_register_type == RegisterType.DISCRETE:
                for next_register_type in [RegisterType.COIL, RegisterType.INPUT, RegisterType.HOLDING]:
                    if (
                        len(
                            self.__registers_registry.get_all_for_device(
                                device_id=device.id, register_type=next_register_type
                            )
                        )
                        > 0
                    ):
                        self.__devices_registry.set_reading_register(
                            device=device,
                            register_address=0,
                            register_type=next_register_type,
                        )

                        return

            if reading_register_type == RegisterType.COIL:
                for next_register_type in [RegisterType.INPUT, RegisterType.HOLDING]:
                    if (
                        len(
                            self.__registers_registry.get_all_for_device(
                                device_id=device.id, register_type=next_register_type
                            )
                        )
                        > 0
                    ):
                        self.__devices_registry.set_reading_register(
                            device=device,
                            register_address=0,
                            register_type=next_register_type,
                        )

                        return

            if reading_register_type == RegisterType.INPUT:
                for next_register_type in [RegisterType.HOLDING]:
                    if (
                        len(
                            self.__registers_registry.get_all_for_device(
                                device_id=device.id, register_type=next_register_type
                            )
                        )
                        > 0
                    ):
                        self.__devices_registry.set_reading_register(
                            device=device,
                            register_address=0,
                            register_type=next_register_type,
                        )

                        return

        self.__devices_registry.reset_reading_register(device=device)
