<p align="center">
	<img src="https://github.com/FastyBird/modbus-connector/blob/main/docs/_media/waveshare_gateway.png" alt="Waveshare gateway"/>
</p>

# RS485 To Wifi/Ethernet module

In some cases you will need to transform Modbus RTU devices to be accessible through LAN or WAN network. Eg. Your modbus
devices, like relays or sensors, are installed in your cabinet and your [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
instance is installed on Raspberry PI which is installed somewhere else, you will need this type of gateway (or from different vendor).

## Hardware installation

Hardware installation is straightforward. Wire you Modbus RTU devices to Modbus gateway with wires.

## Gateway configuration

Gateway have to be configured to specific mode.

### Gateway UART settings

You have to configure UART settings, and it has to match with you devices.

<p align="center">
	<img src="https://github.com/FastyBird/modbus-connector/blob/main/docs/_media/waveshare_uart_settings.png" alt="UART settings"/>
</p>

### Gateway mode

In next step you have to configure gateway mode. In this case we will use `Modbus TCP <==> Modbus RTU` which mean gateway
will expose Modbus TCP commands which will be used by connector.

<p align="center">
	<img src="https://github.com/FastyBird/modbus-connector/blob/main/docs/_media/waveshare_mode.png" alt="Mode settings"/>
</p>

## Connector configuration

You have to create Modbus TCP connector instance. You could use installation command.

Just choose to create new device under Modbus TCP connector:

```shell
 What would you like to do? [Nothing]:
  [0] Create device
  [1] Edit device
  [2] Delete device
  [3] Manage device
  [4] List devices
  [5] Nothing
 > Create device
```

And just follow instructions:

```shell
 Provide device identifier:
 > my-new-device
```

```shell
 Provide device name:
 > My New Device
```

And now you have to provide device IP address which is IP address of your gateway:

```shell
 Provide device IP address:
 > 10.10.0.149
```

And also you have to configure gateway port:

```shell
 Provide device IP address port:
 > 8899
```

Network setting of you gateway you could find in gateway user interface:

<p align="center">
	<img src="https://github.com/FastyBird/modbus-connector/blob/main/docs/_media/waveshare_network.png" alt="Network settings"/>
</p>

In the next step you have to provide unit identifier:

```shell
 Provide device unit identifier:
 > 2
```

Unit identifier is Modbus RTU device station address.

And finally you have to configure byte order for this device:

```shell
 What byte order device uses? [Big-Endian]:
  [0] Big-Endian
  [1] Swapped Big-Endian
  [2] Little-Endian
  [3] Swappd Little-Endian
 > 
```

After providing the necessary information, your new [Modbus](https://en.wikipedia.org/wiki/Modbus) device will be ready for use.

```shell
 [OK] Device "TCP device" was successfully created.  
```

Next you have to configure device registers.
