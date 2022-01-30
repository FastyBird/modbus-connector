PARITY_NONE, PARITY_EVEN, PARITY_ODD, PARITY_MARK, PARITY_SPACE = 'N', 'E', 'O', 'M', 'S'

class Serial:
    baudrate: int
    bytesize: int
    parity: str
    stopbits: float
    timeout: float

