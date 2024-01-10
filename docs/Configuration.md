# Configuration

To use [Modbus](https://en.wikipedia.org/wiki/Modbus) devices with the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem, you will need to configure at least one connector.
The connector can be configured using the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) user interface or through the console.

There are two types of connectors available for selection:

- **RTU** - This connector uses serial line to communicate with devices.
- **TCP/IP** - This connector uses LAN or WAN networks to communicate with devices.

## Configuring the Connectors, Devices and Registers through the Console

To configure the connector through the console, run the following command:

```shell
php bin/fb-console fb:modbus-connector:install
```

> [!NOTE]
The path to the console command may vary depending on your FastyBird application distribution. For more information, refer to the FastyBird documentation.

This command is interactive and easy to operate.

The console will show you basic menu. To navigate in menu you could write value displayed in square brackets or you
could use arrows to select one of the options:

```
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

```
 In what mode should this connector communicate with Modbus devices? [Modbus devices over serial line]:
  [0] Modbus devices over serial line
  [1] Modbus devices over TCP network
 > 0
```

You will then be asked to provide a connector identifier and name:

```
 Provide connector identifier:
 > my-modbus
```

```
 Provide connector name:
 > My Modbus
```

> [!NOTE]
If you choose the RTU mode, you will be prompted to provide path to the serial interface. Something like `/dev/ttyUSB0`

> [!NOTE]
For both connector types you will be prompted to provide another communication settings like baud rate, byte size etc.
You have to use same configuration as your devices use.

After providing the necessary information, your new [Modbus](https://en.wikipedia.org/wiki/Modbus) connector will be ready for use.

```
 [OK] New connector "My Modbus" was successfully created
```

### Create device

After new connector is created you will be asked if you want to create new device:

```
 Would you like to configure connector device(s)? (yes/no) [yes]:
 > 
```

Or you could choose to manage connector devices from the main menu.

Now you will be asked to provide some device details:

```
 Provide device identifier:
 > first-device
```

```
 Provide device name:
 > First device - temperature & humidity
```

```
 Provide device station address:
 > 1
```

```
 What byte order device uses? [Big-Endian]:
  [0] Big-Endian
  [1] Swapped Big-Endian
  [2] Little-Endian
  [3] Swappd Little-Endian
 > 0
```

> [!NOTE]
The byte order of your device is dependent on its specifications. To determine the appropriate byte order, consult it with your device manual.
If you are unsure, the default option of "BIG" is commonly used.

If there are no errors, you will receive a success message.

```
 [OK] Device "First device - temperature & humidity" was successfully created
```

### Create registers

Each device have to have defined registers - value storages. So in next steps you will be prompted to configure device's registers.

```
 Would you like to configure device register(s)? (yes/no) [yes]:
 > y
```

Now you will be asked to provide some device registers details:

```
 What type of device register you would like to add? [Discrete Input]:
  [0] Discrete Input
  [1] Coil
  [2] Input Register
  [3] Holding Register
 > 2
```

```
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

```
Provide register address. It could be single number or range like 1-2:
> 1
```

```
Provide register name (optional):
> Temperature
```

```
Provide register sampling time (s) [120]:
> 120
```

> [!TIP]
The sampling time is used for connector timer which is reading value from device. Every communication on the line means that
other devices have to wait. So choose this time wisely.

If there are no errors, you will receive a success message.

```
 [OK] Device register was successfully created
```

If your device have more registers, you could continue answering and configuring another registers.

### Connectors, Devices and Registers management

With this console command you could manage all your connectors, their devices and registers. Just use the main menu to navigate to proper action.

## Configuring the Connector with the FastyBird User Interface

You can also configure the Modbus connector using the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
user interface. For more information on how to do this, please refer to the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) documentation.
