#!/usr/bin/python3

#     Copyright 2022. FastyBird s.r.o.
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
from datetime import datetime
from typing import Dict, List, Set, Union

# Library dependencies
import minimalmodbus
import serial
from fastybird_devices_module.exceptions import TerminateConnectorException
from fastybird_metadata.devices_module import ConnectionState
from fastybird_metadata.types import ButtonPayload, DataType, SwitchPayload
from kink import inject

# Library libs
from fastybird_modbus_connector.clients.client import IClient
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
from fastybird_modbus_connector.utilities.transformers import DataTransformHelpers


@inject(alias=IClient)
class SerialClient(IClient):  # pylint: disable=too-few-public-methods
    """
    Serial client

    @package        FastyBird:ModbusConnector!
    @module         clients/serial

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __processed_devices: List[str] = []
    __processed_devices_registers: Dict[str, Dict[int, Set[str]]] = {}

    __instrument: minimalmodbus.Instrument

    __devices_registry: DevicesRegistry
    __registers_registry: RegistersRegistry

    __logger: Union[Logger, logging.Logger]

    __MAX_TRANSMIT_ATTEMPTS: int = 5  # Maximum count of sending packets before connector mark device as lost

    __LOST_DELAY: float = 5.0  # Waiting delay before another communication with device after device was lost
    __WRITE_DELAY: float = 2.0  # Waiting delay before another write request to register is made

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        baud_rate: int,
        interface: str,
        devices_registry: DevicesRegistry,
        registers_registry: RegistersRegistry,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
    ) -> None:
        self.__devices_registry = devices_registry
        self.__registers_registry = registers_registry

        try:
            self.__instrument = minimalmodbus.Instrument(
                port=interface,
                slaveaddress=0,
                mode=minimalmodbus.MODE_RTU,
                debug=(logger.level in (logging.DEBUG, logging.NOTSET)),
            )

        except Exception as ex:
            raise TerminateConnectorException("Serial interface couldn't be initialised") from ex

        self.__instrument.serial.baudrate = baud_rate
        self.__instrument.serial.bytesize = 8
        self.__instrument.serial.parity = serial.PARITY_NONE
        self.__instrument.serial.stopbits = 1
        self.__instrument.serial.timeout = 0.2

        self.__logger = logger

        self.__processed_devices = []
        self.__processed_devices_registers = {}

    # -----------------------------------------------------------------------------

    def handle(self) -> None:
        """Process Modbus requests"""
        for device in self.__devices_registry:
            if not device.enabled:
                continue

            if device.id.__str__() not in self.__processed_devices:
                if self.__process_device(device=device):
                    if self.__devices_registry.get_state(device=device) == ConnectionState.UNKNOWN:
                        try:
                            self.__devices_registry.set_state(device=device, state=ConnectionState.CONNECTED)

                        except InvalidStateException:
                            self.__logger.error(
                                "Device state could not be updated. Device is disabled and have to be updated",
                                extra={
                                    "device": {
                                        "id": device.id.__str__(),
                                    },
                                },
                            )

                            self.__devices_registry.disable(device=device)

                            continue

                self.__processed_devices.append(device.id.__str__())

                return

        self.__processed_devices = []

    # -----------------------------------------------------------------------------

    def __process_device(self, device: DeviceRecord) -> bool:
        """Handle client read or write message to device"""
        device_address = self.__devices_registry.get_address(device=device)

        if device_address is None:
            self.__logger.error(
                "Device address could not be fetched from registry. Device is disabled and have to be updated",
                extra={
                    "device": {
                        "id": device.id.__str__(),
                    },
                },
            )

            self.__devices_registry.disable(device=device)

            return False

        # Maximum communication attempts was reached, device is now marked as lost
        if device.transmit_attempts >= self.__MAX_TRANSMIT_ATTEMPTS:
            if device.is_lost:
                self.__logger.debug(
                    "Device with address: %s is still lost",
                    device_address,
                    extra={
                        "device": {
                            "id": device.id.__str__(),
                            "address": device_address,
                        },
                    },
                )

            else:
                self.__logger.debug(
                    "Device with address: %s is lost",
                    device_address,
                    extra={
                        "device": {
                            "id": device.id.__str__(),
                            "address": device_address,
                        },
                    },
                )

            try:
                self.__devices_registry.set_state(device=device, state=ConnectionState.LOST)

            except InvalidStateException:
                self.__logger.error(
                    "Device state could not be updated. Device is disabled and have to be updated",
                    extra={
                        "device": {
                            "id": device.id.__str__(),
                        },
                    },
                )

                self.__devices_registry.disable(device=device)

            return False

        if device.is_lost and time.time() - device.lost_timestamp < self.__LOST_DELAY:
            # Device is lost, lets wait for some time before another communication
            return False

        if self.__write_register_handler(device=device, device_address=device_address):
            return True

        if self.__read_registers_handler(device=device, device_address=device_address):
            return True

        return False

    # -----------------------------------------------------------------------------

    def __write_register_handler(self, device: DeviceRecord, device_address: int) -> bool:
        """Write value to device register"""
        for register_type in (RegisterType.COIL, RegisterType.HOLDING):
            registers = self.__registers_registry.get_all_for_device(
                device_id=device.id,
                register_type=register_type,
            )

            for register in registers:
                if register.expected_value is not None and (
                    register.expected_pending is None or time.time() - register.expected_pending < self.__WRITE_DELAY
                ):
                    if self.__write_single_register(
                        device=device,
                        device_address=device_address,
                        register=register,
                        write_value=register.expected_value,
                    ):
                        return True

        return False

    # -----------------------------------------------------------------------------

    def __read_registers_handler(  # pylint: disable=too-many-branches,too-many-statements
        self,
        device: DeviceRecord,
        device_address: int,
    ) -> bool:
        for registers_type in [  # pylint: disable=too-many-nested-blocks
            RegisterType.COIL,
            RegisterType.DISCRETE,
            RegisterType.HOLDING,
            RegisterType.INPUT,
        ]:
            if device.id.__str__() not in self.__processed_devices_registers:
                self.__processed_devices_registers[device.id.__str__()] = {}

            if registers_type.value not in self.__processed_devices_registers[device.id.__str__()]:
                self.__processed_devices_registers[device.id.__str__()][registers_type.value] = set()

            processed_length = len(self.__processed_devices_registers[device.id.__str__()][registers_type.value])

            registers = self.__registers_registry.get_all_for_device(
                device_id=device.id,
                register_type=registers_type,
            )

            if 0 < len(registers) != processed_length:
                # Registers have to be read one by one
                for register in registers:
                    if (
                        register.id.__str__()
                        in self.__processed_devices_registers[device.id.__str__()][registers_type.value]
                    ):
                        continue

                    self.__read_single_register(
                        device=device,
                        device_address=device_address,
                        register_type=registers_type,
                        register_address=register.address,
                        register_data_type=register.data_type,
                    )

                    self.__processed_devices_registers[device.id.__str__()][registers_type.value].add(
                        register.id.__str__(),
                    )

                    return True

        if time.time() - device.last_reading_packet_timestamp < device.sampling_time:
            return True

        for registers_type in [  # pylint: disable=too-many-nested-blocks
            RegisterType.COIL,
            RegisterType.DISCRETE,
            RegisterType.HOLDING,
            RegisterType.INPUT,
        ]:
            self.__processed_devices_registers[device.id.__str__()][registers_type.value] = set()

        return True

    # -----------------------------------------------------------------------------

    def __write_single_register(  # pylint: disable=too-many-branches,too-many-return-statements
        self,
        device: DeviceRecord,
        device_address: int,
        register: RegisterRecord,
        write_value: Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None],
    ) -> bool:
        if register.data_type in (
            DataType.CHAR,
            DataType.SHORT,
            DataType.INT,
            DataType.UCHAR,
            DataType.USHORT,
            DataType.UINT,
            DataType.FLOAT,
            DataType.BOOLEAN,
            DataType.ENUM,
            DataType.SWITCH,
        ):
            self.__instrument.address = device_address

            try:
                transformed_value = DataTransformHelpers.transform_to_device(
                    data_type=register.data_type,
                    value_format=register.format,
                    value=write_value,
                )

                if transformed_value is None:
                    self.__logger.error(
                        "Register value could transformed for transfer",
                        extra={
                            "device": {
                                "id": device.id.__str__(),
                            },
                            "register": {
                                "id": register.id.__str__(),
                                "address": register.address,
                                "value": write_value,
                            },
                        },
                    )

                    # Reset expected value for register
                    self.__registers_registry.set_expected_value(register=register, value=None)

                    return False

                if isinstance(register, CoilRegister):
                    if isinstance(transformed_value, int) and (transformed_value in (1, 0)):
                        self.__instrument.write_bit(
                            registeraddress=register.address,
                            value=transformed_value,
                            functioncode=ModbusCommand.WRITE_SINGLE_COIL.value,
                        )

                        # Update communication timestamp
                        self.__devices_registry.set_write_packet_timestamp(device=device)

                        # Update write timestamp
                        self.__registers_registry.set_expected_pending(register=register, timestamp=time.time())

                        # Add little delay before reading
                        time.sleep(0.01)

                        # Fetch written value
                        self.__read_single_register(
                            device=device,
                            device_address=device_address,
                            register_type=RegisterType.COIL,
                            register_address=register.address,
                            register_data_type=register.data_type,
                        )

                    else:
                        self.__logger.error(
                            "Transformed value is not in valid format for coil register",
                            extra={
                                "device": {
                                    "id": device.id.__str__(),
                                },
                                "register": {
                                    "id": register.id.__str__(),
                                    "address": register.address,
                                    "transformed_value": transformed_value,
                                },
                            },
                        )

                        # Reset expected value for register
                        self.__registers_registry.set_expected_value(register=register, value=None)

                        return False

                    return True

                if isinstance(register, HoldingRegister):
                    if isinstance(transformed_value, float):
                        self.__instrument.write_float(
                            registeraddress=register.address,
                            value=transformed_value,
                        )

                    elif isinstance(transformed_value, int) and transformed_value.bit_length() == 32:
                        self.__instrument.write_long(
                            registeraddress=register.address,
                            value=transformed_value,
                            signed=(register.data_type in (DataType.CHAR, DataType.SHORT, DataType.INT)),
                        )

                    elif isinstance(transformed_value, str):
                        self.__instrument.write_string(
                            registeraddress=register.address,
                            textstring=transformed_value,
                        )

                    elif isinstance(transformed_value, int):
                        self.__instrument.write_register(
                            registeraddress=register.address,
                            value=transformed_value,
                            functioncode=ModbusCommand.WRITE_SINGLE_HOLDING.value,
                            signed=(register.data_type in (DataType.CHAR, DataType.SHORT, DataType.INT)),
                        )

                    else:
                        self.__logger.error(
                            "Trying to write unsupported value",
                            extra={
                                "device": {
                                    "id": device.id.__str__(),
                                },
                                "register": {
                                    "id": register.id.__str__(),
                                    "address": register.address,
                                },
                            },
                        )

                        # Reset expected value for register
                        self.__registers_registry.set_expected_value(register=register, value=None)

                        return False

                    # Update communication timestamp
                    self.__devices_registry.set_write_packet_timestamp(device=device)

                    # Update write timestamp
                    self.__registers_registry.set_expected_pending(register=register, timestamp=time.time())

                    # Add little delay before reading
                    time.sleep(0.01)

                    # Fetch written value
                    self.__read_single_register(
                        device=device,
                        device_address=device_address,
                        register_type=RegisterType.HOLDING,
                        register_address=register.address,
                        register_data_type=register.data_type,
                    )

                    return True

                self.__logger.error(
                    "Trying to write to unsupported register",
                    extra={
                        "device": {
                            "id": device.id.__str__(),
                        },
                        "register": {
                            "id": register.id.__str__(),
                            "address": register.address,
                        },
                    },
                )

                # Reset expected value for register
                self.__registers_registry.set_expected_value(register=register, value=None)

                return False

            except minimalmodbus.NoResponseError:
                # No response from slave, try to resend command

                # Update communication timestamp
                self.__devices_registry.set_write_packet_timestamp(device=device, success=False)

                return False

            except minimalmodbus.ModbusException as ex:
                self.__logger.error(
                    "Something went wrong and register value can not be writen",
                    extra={
                        "device": {
                            "id": device.id.__str__(),
                        },
                        "register": {
                            "id": register.id.__str__(),
                            "address": register.address,
                        },
                        "exception": {
                            "message": str(ex),
                            "code": type(ex).__name__,
                        },
                    },
                )

                # Update communication timestamp
                self.__devices_registry.set_write_packet_timestamp(device=device, success=False)

                return False

        else:
            self.__logger.error(
                "Trying to write unsupported data type: %s for register",
                register.data_type,
                extra={
                    "device": {
                        "id": device.id.__str__(),
                    },
                    "register": {
                        "id": register.id.__str__(),
                        "address": register.address,
                    },
                },
            )

            # Reset expected value for register
            self.__registers_registry.set_expected_value(register=register, value=None)

            return False

    # -----------------------------------------------------------------------------

    def __read_single_register(  # pylint: disable=too-many-arguments
        self,
        device: DeviceRecord,
        device_address: int,
        register_type: RegisterType,
        register_data_type: DataType,
        register_address: int,
    ) -> None:
        self.__instrument.address = device_address

        try:
            if register_type in (RegisterType.DISCRETE, RegisterType.COIL):
                function_code = (
                    ModbusCommand.READ_DISCRETE if register_type == RegisterType.DISCRETE else ModbusCommand.READ_COIL
                )

                read_bit_result = self.__instrument.read_bit(
                    registeraddress=register_address,
                    functioncode=function_code.value,
                )

                self.__write_register_received_value(
                    device=device,
                    register_type=register_type,
                    register_address=register_address,
                    value=read_bit_result,
                )

            elif register_type in (RegisterType.INPUT, RegisterType.HOLDING):
                function_code = (
                    ModbusCommand.READ_INPUT if register_type == RegisterType.INPUT else ModbusCommand.READ_HOLDING
                )

                if register_data_type == DataType.FLOAT:
                    read_float_result = self.__instrument.read_float(
                        registeraddress=register_address,
                        functioncode=function_code.value,
                    )

                    self.__write_register_received_value(
                        device=device,
                        register_type=register_type,
                        register_address=register_address,
                        value=read_float_result,
                    )

                elif register_data_type in (DataType.INT, DataType.UINT):
                    read_long_result = self.__instrument.read_long(
                        registeraddress=register_address,
                        functioncode=function_code.value,
                        signed=(register_data_type == DataType.INT),
                    )

                    self.__write_register_received_value(
                        device=device,
                        register_type=register_type,
                        register_address=register_address,
                        value=read_long_result,
                    )

                else:
                    read_register_result = self.__instrument.read_register(
                        registeraddress=register_address,
                        functioncode=function_code.value,
                        signed=(register_data_type in (DataType.SHORT, DataType.CHAR)),
                    )

                    self.__write_register_received_value(
                        device=device,
                        register_type=register_type,
                        register_address=register_address,
                        value=read_register_result,
                    )

            else:
                self.__logger.error(
                    "Trying to read from unsupported register",
                    extra={
                        "device": {
                            "id": device.id.__str__(),
                        },
                    },
                )

                return

        except minimalmodbus.ModbusException as ex:
            self.__logger.error(
                "Something went wrong and register value cannot be read",
                extra={
                    "device": {
                        "id": device.id.__str__(),
                    },
                    "exception": {
                        "message": str(ex),
                        "code": type(ex).__name__,
                    },
                },
            )

            self.__write_register_received_error(
                device=device,
                register_type=register_type,
                register_address=register_address,
            )

            # Update communication timestamp
            self.__devices_registry.set_read_packet_timestamp(device=device, success=False)

            return

        # Update communication timestamp
        self.__devices_registry.set_read_packet_timestamp(device=device)

        return

    # -----------------------------------------------------------------------------

    def __write_register_received_value(
        self,
        device: DeviceRecord,
        register_type: RegisterType,
        register_address: int,
        value: Union[int, float],
    ) -> None:
        register = self.__registers_registry.get_by_address(
            device_id=device.id,
            register_type=register_type,
            register_address=register_address,
        )

        if register is not None:
            self.__registers_registry.set_actual_value(
                register=register,
                value=DataTransformHelpers.transform_from_device(
                    data_type=register.data_type,
                    value_format=register.format,
                    value=value,
                ),
            )

    # -----------------------------------------------------------------------------

    def __write_register_received_error(
        self,
        device: DeviceRecord,
        register_type: RegisterType,
        register_address: int,
    ) -> None:
        register = self.__registers_registry.get_by_address(
            device_id=device.id,
            register_type=register_type,
            register_address=register_address,
        )

        if register is not None:
            self.__registers_registry.set_valid_state(
                register=register,
                state=False,
            )
