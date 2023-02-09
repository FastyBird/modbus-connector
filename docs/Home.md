The [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) Modbus Connector is an extension for the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem that enables seamless integration
with [Modbus](https://en.wikipedia.org/wiki/Modbus) devices. It allows users to easily connect and control [Modbus](https://en.wikipedia.org/wiki/Modbus) devices from within the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem,
providing a simple and user-friendly interface for managing and monitoring your devices.

# Naming Convention

The connector uses the following naming convention for its entities:

## Connector

A connector is an entity that manages communication with [Modbus](https://en.wikipedia.org/wiki/Modbus) devices. It needs to be configured for a specific device interface.

## Device

A device is an entity that represents a physical [Modbus](https://en.wikipedia.org/wiki/Modbus) device.

## Device Interface

The connector supports two device interfaces, the first being RS485 on twisted wire lines and the second being TCP/IP via standard network communication.


# Configuration

To use [Modbus](https://en.wikipedia.org/wiki/Modbus) devices with the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem, you will need to configure at least one connector.
The connector can be configured using the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) user interface or through the console.

There are two types of connectors available for selection:

- **RTU** - This connector uses serial to RS485 converter usually connected via USB port.
- **TCP/IP** - This connector communicates with the [Modbus](https://en.wikipedia.org/wiki/Modbus) via local network.

## Configuring the Connector through the Console

To configure the connector through the console, run the following command:

```shell
php bin/fb-console fb:modbus-connector:initialize
```

> **NOTE:**
The path to the console command may vary depending on your FastyBird application distribution. For more information, refer to the FastyBird documentation.

The console will ask you to confirm that you want to continue with the configuration.

```shell
Modbus connector - initialization
=================================

 ! [NOTE] This action will create|update|delete connector configuration.                                                       

 Would you like to continue? (yes/no) [no]:
 > y
```

You will then be prompted to choose an action:

```shell
 What would you like to do?:
  [0] Create new connector configuration
  [1] Edit existing connector configuration
  [2] Delete existing connector configuration
 > 0
```

If you choose to create a new connector, you will be asked to choose the mode in which the connector will communicate with the devices:

```shell
 What type of Modbus devices will this connector handle? [Modbus RTU devices over serial line]:
  [0] Modbus RTU devices over serial line
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

## Configuring the Connector with the FastyBird User Interface

You can also configure the [Modbus](https://en.wikipedia.org/wiki/Modbus) connector using the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) user interface. For more information on how to do this,
please refer to the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) documentation.

# Devices Configuration

With your new connector set up, you must now configure the devices with which the connector will communicate.
This can be accomplished either through a console command or through the user interface of the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things).

## Manual Console Command

To manually trigger device discovery, use the following command:

```shell
php bin/fb-console fb:modbus-connector:devices
```

> **NOTE:**
The path to the console command may vary depending on your FastyBird application distribution. For more information, refer to the FastyBird documentation.

The console will prompt for confirmation before proceeding with the devices configuration process.

```shell
Modbus connector - devices management
=====================================

 ! [NOTE] This action will create|update|delete connector device.                                                       

 Would you like to continue? (yes/no) [no]:
 > y
```

You will then be prompted to select connector to manage devices.

```shell
 Please select connector under which you want to manage devices:
  [0] my-modbus [My Modbus]
 > 0
```

You will then be prompted to select device management action.

```shell
 What would you like to do?:
  [0] Create new connector device
  [1] Edit existing connector device
  [2] Delete existing connector device
 > 0
```

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
 Provide device hardware address:
 > 1
```

```shell
 What byte order device uses? [big]:
  [0] big
  [1] big_swap
  [2] little
  [3] little_swap
 > 0
```

> **NOTE:**
The byte order of your device is dependent on its specifications. To determine the appropriate byte order, consult your device manual.
If you are unsure, the default option of "BIG" is commonly used.

If there are no errors, you will receive a success message.

```shell
 [OK] Device "First device - temperature & humidity" was successfully created
```

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

If your device have more register, you could continue answering and configuring another registers.

# Troubleshooting

## Combined Data Types

In instances where some devices utilize different data types for reading and writing values, a special data type `SWITCH` can be used.

For instance, a Chinese relay device could utilize binary representations when retrieving the relay state and integer values for adjusting the state.
To accommodate this, a specialized format can be set up. For more information, refer to the relevant relay type's [page](https://github.com/FastyBird/modbus-connector/wiki/RelayBoard).

# Known Issues and Limitations

This connector only supports boolean and numerical values, with a maximum byte size for numbers of 4 bytes,
meaning the value is comprised of 2 registers.
