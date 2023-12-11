<p align="center">
	<img src="https://github.com/fastybird/.github/blob/main/assets/repo_title.png?raw=true" alt="FastyBird"/>
</p>

The [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) Modbus Connector is an extension for the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem that enables seamless integration
with [Modbus](https://en.wikipedia.org/wiki/Modbus) devices. It allows users to easily connect and control [Modbus](https://en.wikipedia.org/wiki/Modbus) devices from within the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem,
providing a simple and user-friendly interface for managing and monitoring your devices.

# Naming Convention

The connector uses the following naming convention for its entities:

## Connector

A connector is an entity that manages communication with [Modbus](https://en.wikipedia.org/wiki/Modbus) devices. It needs to be configured for a specific device interface.

There are two types of connectors interfaces supported:

- **RTU** - This connector uses serial to RS485 converter usually connected via USB port.
- **TCP/IP** - This connector communicates with the [Modbus](https://en.wikipedia.org/wiki/Modbus) via LAN or WAN network.

## Device

A device is an entity that represents a physical [Modbus](https://en.wikipedia.org/wiki/Modbus) device.

## Register

A register represent data storage in physical device. The connector read and write value to device registers.

## Device Interface

The connector supports two device interfaces, the first being RS485 on twisted wire lines and the second being TCP/IP via standard network communication.

# Configuration

To use [Modbus](https://en.wikipedia.org/wiki/Modbus) devices with the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem, you will need to configure at least one connector.
The connector can be configured using the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) user interface or through the console.

## Configuring the Connectors, Devices and Registers through the Console

To configure the connector through the console, run the following command:

```shell
php bin/fb-console fb:modbus-connector:install
```

> **NOTE:**
The path to the console command may vary depending on your FastyBird application distribution. For more information, refer to the FastyBird documentation.

The console will show you basic menu. To navigate in menu you could write value displayed in square brackets or you
could use arrows to select one of the options:

```shell
Modbus connector - installer
============================

 ! [NOTE] This action will create|update|delete connector configuration                                                 

 What would you like to do? [Nothing]:
  [0] Create connector
  [1] Edit connector
  [2] Delete connector
  [3] Manage connector
  [4] List connectors
  [5] Nothing
 > 0
```

### Create connector

If you choose to create a new connector, you will be asked to choose the mode in which the connector will communicate with the devices:

```shell
 In what mode should this connector communicate with Modbus devices? [Modbus devices over serial line]:
  [0] Modbus devices over serial line
  [1] Modbus devices over TCP network
 > 0
```

You will then be asked to provide a connector identifier and name:

```shell
 Provide connector identifier:
 > my-modbus
```

```shell
 Provide connector name:
 > My Modbus
```

> **NOTE:**
If you choose the RTU mode, you will be prompted to provide path to the serial interface. Something like `/dev/ttyUSB0`

> **NOTE:**
For both connector types you will be prompted to provide another communication settings like baud rate, byte size etc.
You have to use same configuration as your devices use. 

After providing the necessary information, your new [Modbus](https://en.wikipedia.org/wiki/Modbus) connector will be ready for use.

```shell
 [OK] New connector "My Modbus" was successfully created                                                                
```

### Create device

After new connector is created you will be asked if you want to create new device:

```shell
 Would you like to configure connector device(s)? (yes/no) [yes]:
 > 
```

Or you could choose to manage connector devices from the main menu.

Now you will be asked to provide some device details:

```shell
 Provide device identifier:
 > first-device
```

```shell
 Provide device name:
 > First device - temperature & humidity
```

```shell
 Provide device station address:
 > 1
```

```shell
 What byte order device uses? [Big-Endian]:
  [0] Big-Endian
  [1] Swapped Big-Endian
  [2] Little-Endian
  [3] Swappd Little-Endian
 > 0
```

> **NOTE:**
The byte order of your device is dependent on its specifications. To determine the appropriate byte order, consult it with your device manual.
If you are unsure, the default option of "BIG" is commonly used.

If there are no errors, you will receive a success message.

```shell
 [OK] Device "First device - temperature & humidity" was successfully created
```

### Create registers

Each device have to have defined registers - value storages. So in next steps you will be prompted to configure device's registers.

```shell
 Would you like to configure device register(s)? (yes/no) [yes]:
 > y
```

Now you will be asked to provide some device registers details:

```shell
 What type of device register you would like to add? [Discrete Input]:
  [0] Discrete Input
  [1] Coil
  [2] Input Register
  [3] Holding Register
 > 2
```

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
  [8] button
 > 2
```

```shell
Provide register address. It could be single number or range like 1-2:
> 1
```

```shell
Provide register name (optional):
> Temperature
```

```shell
Provide register sampling time (s) [120]:
> 120
```

If there are no errors, you will receive a success message.

```shell
 [OK] Device register was successfully created
```

If your device have more registers, you could continue answering and configuring another registers.

### Connectors, Devices and Registers management

With this console command you could manage all your connectors, their devices and registers. Just use the main menu to navigate to proper action.

# Troubleshooting

## Combined Data Types

In instances where some devices utilize different data types for reading and writing values, a special data type `SWITCH` can be used.

For instance, a Chinese relay device could utilize binary representations when retrieving the relay state and integer values for adjusting the state.
To accommodate this, a specialized format can be set up. For more information, refer to the relevant relay type's [page](https://github.com/FastyBird/modbus-connector/wiki/RelayBoard).

# Known Issues and Limitations

This connector only supports boolean and numerical values, with a maximum byte size for numbers of 4 bytes,
meaning the value is comprised of 2 registers.
