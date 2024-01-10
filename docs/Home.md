<p align="center">
	<img src="https://github.com/fastybird/.github/blob/main/assets/repo_title.png?raw=true" alt="FastyBird"/>
</p>

> [!IMPORTANT]
This documentation is meant to be used by developers or users which has basic programming skills. If you are regular user
please use FastyBird IoT documentation which is available on [docs.fastybird.com](https://docs.fastybird.com).

The [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) Modbus Connector is an extension for the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem that enables seamless integration
with [Modbus](https://en.wikipedia.org/wiki/Modbus) devices. It allows users to easily connect and control [Modbus](https://en.wikipedia.org/wiki/Modbus) devices from within the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem,
providing a simple and user-friendly interface for managing and monitoring your devices.

# About Connector

This connector has some services divided into namespaces. All services are preconfigured and imported into application
container automatically.

```
\FastyBird\Connector\Modbus
  \API - Services and helpers related to API - for managing data exchange validation and data parsing
  \Clients - Services which handle communication with Modbus devices
  \Commands - Services used for user console interface
  \Entities - All entities used by connector
  \Helpers - Useful helpers for reading values, bulding entities etc.
  \Queue - Services related to connector internal communication
  \Schemas - {JSON:API} schemas mapping for API requests
  \Translations - Connector translations
  \Writers - Services for handling request from other services
```

All services, helpers, etc. are written to be self-descriptive :wink:.

> [!TIP]
To better understand what some parts of the connector meant to be used for, please refer to the [Naming Convention](Naming-Convention) page.

## Using Connector

The connector is ready to be used as is. Has configured all services in application container and there is no need to develop
some other services or bridges.

> [!TIP]
Find fundamental details regarding the installation and configuration of this connector on the [Configuration](Configuration) page.

This connector is equipped with interactive console. With this console commands you could manage almost all connector features.

* **fb:modbus-connector:install**: is used for connector installation and configuration. With interactive menu you could manage connector and devices.
* **fb:modbus-connector:execute**: is used for connector execution. It is simple command that will trigger all services which are related to communication with Modbus devices and services with other [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem services like state storage, or user interface communication.

Each console command could be triggered like this :nerd_face:

```shell
php bin/fb-console fb:modbus-connector:install
```

> [!NOTE]
The path to the console command may vary depending on your FastyBird application distribution. For more information, refer to the FastyBird documentation.

# Troubleshooting

## Combined Data Types

In instances where some devices utilize different data types for reading and writing values, a special data type `SWITCH` can be used.

For instance, a Chinese relay device could utilize binary representations when retrieving the relay state and integer values for adjusting the state.
To accommodate this, a specialized format can be set up. For more information, refer to the relevant relay type's [page](Relay-Board).

# Known Issues and Limitations

This connector only supports boolean and numerical values, with a maximum byte size for numbers of 4 bytes,
meaning the value is composited of 2 registers.
