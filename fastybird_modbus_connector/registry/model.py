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

# pylint: disable=too-many-lines

"""
Modbus connector registry module models
"""

# Python base dependencies
import time
import uuid
from datetime import datetime
from typing import Dict, List, Optional, Sequence, Tuple, Union

# Library dependencies
from fastybird_devices_module.repositories.channel import ChannelPropertiesRepository
from fastybird_devices_module.repositories.state import (
    ChannelPropertiesStatesRepository,
    DevicePropertiesStatesRepository,
)
from fastybird_metadata.devices_module import ConnectionState
from fastybird_metadata.types import ButtonPayload, DataType, SwitchPayload
from kink import inject
from whistle import EventDispatcher

# Library libs
from fastybird_modbus_connector.events.events import (
    AttributeActualValueEvent,
    DeviceRecordUpdatedEvent,
    RegisterActualValueEvent,
)
from fastybird_modbus_connector.exceptions import InvalidStateException
from fastybird_modbus_connector.registry.records import (
    AttributeRecord,
    CoilRegister,
    DeviceRecord,
    DiscreteRegister,
    HoldingRegister,
    InputRegister,
    RegisterRecord,
)
from fastybird_modbus_connector.types import DeviceAttribute, RegisterType


class DevicesRegistry:
    """
    Devices registry

    @package        FastyBird:ModbusConnector!
    @module         registry/model

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __items: Dict[str, DeviceRecord] = {}

    __iterator_index = 0

    __attributes_registry: "AttributesRegistry"
    __registers_registry: "RegistersRegistry"

    __event_dispatcher: EventDispatcher

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        event_dispatcher: EventDispatcher,
        attributes_registry: "AttributesRegistry",
        registers_registry: "RegistersRegistry",
    ) -> None:
        self.__items = {}

        self.__event_dispatcher = event_dispatcher

        self.__attributes_registry = attributes_registry
        self.__registers_registry = registers_registry

    # -----------------------------------------------------------------------------

    def get_by_id(self, device_id: uuid.UUID) -> Optional[DeviceRecord]:
        """Find device in registry by given unique identifier"""
        items = self.__items.copy()

        return next(
            iter([record for record in items.values() if device_id.__eq__(record.id)]),
            None,
        )

    # -----------------------------------------------------------------------------

    def append(
        self,
        device_id: uuid.UUID,
        device_enabled: bool = False,
    ) -> DeviceRecord:
        """Append device record into registry"""
        device_record = DeviceRecord(
            device_id=device_id,
            device_enabled=device_enabled,
        )

        self.__items[device_record.id.__str__()] = device_record

        return device_record

    # -----------------------------------------------------------------------------

    def remove(self, device_id: uuid.UUID) -> None:
        """Remove device from registry"""
        items = self.__items.copy()

        for record in items.values():
            if device_id.__eq__(record.id):
                try:
                    del self.__items[record.id.__str__()]

                    self.__attributes_registry.reset(device_id=record.id)
                    self.__registers_registry.reset(device_id=record.id)

                except KeyError:
                    pass

                break

    # -----------------------------------------------------------------------------

    def reset(self) -> None:
        """Reset devices registry to initial state"""
        items = self.__items.copy()

        for record in items.values():
            self.__attributes_registry.reset(device_id=record.id)
            self.__registers_registry.reset(device_id=record.id)

        self.__items = {}

    # -----------------------------------------------------------------------------

    def enable(self, device: DeviceRecord) -> DeviceRecord:
        """Enable device for communication"""
        device.enabled = True

        self.__update(device=device, dispatch=True)

        updated_device = self.get_by_id(device.id)

        if updated_device is None:
            raise InvalidStateException("Device record could not be re-fetched from registry after update")

        return updated_device

    # -----------------------------------------------------------------------------

    def disable(self, device: DeviceRecord) -> DeviceRecord:
        """Enable device for communication"""
        device.enabled = False

        self.__update(device=device, dispatch=True)

        updated_device = self.get_by_id(device.id)

        if updated_device is None:
            raise InvalidStateException("Device record could not be re-fetched from registry after update")

        return updated_device

    # -----------------------------------------------------------------------------

    def set_state(self, device: DeviceRecord, state: ConnectionState) -> DeviceRecord:
        """Set device actual state"""
        actual_state = self.__attributes_registry.get_by_attribute(
            device_id=device.id,
            attribute_type=DeviceAttribute.STATE,
        )

        if actual_state is None:
            raise InvalidStateException(
                "Device state could not be updated. Attribute register was not found in registry",
            )

        self.__attributes_registry.set_value(attribute=actual_state, value=state.value)

        if actual_state.actual_value != state.value:
            # Reset pointers & counters
            device.lost_timestamp = 0
            device.transmit_attempts = 0
            device.last_writing_packet_timestamp = 0
            device.last_reading_packet_timestamp = 0

        if state == ConnectionState.LOST:
            device.lost_timestamp = time.time()
            device.transmit_attempts = 0
            device.last_writing_packet_timestamp = 0
            device.last_reading_packet_timestamp = 0

        self.__update(device=device)

        updated_device = self.get_by_id(device.id)

        if updated_device is None:
            raise InvalidStateException("Device record could not be re-fetched from registry after update")

        return device

    # -----------------------------------------------------------------------------

    def get_state(self, device: DeviceRecord) -> ConnectionState:
        """Set device actual state"""
        actual_state = self.__attributes_registry.get_by_attribute(
            device_id=device.id,
            attribute_type=DeviceAttribute.STATE,
        )

        if actual_state is not None and ConnectionState.has_value(str(actual_state.actual_value)):
            return ConnectionState(actual_state.actual_value)

        return ConnectionState.UNKNOWN

    # -----------------------------------------------------------------------------

    def set_write_packet_timestamp(self, device: DeviceRecord, success: bool = True) -> DeviceRecord:
        """Set packet timestamp for registers writing"""
        device.last_writing_packet_timestamp = time.time()
        device.transmit_attempts = 0 if success else device.transmit_attempts + 1

        self.__update(device=device)

        updated_device = self.get_by_id(device.id)

        if updated_device is None:
            raise InvalidStateException("Device record could not be re-fetched from registry after update")

        return updated_device

    # -----------------------------------------------------------------------------

    def set_read_packet_timestamp(self, device: DeviceRecord, success: bool = True) -> DeviceRecord:
        """Set packet timestamp for registers reading"""
        device.last_reading_packet_timestamp = time.time()
        device.transmit_attempts = 0 if success else device.transmit_attempts + 1

        self.__update(device=device)

        updated_device = self.get_by_id(device.id)

        if updated_device is None:
            raise InvalidStateException("Device record could not be re-fetched from registry after update")

        return updated_device

    # -----------------------------------------------------------------------------

    def get_address(self, device: DeviceRecord) -> Optional[int]:
        """Get device actual state"""
        actual_address = self.__attributes_registry.get_by_attribute(
            device_id=device.id,
            attribute_type=DeviceAttribute.ADDRESS,
        )

        if actual_address is None or not isinstance(actual_address.actual_value, int):
            return None

        return actual_address.actual_value

    # -----------------------------------------------------------------------------

    def __update(self, device: DeviceRecord, dispatch: bool = False) -> bool:
        items = self.__items.copy()

        for record in items.values():
            if record.id == device.id:
                self.__items[device.id.__str__()] = device

                if dispatch:
                    self.__event_dispatcher.dispatch(
                        event_id=DeviceRecordUpdatedEvent.EVENT_NAME,
                        event=DeviceRecordUpdatedEvent(record=device),
                    )

                return True

        return False

    # -----------------------------------------------------------------------------

    def __iter__(self) -> "DevicesRegistry":
        # Reset index for nex iteration
        self.__iterator_index = 0

        return self

    # -----------------------------------------------------------------------------

    def __len__(self) -> int:
        return len(self.__items.values())

    # -----------------------------------------------------------------------------

    def __next__(self) -> DeviceRecord:
        if self.__iterator_index < len(self.__items.values()):
            items: List[DeviceRecord] = list(self.__items.values())

            result: DeviceRecord = items[self.__iterator_index]

            self.__iterator_index += 1

            return result

        # Reset index for nex iteration
        self.__iterator_index = 0

        # End of iteration
        raise StopIteration


@inject
class RegistersRegistry:
    """
    Registers registry

    @package        FastyBird:ModbusConnector!
    @module         registry/model

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __items: Dict[str, RegisterRecord] = {}

    __iterator_index = 0

    __event_dispatcher: EventDispatcher

    __channels_properties_repository: ChannelPropertiesRepository
    __channel_property_state_repository: ChannelPropertiesStatesRepository

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        event_dispatcher: EventDispatcher,
        channels_properties_repository: ChannelPropertiesRepository,
        channel_property_state_repository: ChannelPropertiesStatesRepository,
    ) -> None:
        self.__items = {}

        self.__event_dispatcher = event_dispatcher

        self.__channels_properties_repository = channels_properties_repository
        self.__channel_property_state_repository = channel_property_state_repository

    # -----------------------------------------------------------------------------

    def get_by_id(self, register_id: uuid.UUID) -> Optional[RegisterRecord]:
        """Find register in registry by given unique identifier"""
        items = self.__items.copy()

        return next(
            iter([record for record in items.values() if register_id.__eq__(record.id)]),
            None,
        )

    # -----------------------------------------------------------------------------

    def get_by_address(
        self,
        device_id: uuid.UUID,
        register_type: RegisterType,
        register_address: int,
    ) -> Optional[RegisterRecord]:
        """Get register by its address"""
        items = self.__items.copy()

        return next(
            iter(
                [
                    record
                    for record in items.values()
                    if device_id.__eq__(record.device_id)
                    and record.address == register_address
                    and (
                        (register_type == RegisterType.DISCRETE and isinstance(record, DiscreteRegister))
                        or (register_type == RegisterType.COIL and isinstance(record, CoilRegister))
                        or (register_type == RegisterType.INPUT and isinstance(record, InputRegister))
                        or (register_type == RegisterType.HOLDING and isinstance(record, HoldingRegister))
                    )
                ]
            ),
            None,
        )

    # -----------------------------------------------------------------------------

    def get_all_for_device(
        self,
        device_id: uuid.UUID,
        register_type: Union[RegisterType, List[RegisterType]],
    ) -> Sequence[RegisterRecord]:
        """Find registers in registry by device unique identifier and register type"""
        items = self.__items.copy()

        registers = [
            record
            for record in items.values()
            if device_id.__eq__(record.device_id)
            and (
                (
                    isinstance(register_type, RegisterType)
                    and (
                        (register_type == RegisterType.DISCRETE and isinstance(record, DiscreteRegister))
                        or (register_type == RegisterType.COIL and isinstance(record, CoilRegister))
                        or (register_type == RegisterType.INPUT and isinstance(record, InputRegister))
                        or (register_type == RegisterType.HOLDING and isinstance(record, HoldingRegister))
                    )
                )
                or (
                    isinstance(register_type, list)
                    and (
                        (isinstance(record, DiscreteRegister) and RegisterType.DISCRETE in register_type)
                        or (isinstance(record, CoilRegister) and RegisterType.COIL in register_type)
                        or (isinstance(record, InputRegister) and RegisterType.INPUT in register_type)
                        or (isinstance(record, HoldingRegister) and RegisterType.HOLDING in register_type)
                    )
                )
            )
        ]

        registers.sort(key=lambda r: r.address)

        return registers

    # -----------------------------------------------------------------------------

    def append_discrete(  # pylint: disable=too-many-arguments
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
    ) -> DiscreteRegister:
        """Append register record into registry"""
        existing_register = self.get_by_id(register_id=register_id)

        register_record: DiscreteRegister = DiscreteRegister(
            device_id=device_id,
            register_id=register_id,
            register_address=register_address,
            register_format=register_format,
            register_invalid=register_invalid,
            channel_id=channel_id,
        )

        if existing_register is None:
            try:
                channel_property = self.__channels_properties_repository.get_by_id(property_id=register_id)

                if channel_property is not None:
                    stored_state = self.__channel_property_state_repository.get_by_id(property_id=channel_property.id)

                    if stored_state is not None:
                        register_record.actual_value = stored_state.actual_value
                        register_record.expected_value = stored_state.expected_value
                        register_record.expected_pending = stored_state.pending

            except (NotImplementedError, AttributeError):
                pass

        self.__items[register_record.id.__str__()] = register_record

        return register_record

    # -----------------------------------------------------------------------------

    def append_coil(  # pylint: disable=too-many-arguments
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
    ) -> CoilRegister:
        """Append register record into registry"""
        existing_register = self.get_by_id(register_id=register_id)

        register_record: CoilRegister = CoilRegister(
            device_id=device_id,
            register_id=register_id,
            register_address=register_address,
            register_format=register_format,
            register_invalid=register_invalid,
            channel_id=channel_id,
        )

        if existing_register is None:
            try:
                channel_property = self.__channels_properties_repository.get_by_id(property_id=register_id)

                if channel_property is not None:
                    stored_state = self.__channel_property_state_repository.get_by_id(property_id=channel_property.id)

                    if stored_state is not None:
                        register_record.actual_value = stored_state.actual_value
                        register_record.expected_value = stored_state.expected_value
                        register_record.expected_pending = stored_state.pending

            except (NotImplementedError, AttributeError):
                pass

        self.__items[register_record.id.__str__()] = register_record

        return register_record

    # -----------------------------------------------------------------------------

    def append_input(  # pylint: disable=too-many-arguments
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
    ) -> InputRegister:
        """Append register record into registry"""
        existing_register = self.get_by_id(register_id=register_id)

        register_record: InputRegister = InputRegister(
            device_id=device_id,
            register_id=register_id,
            register_address=register_address,
            register_data_type=register_data_type,
            register_format=register_format,
            register_invalid=register_invalid,
            register_number_of_decimals=register_number_of_decimals,
            channel_id=channel_id,
        )

        if existing_register is None:
            try:
                channel_property = self.__channels_properties_repository.get_by_id(property_id=register_id)

                if channel_property is not None:
                    stored_state = self.__channel_property_state_repository.get_by_id(property_id=channel_property.id)

                    if stored_state is not None:
                        register_record.actual_value = stored_state.actual_value
                        register_record.expected_value = stored_state.expected_value
                        register_record.expected_pending = stored_state.pending

            except (NotImplementedError, AttributeError):
                pass

        self.__items[register_record.id.__str__()] = register_record

        return register_record

    # -----------------------------------------------------------------------------

    def append_holding(  # pylint: disable=too-many-arguments
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
    ) -> HoldingRegister:
        """Append register record into registry"""
        existing_register = self.get_by_id(register_id=register_id)

        register_record: HoldingRegister = HoldingRegister(
            device_id=device_id,
            register_id=register_id,
            register_address=register_address,
            register_data_type=register_data_type,
            register_format=register_format,
            register_invalid=register_invalid,
            register_number_of_decimals=register_number_of_decimals,
            channel_id=channel_id,
        )

        if existing_register is None:
            try:
                channel_property = self.__channels_properties_repository.get_by_id(property_id=register_id)

                if channel_property is not None:
                    stored_state = self.__channel_property_state_repository.get_by_id(property_id=channel_property.id)

                    if stored_state is not None:
                        register_record.actual_value = stored_state.actual_value
                        register_record.expected_value = stored_state.expected_value
                        register_record.expected_pending = stored_state.pending

            except (NotImplementedError, AttributeError):
                pass

        self.__items[register_record.id.__str__()] = register_record

        return register_record

    # -----------------------------------------------------------------------------

    def remove(self, register_id: uuid.UUID) -> None:
        """Remove register from registry"""
        items = self.__items.copy()

        for record in items.values():
            if register_id.__eq__(record.id):
                try:
                    del self.__items[record.id.__str__()]

                except KeyError:
                    pass

                break

    # -----------------------------------------------------------------------------

    def reset(self, device_id: Optional[uuid.UUID] = None, registers_type: Optional[RegisterType] = None) -> None:
        """Reset registers registry to initial state"""
        items = self.__items.copy()

        if device_id is not None:
            for record in items.values():
                if device_id.__eq__(record.device_id) and (  # pylint: disable=too-many-boolean-expressions
                    registers_type is None
                    or (
                        (registers_type == RegisterType.DISCRETE and isinstance(record, DiscreteRegister))
                        or (registers_type == RegisterType.COIL and isinstance(record, CoilRegister))
                        or (registers_type == RegisterType.INPUT and isinstance(record, InputRegister))
                        or (registers_type == RegisterType.HOLDING and isinstance(record, HoldingRegister))
                    )
                ):
                    self.remove(register_id=record.id)

        elif registers_type is not None:
            for record in items.values():
                if (
                    (  # pylint: disable=too-many-boolean-expressions
                        registers_type == RegisterType.DISCRETE and isinstance(record, DiscreteRegister)
                    )
                    or (registers_type == RegisterType.COIL and isinstance(record, CoilRegister))
                    or (registers_type == RegisterType.INPUT and isinstance(record, InputRegister))
                    or (registers_type == RegisterType.HOLDING and isinstance(record, HoldingRegister))
                ):
                    self.remove(register_id=record.id)

        else:
            self.__items = {}

    # -----------------------------------------------------------------------------

    def set_actual_value(
        self,
        register: RegisterRecord,
        value: Union[str, int, float, bool, SwitchPayload, None],
    ) -> RegisterRecord:
        """Set register actual value"""
        existing_record = self.get_by_id(register_id=register.id)

        register.actual_value = value
        register.actual_value_valid = True

        self.__update(register=register)

        updated_register = self.get_by_id(register_id=register.id)

        if updated_register is None:
            raise InvalidStateException("Register record could not be re-fetched from registry after update")

        self.__event_dispatcher.dispatch(
            event_id=RegisterActualValueEvent.EVENT_NAME,
            event=RegisterActualValueEvent(
                original_record=existing_record,
                updated_record=updated_register,
            ),
        )

        return updated_register

    # -----------------------------------------------------------------------------

    def set_expected_value(
        self,
        register: RegisterRecord,
        value: Union[str, int, float, bool, SwitchPayload, None],
    ) -> RegisterRecord:
        """Set register expected value"""
        existing_record = self.get_by_id(register_id=register.id)

        register.expected_value = value

        self.__update(register=register)

        updated_register = self.get_by_id(register.id)

        if updated_register is None:
            raise InvalidStateException("Register record could not be re-fetched from registry after update")

        self.__event_dispatcher.dispatch(
            event_id=RegisterActualValueEvent.EVENT_NAME,
            event=RegisterActualValueEvent(
                original_record=existing_record,
                updated_record=updated_register,
            ),
        )

        return updated_register

    # -----------------------------------------------------------------------------

    def set_expected_pending(self, register: RegisterRecord, timestamp: float) -> RegisterRecord:
        """Set register expected value transmit timestamp"""
        register.expected_pending = timestamp

        self.__update(register=register)

        updated_register = self.get_by_id(register.id)

        if updated_register is None:
            raise InvalidStateException("Register record could not be re-fetched from registry after update")

        return updated_register

    # -----------------------------------------------------------------------------

    def set_valid_state(self, register: RegisterRecord, state: bool) -> RegisterRecord:
        """Set register actual value reading state"""
        existing_record = self.get_by_id(register_id=register.id)

        register.actual_value_valid = state

        self.__update(register=register)

        updated_register = self.get_by_id(register.id)

        if updated_register is None:
            raise InvalidStateException("Register record could not be re-fetched from registry after update")

        self.__event_dispatcher.dispatch(
            event_id=RegisterActualValueEvent.EVENT_NAME,
            event=RegisterActualValueEvent(
                original_record=existing_record,
                updated_record=updated_register,
            ),
        )

        return updated_register

    # -----------------------------------------------------------------------------

    def __update(self, register: RegisterRecord) -> bool:
        items = self.__items.copy()

        for record in items.values():
            if record.id == register.id:
                self.__items[register.id.__str__()] = register

                return True

        return False

    # -----------------------------------------------------------------------------

    def __iter__(self) -> "RegistersRegistry":
        # Reset index for nex iteration
        self.__iterator_index = 0

        return self

    # -----------------------------------------------------------------------------

    def __len__(self) -> int:
        return len(self.__items.values())

    # -----------------------------------------------------------------------------

    def __next__(self) -> RegisterRecord:
        if self.__iterator_index < len(self.__items.values()):
            items: List[RegisterRecord] = list(self.__items.values())

            result: RegisterRecord = items[self.__iterator_index]

            self.__iterator_index += 1

            return result

        # Reset index for nex iteration
        self.__iterator_index = 0

        # End of iteration
        raise StopIteration


@inject
class AttributesRegistry:
    """
    Attributes registry

    @package        FastyBird:ModbusConnector!
    @module         registry/model

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __items: Dict[str, AttributeRecord] = {}

    __event_dispatcher: EventDispatcher

    __device_property_state_repository: DevicePropertiesStatesRepository

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        event_dispatcher: EventDispatcher,
        device_property_state_repository: DevicePropertiesStatesRepository,
    ) -> None:
        self.__items = {}

        self.__event_dispatcher = event_dispatcher
        self.__device_property_state_repository = device_property_state_repository

    # -----------------------------------------------------------------------------

    def get_by_id(self, attribute_id: uuid.UUID) -> Optional[AttributeRecord]:
        """Find attribute in registry by given unique identifier"""
        items = self.__items.copy()

        return next(
            iter([record for record in items.values() if attribute_id.__eq__(record.id)]),
            None,
        )

    # -----------------------------------------------------------------------------

    def get_by_attribute(self, device_id: uuid.UUID, attribute_type: DeviceAttribute) -> Optional[AttributeRecord]:
        """Find device attribute in registry by given unique type in given device"""
        items = self.__items.copy()

        return next(
            iter(
                [
                    record
                    for record in items.values()
                    if device_id.__eq__(record.device_id) and record.type == attribute_type
                ]
            ),
            None,
        )

    # -----------------------------------------------------------------------------

    def get_all_by_device(self, device_id: uuid.UUID) -> List[AttributeRecord]:
        """Get all device attributes"""
        items = self.__items.copy()

        return list(iter([record for record in items.values() if device_id.__eq__(record.device_id)]))

    # -----------------------------------------------------------------------------

    def get_all_by_type(self, attribute_type: DeviceAttribute) -> List[AttributeRecord]:
        """Get all attributes by given type"""
        items = self.__items.copy()

        return list(iter([record for record in items.values() if record.type == attribute_type]))

    # -----------------------------------------------------------------------------

    def append(  # pylint: disable=too-many-arguments
        self,
        device_id: uuid.UUID,
        attribute_id: uuid.UUID,
        attribute_type: DeviceAttribute,
        attribute_value: Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None] = None,
    ) -> AttributeRecord:
        """Append new device record"""
        existing_attribute = self.get_by_id(attribute_id=attribute_id)

        attribute_record = AttributeRecord(
            device_id=device_id,
            attribute_id=attribute_id,
            attribute_type=attribute_type,
            attribute_value=attribute_value,
        )

        if existing_attribute is None:
            if attribute_value is None:
                try:
                    stored_state = self.__device_property_state_repository.get_by_id(property_id=attribute_id)

                    if stored_state is not None:
                        attribute_record.actual_value = stored_state.actual_value

                    else:
                        attribute_record.actual_value = None

                except (NotImplementedError, AttributeError):
                    pass

            else:
                attribute_record.actual_value = attribute_value

        else:
            if attribute_value is None:
                attribute_record.actual_value = existing_attribute.actual_value

            else:
                attribute_record.actual_value = attribute_value

        self.__items[attribute_record.id.__str__()] = attribute_record

        return attribute_record

    # -----------------------------------------------------------------------------

    def remove(self, attribute_id: uuid.UUID) -> None:
        """Remove device attribute from registry"""
        items = self.__items.copy()

        for record in items.values():
            if attribute_id.__eq__(record.id):
                try:
                    del self.__items[record.id.__str__()]

                except KeyError:
                    pass

                break

    # -----------------------------------------------------------------------------

    def reset(self, device_id: Optional[uuid.UUID] = None) -> None:
        """Reset devices attributes registry to initial state"""
        items = self.__items.copy()

        if device_id is not None:
            for record in items.values():
                if device_id.__eq__(record.device_id):
                    try:
                        self.remove(attribute_id=record.id)

                    except KeyError:
                        pass

        else:
            self.__items = {}

    # -----------------------------------------------------------------------------

    def set_value(
        self,
        attribute: AttributeRecord,
        value: Union[str, bool, None],
    ) -> AttributeRecord:
        """Set attribute value"""
        existing_record = self.get_by_id(attribute_id=attribute.id)

        attribute.actual_value = value

        self.__update(attribute=attribute)

        updated_attribute = self.get_by_id(attribute_id=attribute.id)

        if updated_attribute is None:
            raise InvalidStateException("Attribute record could not be re-fetched from registry after update")

        self.__event_dispatcher.dispatch(
            event_id=AttributeActualValueEvent.EVENT_NAME,
            event=AttributeActualValueEvent(
                original_record=existing_record,
                updated_record=updated_attribute,
            ),
        )

        return updated_attribute

    # -----------------------------------------------------------------------------

    def __update(self, attribute: AttributeRecord) -> bool:
        items = self.__items.copy()

        for record in items.values():
            if record.id == attribute.id:
                self.__items[attribute.id.__str__()] = attribute

                return True

        return False
