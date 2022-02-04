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
Modbus connector entities module
"""

# Library dependencies
from typing import Dict, List, Optional, Union

from fastybird_devices_module.entities.connector import ConnectorEntity
from fastybird_devices_module.entities.device import DeviceEntity

# Library libs
from fastybird_modbus_connector.types import CONNECTOR_NAME, DEVICE_NAME


class ModbusConnectorEntity(ConnectorEntity):
    """
    Modbus connector entity

    @package        FastyBird:ModbusConnector!
    @module         entities

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __mapper_args__ = {"polymorphic_identity": CONNECTOR_NAME}

    # -----------------------------------------------------------------------------

    @property
    def type(self) -> str:
        """Connector type"""
        return CONNECTOR_NAME

    @property
    def interface(self) -> str:
        """Connector serial interface"""
        return (
            str(self.params.get("interface", None))
            if self.params is not None and self.params.get("interface") is not None
            else "/dev/ttyAMA0"
        )

    # -----------------------------------------------------------------------------

    @interface.setter
    def interface(self, interface: Optional[str]) -> None:
        """Connector serial interface setter"""
        if self.params is not None and bool(self.params) is True:
            self.params["interface"] = interface

        else:
            self.params = {"interface": interface}

    # -----------------------------------------------------------------------------

    @property
    def baud_rate(self) -> int:
        """Connector communication baud rate"""
        return (
            int(str(self.params.get("baud_rate", 9600)))
            if self.params is not None and self.params.get("baud_rate", 9600) is not None
            else 9600
        )

    # -----------------------------------------------------------------------------

    @baud_rate.setter
    def baud_rate(self, baud_rate: Optional[int]) -> None:
        """Connector communication baud rate setter"""
        if self.params is not None and bool(self.params) is True:
            self.params["baud_rate"] = baud_rate

        else:
            self.params = {"baud_rate": baud_rate}

    # -----------------------------------------------------------------------------

    def to_dict(self) -> Dict[str, Union[str, int, bool, List[str], None]]:
        """Transform entity to dictionary"""
        return {
            **super().to_dict(),
            **{
                "interface": self.interface,
                "baud_rate": self.baud_rate,
            },
        }


class ModbusDeviceEntity(DeviceEntity):  # pylint: disable=too-few-public-methods
    """
    Modbus device entity

    @package        FastyBird:ModbusConnector!
    @module         entities

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __mapper_args__ = {"polymorphic_identity": DEVICE_NAME}

    # -----------------------------------------------------------------------------

    @property
    def type(self) -> str:
        """Device type"""
        return DEVICE_NAME
