from typing import Union, List

from serial import Serial

MODE_RTU: str = "rtu"
BYTEORDER_BIG: int = 0

class Instrument:
    serial: Serial
    address: int

    def __init__(
        self,
        port: str,
        slaveaddress: int,
        mode: str = MODE_RTU,
        close_port_after_each_call: bool = False,
        debug: bool = False,
    ) -> None: ...

    def write_bit(
        self,
        registeraddress: int,
        value: int,
        functioncode: int = 5,
    ) -> None: ...

    def write_float(
        self,
        registeraddress: int,
        value: Union[int, float],
        number_of_registers: int = 2,
        byteorder: int = BYTEORDER_BIG,
    ) -> None: ...

    def read_bits(
        self,
        registeraddress: int,
        number_of_bits: int,
        functioncode: int = 2,
    ) -> List[int]: ...

    def read_registers(
        self,
        registeraddress: int,
        number_of_registers: int,
        functioncode: int = 3,
    ) -> List[int]: ...

class ModbusException(IOError): ...

class MasterReportedException(ModbusException): ...

class NoResponseError(MasterReportedException): ...
