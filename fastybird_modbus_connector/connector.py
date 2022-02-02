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
Modbus connector module
"""

# Python base dependencies
import logging
import uuid
from typing import Dict, List, Optional, Tuple, Union

# App dependencies
from fastybird_devices_module.connectors.connector import IConnector
from fastybird_devices_module.entities.channel import (
    ChannelControlEntity,
    ChannelDynamicPropertyEntity,
    ChannelEntity,
    ChannelPropertyEntity,
    ChannelStaticPropertyEntity,
)
from fastybird_devices_module.entities.connector import ConnectorControlEntity
from fastybird_devices_module.entities.device import (
    DeviceControlEntity,
    DeviceEntity,
    DevicePropertyEntity,
    DeviceStaticPropertyEntity,
)
from fastybird_devices_module.repositories.device import DevicesRepository
from fastybird_metadata.devices_module import ConnectionState
from fastybird_metadata.helpers import normalize_value
from fastybird_metadata.types import ControlAction, DataType
from kink import inject

# App libs
from fastybird_modbus_connector.clients.client import IClient
from fastybird_modbus_connector.entities import ModbusDeviceEntity
from fastybird_modbus_connector.events.listeners import EventsListener
from fastybird_modbus_connector.logger import Logger
from fastybird_modbus_connector.registry.model import (
    AttributesRegistry,
    DevicesRegistry,
    RegistersRegistry,
)
from fastybird_modbus_connector.types import DeviceAttribute, RegisterAttribute


@inject(alias=IConnector)
class ModbusConnector(IConnector):  # pylint: disable=too-many-public-methods,too-many-instance-attributes
    """
    Modbus connector service

    @package        FastyBird:ModbusConnector!
    @module         connector

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __stopped: bool = False

    __connector_id: uuid.UUID

    __devices_repository: DevicesRepository

    __devices_registry: DevicesRegistry
    __attributes_registry: AttributesRegistry
    __registers_registry: RegistersRegistry

    __client: IClient

    __events_listener: EventsListener

    __logger: Union[Logger, logging.Logger]

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        connector_id: uuid.UUID,
        devices_repository: DevicesRepository,
        attributes_registry: AttributesRegistry,
        devices_registry: DevicesRegistry,
        registers_registry: RegistersRegistry,
        client: IClient,
        events_listener: EventsListener,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
    ) -> None:
        self.__connector_id = connector_id

        self.__devices_repository = devices_repository

        self.__devices_registry = devices_registry
        self.__attributes_registry = attributes_registry
        self.__registers_registry = registers_registry

        self.__client = client

        self.__events_listener = events_listener

        self.__logger = logger

    # -----------------------------------------------------------------------------

    def initialize(self, settings: Optional[Dict] = None) -> None:
        """Set connector to initial state"""
        self.__devices_registry.reset()

        for device in self.__devices_repository.get_all_by_connector(connector_id=self.__connector_id):
            self.initialize_device(device=device)

    # -----------------------------------------------------------------------------

    def initialize_device(self, device: ModbusDeviceEntity) -> None:
        """Initialize device in connector registry"""
        device_record = self.__devices_registry.append(
            device_id=device.id,
            device_enabled=False,
        )

        for device_property in device.properties:
            self.initialize_device_property(device_property=device_property)

        for channel in device.channels:
            self.initialize_device_channel(channel=channel)

        if device.enabled:
            self.__devices_registry.enable(device=device_record)

    # -----------------------------------------------------------------------------

    def remove_device(self, device_id: uuid.UUID) -> None:
        """Remove device from connector registry"""
        self.__devices_registry.remove(device_id=device_id)

    # -----------------------------------------------------------------------------

    def reset_devices(self) -> None:
        """Reset devices registry to initial state"""
        self.__devices_registry.reset()

    # -----------------------------------------------------------------------------

    def initialize_device_property(self, device_property: DevicePropertyEntity) -> None:
        """Initialize device property in connector registry"""
        if not isinstance(device_property, DeviceStaticPropertyEntity):
            return

        if not DeviceAttribute.has_value(device_property.identifier):
            return

        attribute_record = self.__attributes_registry.append(
            device_id=device_property.device.id,
            attribute_id=device_property.id,
            attribute_type=DeviceAttribute(device_property.identifier),
            attribute_value=device_property.value,
        )

        if device_property.identifier == DeviceAttribute.STATE.value:
            self.__attributes_registry.set_value(attribute=attribute_record, value=ConnectionState.UNKNOWN.value)

    # -----------------------------------------------------------------------------

    def remove_device_property(self, property_id: uuid.UUID) -> None:
        """Remove device from connector registry"""
        self.__attributes_registry.remove(attribute_id=property_id)

    # -----------------------------------------------------------------------------

    def reset_devices_properties(self, device: DeviceEntity) -> None:
        """Reset devices properties registry to initial state"""
        self.__attributes_registry.reset(device_id=device.id)

    # -----------------------------------------------------------------------------

    def initialize_device_channel(self, channel: ChannelEntity) -> None:
        """Initialize device channel aka registers group in connector registry"""
        register_id: Optional[uuid.UUID] = None
        register_address: Optional[int] = None
        register_data_type: Optional[DataType] = None
        register_settable: bool = False
        register_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ] = None
        register_number_of_decimals: Optional[int] = None

        for channel_property in channel.properties:
            if (
                channel_property.identifier == RegisterAttribute.ADDRESS.value
                and isinstance(channel_property, ChannelStaticPropertyEntity)
                and isinstance(channel_property.value, int)
            ):
                register_address = channel_property.value

            if channel_property.identifier == RegisterAttribute.VALUE.value and isinstance(
                channel_property, ChannelDynamicPropertyEntity
            ):
                register_id = channel_property.id
                register_data_type = channel_property.data_type
                register_settable = channel_property.settable
                register_format = channel_property.format
                register_number_of_decimals = channel_property.number_of_decimals

        if register_id is None or register_address is None or register_data_type is None:
            self.__logger.warning(
                "Channel does not have expected properties and can't be mapped to register",
                extra={
                    "device": {
                        "id": channel.device.id.__str__(),
                    },
                    "channel": {
                        "id": channel.device.id.__str__(),
                    },
                },
            )

            return

        if register_settable:
            if register_data_type == DataType.BOOLEAN:
                self.__registers_registry.append_coil(
                    device_id=channel.device.id,
                    register_id=register_id,
                    register_address=register_address,
                    register_format=register_format,
                )

            else:
                self.__registers_registry.append_holding(
                    device_id=channel.device.id,
                    register_id=register_id,
                    register_address=register_address,
                    register_data_type=register_data_type,
                    register_format=register_format,
                    register_number_of_decimals=register_number_of_decimals,
                )

        else:
            if register_data_type == DataType.BOOLEAN:
                self.__registers_registry.append_discrete(
                    device_id=channel.device.id,
                    register_id=register_id,
                    register_address=register_address,
                    register_format=register_format,
                )

            else:
                self.__registers_registry.append_input(
                    device_id=channel.device.id,
                    register_id=register_id,
                    register_address=register_address,
                    register_data_type=register_data_type,
                    register_format=register_format,
                    register_number_of_decimals=register_number_of_decimals,
                )

    # -----------------------------------------------------------------------------

    def remove_device_channel(self, channel_id: uuid.UUID) -> None:
        """Remove device channel from connector registry"""
        self.__registers_registry.remove(register_id=channel_id)

    # -----------------------------------------------------------------------------

    def reset_devices_channels(self, device: DeviceEntity) -> None:
        """Reset devices channels registry to initial state"""
        self.__registers_registry.reset(device_id=device.id)

    # -----------------------------------------------------------------------------

    def initialize_device_channel_property(self, channel_property: ChannelPropertyEntity) -> None:
        """Initialize device channel property aka input or output register in connector registry"""

    # -----------------------------------------------------------------------------

    def remove_device_channel_property(self, property_id: uuid.UUID) -> None:
        """Remove device channel property from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_channels_properties(self, channel: ChannelEntity) -> None:
        """Reset devices channels properties registry to initial state"""

    # -----------------------------------------------------------------------------

    def start(self) -> None:
        """Start connector services"""
        # When connector is starting...
        self.__events_listener.open()

        for device in self.__devices_registry:
            # ...set device state to unknown
            self.__devices_registry.set_state(device=device, state=ConnectionState.UNKNOWN)

        self.__logger.info("Connector has been started")

        self.__stopped = False

    # -----------------------------------------------------------------------------

    def stop(self) -> None:
        """Close all opened connections & stop connector"""
        # When connector is closing...
        for device in self.__devices_registry:
            # ...set device state to disconnected
            self.__devices_registry.set_state(device=device, state=ConnectionState.DISCONNECTED)

        self.__events_listener.close()

        self.__logger.info("Connector has been stopped")

        self.__stopped = True

    # -----------------------------------------------------------------------------

    def has_unfinished_tasks(self) -> bool:
        """Check if connector has some unfinished task"""
        return False

    # -----------------------------------------------------------------------------

    def handle(self) -> None:
        """Run connector service"""
        if self.__stopped and not self.has_unfinished_tasks():
            self.__logger.warning("Connector is stopped and can't process another requests")

            return

        if self.__stopped:
            return

        self.__client.handle()

    # -----------------------------------------------------------------------------

    def write_property(self, property_item: Union[DevicePropertyEntity, ChannelPropertyEntity], data: Dict) -> None:
        """Write device or channel property value to device"""
        if self.__stopped:
            self.__logger.warning("Connector is stopped, value can't be written")

            return

        if isinstance(property_item, ChannelDynamicPropertyEntity):
            register_record = self.__registers_registry.get_by_id(register_id=property_item.id)

            if register_record is None:
                return

            if property_item.data_type is not None:
                value_to_write = normalize_value(
                    data_type=property_item.data_type,
                    value=data.get("expected_value", None),
                    value_format=property_item.format,
                )

            else:
                value_to_write = data.get("expected_value", None)

            if isinstance(value_to_write, (int, float, bool)) or value_to_write is None:
                self.__registers_registry.set_expected_value(register=register_record, value=value_to_write)

                return

    # -----------------------------------------------------------------------------

    def write_control(
        self,
        control_item: Union[ConnectorControlEntity, DeviceControlEntity, ChannelControlEntity],
        data: Optional[Dict],
        action: ControlAction,
    ) -> None:
        """Write connector control action"""
