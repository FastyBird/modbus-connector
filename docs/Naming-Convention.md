# Naming Convention

The connector uses the following naming convention for its entities:

## Connector

A connector entity in the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem refers to a entity which is holding basic configuration
and is responsible for managing communication with [Modbus](https://en.wikipedia.org/wiki/Modbus) devices and other [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem services.

## Device

A device entity in the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem refers to a entity which is holding configuration of
a physical [Modbus](https://en.wikipedia.org/wiki/Modbus) device.

## Channel

A channel in the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem refers to a entity which is representing a one device **register**
on specific address, e.g. coil register, discrete inputs etc.

## Property

A property in the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem refers to a entity which is holding configuration values or
device actual state. Connector, Device and Channel entity has own Property entities.

### Connector Property

Connector related properties are used to store configuration like `communication mode`, `rtu interface` or `baud rate`. This configuration values are used
to connect to [Modbus](https://en.wikipedia.org/wiki/Modbus) devices.

### Device Property

Device related properties are used to store configuration like `address` or `byte order` for RTU devices or `ip address`, `communication port`
or `unit identifier` for TCP devices. Some of them have to be configured to be able to use this connector
or to communicate with device. In case some of the mandatory property is missing, connector will log and error.

### Channel Property

Channel related properties are used for storing actual state of [Modbus](https://en.wikipedia.org/wiki/Modbus) device register.
It could be a boolean value of a `discrete input` or a numeric value of a `holding register`. These values are read from
device and stored in system.

## Register

A register represent data storage in physical [Modbus](https://en.wikipedia.org/wiki/Modbus) device. The connector read
and write value to device registers.

## Device Interface

The connector supports two device interfaces, the first being RS485 on twisted wire lines and the second being TCP/IP
via standard network communication.

## Device Mode

There are two types of connectors interfaces supported:

The first mode is **RTU mode** and in this mode this the connector is using **USB** interface or **Serial line** interface, it
depends on where you have connected the **RS 485** communication interface which is connected to the physical devices.

The second mode is **TCP/IP mode** and in this mode the connector uses **LAN** interface for communication with devices.
