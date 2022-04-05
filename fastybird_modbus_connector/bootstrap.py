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
Modbus connector DI container
"""

# pylint: disable=no-value-for-parameter

# Python base dependencies
import logging

# Library dependencies
from kink import di
from whistle import EventDispatcher

# Library libs
from fastybird_modbus_connector.clients.serial import SerialClient
from fastybird_modbus_connector.connector import ModbusConnector
from fastybird_modbus_connector.entities import ModbusConnectorEntity
from fastybird_modbus_connector.events.listeners import EventsListener
from fastybird_modbus_connector.logger import Logger
from fastybird_modbus_connector.registry.model import (
    AttributesRegistry,
    DevicesRegistry,
    RegistersRegistry,
)


def create_connector(
    connector: ModbusConnectorEntity,
    logger: logging.Logger = logging.getLogger("dummy"),
) -> ModbusConnector:
    """Create Modbus connector services"""
    if isinstance(logger, logging.Logger):
        connector_logger = Logger(connector_id=connector.id, logger=logger)

        di[Logger] = connector_logger
        di["modbus-connector_logger"] = di[Logger]

    else:
        connector_logger = logger

    di[EventDispatcher] = EventDispatcher()
    di["modbus-connector_events-dispatcher"] = di[EventDispatcher]

    # Registers
    di[AttributesRegistry] = AttributesRegistry(event_dispatcher=di[EventDispatcher])  # type: ignore[call-arg]
    di["modbus-connector_attributes-registry"] = di[AttributesRegistry]
    di[RegistersRegistry] = RegistersRegistry(event_dispatcher=di[EventDispatcher])  # type: ignore[call-arg]
    di["modbus-connector_registers-registry"] = di[RegistersRegistry]
    di[DevicesRegistry] = DevicesRegistry(
        event_dispatcher=di[EventDispatcher],
        attributes_registry=di[AttributesRegistry],
        registers_registry=di[RegistersRegistry],
    )
    di["modbus-connector_devices-registry"] = di[DevicesRegistry]

    # Connector clients
    di[SerialClient] = SerialClient(
        baud_rate=connector.baud_rate,
        interface=connector.interface,
        devices_registry=di[DevicesRegistry],
        registers_registry=di[RegistersRegistry],
    )
    di["modbus-connector_client"] = di[SerialClient]

    # Inner events system
    di[EventsListener] = EventsListener(  # type: ignore[call-arg]
        event_dispatcher=di[EventDispatcher],
        logger=connector_logger,
    )
    di["modbus-connector_events-listener"] = di[EventsListener]

    # Main connector service
    connector_service = ModbusConnector(
        connector_id=connector.id,
        devices_registry=di[DevicesRegistry],
        attributes_registry=di[AttributesRegistry],
        registers_registry=di[RegistersRegistry],
        client=di[SerialClient],
        events_listener=di[EventsListener],
        logger=connector_logger,
    )
    di[ModbusConnector] = connector_service
    di["modbus-connector_connector"] = connector_service

    return connector_service
