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
Modbus connector registry module records
"""

# Python base dependencies
import uuid
from abc import ABC
from datetime import datetime
from typing import List, Optional, Tuple, Union

# Library dependencies
from fastybird_devices_module.utils import normalize_value
from fastybird_metadata.devices_module import ConnectionState
from fastybird_metadata.types import ButtonPayload, DataType, SwitchPayload

# Library libs
from fastybird_modbus_connector.types import DeviceAttribute


class DeviceRecord:  # pylint: disable=too-many-instance-attributes
    """
    Modbus device record

    @package        FastyBird:ModbusConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __id: uuid.UUID

    __enabled: bool = False

    __last_writing_packet_timestamp: float = 0.0  # Timestamp writing when request was sent to the device
    __last_reading_packet_timestamp: float = 0.0  # Timestamp reading when request was sent to the device

    __attempts: int = 0

    __sampling_time: float = 10.0

    __lost_timestamp: float = 0.0

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        device_id: uuid.UUID,
        device_enabled: bool = False,
    ) -> None:
        self.__id = device_id
        self.__enabled = device_enabled

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Device unique identifier"""
        return self.__id

    # -----------------------------------------------------------------------------

    @property
    def enabled(self) -> bool:
        """Is device enabled?"""
        return self.__enabled

    # -----------------------------------------------------------------------------

    @enabled.setter
    def enabled(self, enabled: bool) -> None:
        """Set device enable state"""
        self.__enabled = enabled

    # -----------------------------------------------------------------------------

    @property
    def last_reading_packet_timestamp(self) -> float:
        """Last reading packet sent time stamp"""
        return self.__last_reading_packet_timestamp

    # -----------------------------------------------------------------------------

    @last_reading_packet_timestamp.setter
    def last_reading_packet_timestamp(self, timestamp: float) -> None:
        """Last reading packet sent time stamp setter"""
        self.__last_reading_packet_timestamp = timestamp

    # -----------------------------------------------------------------------------

    @property
    def last_writing_packet_timestamp(self) -> float:
        """Last writing packet sent time stamp"""
        return self.__last_writing_packet_timestamp

    # -----------------------------------------------------------------------------

    @last_writing_packet_timestamp.setter
    def last_writing_packet_timestamp(self, timestamp: float) -> None:
        """Last writing packet sent time stamp setter"""
        self.__last_writing_packet_timestamp = timestamp

    # -----------------------------------------------------------------------------

    @property
    def transmit_attempts(self) -> int:
        """Transmit packet attempts count"""
        return self.__attempts

    # -----------------------------------------------------------------------------

    @transmit_attempts.setter
    def transmit_attempts(self, attempts: int) -> None:
        """Transmit packet attempts count"""
        self.__attempts = attempts

    # -----------------------------------------------------------------------------

    @property
    def lost_timestamp(self) -> float:
        """Time stamp when communication with device was lost"""
        return self.__lost_timestamp

    # -----------------------------------------------------------------------------

    @lost_timestamp.setter
    def lost_timestamp(self, timestamp: float) -> None:
        """Time stamp when communication with device was lost setter"""
        self.__lost_timestamp = timestamp

    # -----------------------------------------------------------------------------

    @property
    def sampling_time(self) -> float:
        """Device registers reading sampling time"""
        return self.__sampling_time

    # -----------------------------------------------------------------------------

    @property
    def is_lost(self) -> bool:
        """Is device in lost state?"""
        return self.__lost_timestamp != 0

    # -----------------------------------------------------------------------------

    def __eq__(self, other: object) -> bool:
        if not isinstance(other, DeviceRecord):
            return False

        return self.id == other.id

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return self.__id.__hash__()


class RegisterRecord(ABC):  # pylint: disable=too-many-instance-attributes
    """
    Device register record

    @package        FastyBird:ModbusConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __device_id: uuid.UUID

    __id: uuid.UUID
    __address: int
    __data_type: DataType
    __format: Union[
        Tuple[Optional[int], Optional[int]],
        Tuple[Optional[float], Optional[float]],
        List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
        None,
    ] = None
    __invalid: Union[int, float, str, None] = None
    __number_of_decimals: Optional[int] = None
    __queryable: bool = False
    __settable: bool = False

    __actual_value: Union[str, int, float, bool, SwitchPayload, None] = None
    __expected_value: Union[str, int, float, bool, None] = None
    __expected_pending: Optional[float] = None
    __actual_value_valid: bool = False

    __channel_id: Optional[uuid.UUID]

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        device_id: uuid.UUID,
        register_id: uuid.UUID,
        register_address: int,
        register_data_type: DataType,
        register_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ] = None,
        register_invalid: Union[int, float, str, None] = None,
        register_queryable: bool = False,
        register_settable: bool = False,
        register_number_of_decimals: Optional[int] = None,
        channel_id: Optional[uuid.UUID] = None,
    ) -> None:
        self.__device_id = device_id

        self.__id = register_id
        self.__address = register_address
        self.__data_type = register_data_type
        self.__format = register_format
        self.__invalid = register_invalid
        self.__number_of_decimals = register_number_of_decimals

        self.__queryable = register_queryable
        self.__settable = register_settable

        self.__channel_id = channel_id

    # -----------------------------------------------------------------------------

    @property
    def device_id(self) -> uuid.UUID:
        """Register device unique identifier"""
        return self.__device_id

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Register unique identifier"""
        return self.__id

    # -----------------------------------------------------------------------------

    @property
    def address(self) -> int:
        """Register address"""
        return self.__address

    # -----------------------------------------------------------------------------

    @property
    def data_type(self) -> DataType:
        """Register value data type"""
        return self.__data_type

    # -----------------------------------------------------------------------------

    @property
    def format(
        self,
    ) -> Union[
        Tuple[Optional[int], Optional[int]],
        Tuple[Optional[float], Optional[float]],
        List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
        None,
    ]:
        """Register value format"""
        return self.__format

    # -----------------------------------------------------------------------------

    @property
    def invalid(self) -> Union[int, float, str, None]:
        """Invalid value representation"""
        return self.__invalid

    # -----------------------------------------------------------------------------

    @property
    def number_of_decimals(self) -> Optional[int]:
        """Number of decimals for transforming int to float"""
        return self.__number_of_decimals

    # -----------------------------------------------------------------------------

    @property
    def queryable(self) -> bool:
        """Is register queryable?"""
        return self.__queryable

    # -----------------------------------------------------------------------------

    @property
    def settable(self) -> bool:
        """Is register settable?"""
        return self.__settable

    # -----------------------------------------------------------------------------

    @property
    def actual_value(self) -> Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None]:
        """Register actual value"""
        return normalize_value(
            data_type=self.data_type,
            value=self.__actual_value,
            value_format=self.format,
            value_invalid=self.invalid,
        )

    # -----------------------------------------------------------------------------

    @actual_value.setter
    def actual_value(self, value: Union[str, int, float, bool, SwitchPayload, None]) -> None:
        """Set register actual value"""
        self.__actual_value = value

        if self.actual_value == self.expected_value:
            self.expected_value = None
            self.expected_pending = None

        if self.expected_value is None:
            self.expected_pending = None

    # -----------------------------------------------------------------------------

    @property
    def expected_value(self) -> Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None]:
        """Register expected value"""
        return normalize_value(
            data_type=self.data_type,
            value=self.__expected_value,
            value_format=self.format,
            value_invalid=self.invalid,
        )

    # -----------------------------------------------------------------------------

    @expected_value.setter
    def expected_value(self, value: Union[str, int, float, bool, None]) -> None:
        """Set register expected value"""
        self.__expected_value = value
        self.expected_pending = None

    # -----------------------------------------------------------------------------

    @property
    def expected_pending(self) -> Optional[float]:
        """Register expected value pending status"""
        return self.__expected_pending

    # -----------------------------------------------------------------------------

    @expected_pending.setter
    def expected_pending(self, timestamp: Optional[float]) -> None:
        """Set register expected value transmit timestamp"""
        self.__expected_pending = timestamp

    # -----------------------------------------------------------------------------

    @property
    def actual_value_valid(self) -> bool:
        """Register actual value reading status"""
        return self.__actual_value_valid

    # -----------------------------------------------------------------------------

    @actual_value_valid.setter
    def actual_value_valid(self, state: bool) -> None:
        """Register actual value reading status setter"""
        self.__actual_value_valid = state

    # -----------------------------------------------------------------------------

    @property
    def channel_id(self) -> Optional[uuid.UUID]:
        """Device channel unique database identifier"""
        return self.__channel_id

    # -----------------------------------------------------------------------------

    def __eq__(self, other: object) -> bool:
        if not isinstance(other, RegisterRecord):
            return False

        return (
            self.device_id == other.device_id
            and self.id == other.id
            and self.data_type == other.data_type
            and self.settable == other.settable
            and self.queryable == other.queryable
        )

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return self.__id.__hash__()


class DiscreteRegister(RegisterRecord):
    """
    Device discrete input register record

    @package        FastyBird:ModbusConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        device_id: uuid.UUID,
        register_id: uuid.UUID,
        register_address: int,
        register_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ] = None,
        register_invalid: Union[int, float, str, None] = None,
        channel_id: Optional[uuid.UUID] = None,
    ) -> None:
        super().__init__(
            device_id=device_id,
            register_id=register_id,
            register_address=register_address,
            register_data_type=DataType.BOOLEAN,
            register_format=register_format,
            register_invalid=register_invalid,
            register_settable=False,
            register_queryable=True,
            channel_id=channel_id,
        )


class CoilRegister(RegisterRecord):
    """
    Device coil register record

    @package        FastyBird:ModbusConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        device_id: uuid.UUID,
        register_id: uuid.UUID,
        register_address: int,
        register_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ] = None,
        register_invalid: Union[int, float, str, None] = None,
        channel_id: Optional[uuid.UUID] = None,
    ) -> None:
        super().__init__(
            device_id=device_id,
            register_id=register_id,
            register_address=register_address,
            register_data_type=DataType.BOOLEAN,
            register_format=register_format,
            register_invalid=register_invalid,
            register_settable=True,
            register_queryable=True,
            channel_id=channel_id,
        )


class InputRegister(RegisterRecord):
    """
    Device input register record

    @package        FastyBird:ModbusConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        device_id: uuid.UUID,
        register_id: uuid.UUID,
        register_address: int,
        register_data_type: DataType,
        register_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ] = None,
        register_invalid: Union[int, float, str, None] = None,
        register_number_of_decimals: Optional[int] = None,
        channel_id: Optional[uuid.UUID] = None,
    ) -> None:
        super().__init__(
            device_id=device_id,
            register_id=register_id,
            register_address=register_address,
            register_data_type=register_data_type,
            register_format=register_format,
            register_invalid=register_invalid,
            register_settable=False,
            register_queryable=True,
            register_number_of_decimals=register_number_of_decimals,
            channel_id=channel_id,
        )


class HoldingRegister(RegisterRecord):
    """
    Device holding register record

    @package        FastyBird:ModbusConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        device_id: uuid.UUID,
        register_id: uuid.UUID,
        register_address: int,
        register_data_type: DataType,
        register_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ] = None,
        register_invalid: Union[int, float, str, None] = None,
        register_number_of_decimals: Optional[int] = None,
        channel_id: Optional[uuid.UUID] = None,
    ) -> None:
        super().__init__(
            device_id=device_id,
            register_id=register_id,
            register_address=register_address,
            register_data_type=register_data_type,
            register_format=register_format,
            register_invalid=register_invalid,
            register_settable=True,
            register_queryable=True,
            register_number_of_decimals=register_number_of_decimals,
            channel_id=channel_id,
        )


class AttributeRecord:  # pylint: disable=too-many-public-methods,too-many-instance-attributes
    """
    Device attribute record

    @package        FastyBird:ModbusConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __device_id: uuid.UUID

    __id: uuid.UUID
    __type: DeviceAttribute
    __value: Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None] = None

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        device_id: uuid.UUID,
        attribute_id: uuid.UUID,
        attribute_type: DeviceAttribute,
        attribute_value: Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None] = None,
    ) -> None:
        self.__device_id = device_id

        self.__id = attribute_id
        self.__type = attribute_type
        self.__value = attribute_value

    # -----------------------------------------------------------------------------

    @property
    def device_id(self) -> uuid.UUID:
        """Device unique identifier"""
        return self.__device_id

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Attribute unique identifier"""
        return self.__id

    # -----------------------------------------------------------------------------

    @property
    def type(self) -> DeviceAttribute:
        """Attribute type"""
        return self.__type

    # -----------------------------------------------------------------------------

    @property
    def actual_value(self) -> Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None]:
        """Attribute actual value"""
        return self.__value

    # -----------------------------------------------------------------------------

    @actual_value.setter
    def actual_value(self, value: Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None]) -> None:
        """Set attribute actual value"""
        self.__value = value

    # -----------------------------------------------------------------------------

    @property
    def data_type(self) -> Optional[DataType]:
        """Attribute data type"""
        if self.type == DeviceAttribute.STATE:
            return DataType.ENUM

        if self.type == DeviceAttribute.ADDRESS:
            return DataType.BOOLEAN

        return DataType.STRING

    # -----------------------------------------------------------------------------

    @property
    def format(
        self,
    ) -> Union[List[str], Tuple[Optional[int], Optional[int]], Tuple[Optional[float], Optional[float]], None]:
        """Attribute format"""
        if self.type == DeviceAttribute.STATE:
            return [
                ConnectionState.CONNECTED.value,
                ConnectionState.DISCONNECTED.value,
                ConnectionState.LOST.value,
                ConnectionState.UNKNOWN.value,
            ]

        return None

    # -----------------------------------------------------------------------------

    def __eq__(self, other: object) -> bool:
        if not isinstance(other, AttributeRecord):
            return False

        return (
            self.device_id == other.device_id
            and self.id == other.id
            and self.type == other.type
            and self.data_type == other.data_type
            and self.format == other.format
            and self.actual_value == other.actual_value
        )

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return self.__id.__hash__()
