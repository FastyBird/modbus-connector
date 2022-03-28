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
Modbus connector types
"""

# Python base dependencies
from enum import Enum, unique

# Library dependencies
from fastybird_metadata.devices_module import DevicePropertyName
from fastybird_metadata.enum import ExtendedEnum

CONNECTOR_NAME: str = "modbus"
DEVICE_NAME: str = "modbus"

DEFAULT_SERIAL_INTERFACE: str = "/dev/ttyAMA0"
DEFAULT_BAUD_RATE: int = 9600


@unique
class RegisterType(Enum):
    """
    Registers types

    @package        FastyBird:ModbusConnector!
    @module         types

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    DISCRETE: int = 0x01
    COIL: int = 0x02
    INPUT: int = 0x03
    HOLDING: int = 0x04

    # -----------------------------------------------------------------------------

    @classmethod
    def has_value(cls, value: int) -> bool:
        """Check if provided value is valid enum value"""
        return value in cls._value2member_map_  # pylint: disable=no-member

    # -----------------------------------------------------------------------------

    def __str__(self) -> str:
        """Transform enum to string"""
        return str(self.value)

    # -----------------------------------------------------------------------------

    def __eq__(self, other: object) -> bool:
        """Compare two enums"""
        return str(self) == str(other)


@unique
class ModbusCommand(Enum):
    """
    Modbus's communication command

    @package        FastyBird:ModbusConnector!
    @module         types

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    READ_COIL: int = 0x01
    READ_DISCRETE: int = 0x02
    READ_HOLDING: int = 0x03
    READ_INPUT: int = 0x04
    WRITE_SINGLE_COIL: int = 0x05
    WRITE_SINGLE_HOLDING: int = 0x06
    WRITE_MULTIPLE_COILS: int = 0x15
    WRITE_MULTIPLE_HOLDINGS: int = 0x16

    # -----------------------------------------------------------------------------

    @classmethod
    def has_value(cls, value: int) -> bool:
        """Check if provided value is valid enum value"""
        return value in cls._value2member_map_  # pylint: disable=no-member

    # -----------------------------------------------------------------------------

    def __str__(self) -> str:
        """Transform enum to string"""
        return str(self.value)

    # -----------------------------------------------------------------------------

    def __eq__(self, other: object) -> bool:
        """Compare two enums"""
        return str(self) == str(other)


@unique
class DeviceAttribute(ExtendedEnum):
    """
    Device attribute name

    @package        FastyBird:ModbusConnector!
    @module         types

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    STATE: str = DevicePropertyName.STATE.value
    ADDRESS: str = DevicePropertyName.ADDRESS.value
    BYTE_SIZE: str = "byte_size"
    PARITY: str = "parity"
    STOP_BITS: str = "stop_bits"

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return hash(self._name_)  # pylint: disable=no-member
