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
Modbus connector client
"""

# Python base dependencies
import logging
from typing import Set, Union

# Library libs
from fastybird_modbus_connector.clients.base import IClient
from fastybird_modbus_connector.clients.serial import SerialClient
from fastybird_modbus_connector.logger import Logger
from fastybird_modbus_connector.registry.model import DevicesRegistry, RegistersRegistry


class Client:
    """
    Clients proxy

    @package        FastyBird:ModbusConnector!
    @module         clients/client

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __clients: Set[IClient]

    __devices_registry: DevicesRegistry
    __registers_registry: RegistersRegistry

    __logger: Union[Logger, logging.Logger]

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        devices_registry: DevicesRegistry,
        registers_registry: RegistersRegistry,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
    ) -> None:
        self.__clients = set()

        self.__devices_registry = devices_registry
        self.__registers_registry = registers_registry

        self.__logger = logger

    # -----------------------------------------------------------------------------

    def initialize(
        self,
        baud_rate: int,
        interface: str,
    ) -> None:
        """Register new client to proxy"""
        self.__clients.add(
            SerialClient(
                client_baud_rate=baud_rate,
                client_interface=interface,
                devices_registry=self.__devices_registry,
                registers_registry=self.__registers_registry,
                logger=self.__logger,
            )
        )

    # -----------------------------------------------------------------------------

    def handle(self) -> None:
        """Handle communication from client"""
        for client in self.__clients:
            client.handle()
