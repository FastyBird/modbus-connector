<p align="center">
	<img src="https://github.com/FastyBird/modbus-connector/blob/main/docs/_media/N4D8B08_relay.jpg" alt="Eletechsup N4D8B08 relay"/>
</p>

# Eletechsup N4D8B08 - multifunction RS485 relay

This device is used as example how to configure combined data type.

Device basic configuration according to manufacturer specification:

| Type            | Value |
|-----------------|:-----:|
| Baud rate       | 9600  |
| Byte size       |   8   |
| Stop bits       |   1   |
| Parity checking | None  |

## Device registers

This device has multiple register types for reading inputs, outputs and device configuration:

| Register address | Register contents | Data type |
|------------------|:-----------------:|:---------:|
| 1-8              |   Relay outputs   |  switch   |
| 81-88            |  Digital inputs   |  ushort   |
| 253              |    I/O Linking    |  ushort   |
| 254              |     Baud rate     |  ushort   |

Relay outputs registers have special data type which allow you to define read and write values separately.

### Configuring Relay Registers

When configuring relay registers you have to choose `Holding register`

```shell
 What type of device register you would like to add? [Discrete Input]:
  [0] Discrete Input
  [1] Coil
  [2] Input Register
  [3] Holding Register
 > 3
```

In the next step you have to choose `swtich` data type, which allow you to define different values for writing and reading.

```shell
 What type of data type this register has [char]:
  [0] char
  [1] uchar
  [2] short
  [3] ushort
  [4] int
  [5] uint
  [6] float
  [7] string
  [8] switch
 > 8
```

This device has 8 relay outputs with starting address for relays `1`

```shell
 Provide register address. It could be single number or range like 1-2:
 > 1-8
```

Reading sampling time is time between readings in seconds. Connector will repeatedly read relay status.

```shell
 Provide register sampling time (s) [120]:
 > 120
```

In the next steps will be configured read and write values.

```shell
 Does register support Switch ON action? (yes/no) [no]:
 > y
```

```shell
 Provide read value representing Switch ON:
 > 1
```

```shell
 What type of data type provided value has:
  [0] b
  [1] i8
  [2] u8
  [3] i16
  [4] u16
  [5] i32
  [6] u32
  [7] f
 > 2
```

```shell
 Provide write value representing Switch ON:
 > 256
```

```shell
 What type of data type provided value has:
  [0] b
  [1] i8
  [2] u8
  [3] i16
  [4] u16
  [5] i32
  [6] u32
  [7] f
 > 4
```

```shell
 Does register support Switch OFF action? (yes/no) [no]:
 > y
```

```shell
 Provide read value representing Switch OFF:
 > 0
```

```shell
 What type of data type provided value has:
  [0] b
  [1] i8
  [2] u8
  [3] i16
  [4] u16
  [5] i32
  [6] u32
  [7] f
 > 2
```

```shell
 Provide write value representing Switch OFF:
 > 512
```

```shell
 What type of data type provided value has:
  [0] b
  [1] i8
  [2] u8
  [3] i16
  [4] u16
  [5] i32
  [6] u32
  [7] f
 > 4
```

```shell
 Does register support Switch TOGGLE action? (yes/no) [no]:
 > y
```

```shell
 Provide read value representing Switch TOGGLE:
 >
```

```shell
 Provide write value representing Switch TOGGLE:
 > 768
```

```shell
 What type of data type provided value has:
  [0] b
  [1] i8
  [2] u8
  [3] i16
  [4] u16
  [5] i32
  [6] u32
  [7] f
 > 4
```

So in short.

When relay is turned on, connector will read value `1` from registers. When relay is turned off, connector will read value `0` from registers.

But when you want to turn on relay, you have to write to relay register value `256` and to turn off you have to write value `512`.

And this mapping is solved with special data type `switch` so from user interface, this relay device will show its relays status as `switch_on` or `switch_off`.