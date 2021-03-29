**This module is no longer maintained. The PHP SDK, which this module makes calls through, is still available at <https://github.com/getyoti/yoti-php-sdk>**

# Yoti Drupal 8 Module

[![Build Status](https://travis-ci.com/getyoti/yoti-drupal.svg?branch=8.x-2.x)](https://travis-ci.com/getyoti/yoti-drupal)

This repository contains the tools you need to quickly integrate your Drupal 8 backend with Yoti so that your users can share their identity details with your application in a secure and trusted way. The module uses the Yoti PHP SDK. If you're interested in finding out more about the SDK, click [here](https://github.com/getyoti/yoti-php-sdk).

## Installing the module

To import the Yoti Drupal 8 module inside your project:

1. Log on to the admin console of your Drupal website. e.g. `https://www.drupalurl.org.uk/admin`
2. Navigate to `Extend/Modules`, click the `contributed modules` link and search for Yoti - you can also download the package from [here](https://www.drupal.org/project/yoti).
3. Set up Drupal `private file systems` as required by Yoti. You can use the [following guide](https://www.drupal.org/docs/8/core/modules/file/overview#private-file-system).
4. Install and enable the module.

## Setting up your Yoti Application

After you registered your [Yoti](https://www.yoti.com/), access the [Yoti Hub](https://hub.yoti.com) to create a new application.

Specify the basic details of your application such as the name, description and optional logo. These details can be whatever you like and will not affect the module's functionality.

The `Data` tab - Specify any attributes you'd like users to share. You must select at least one. If you plan to allow new user registrations, we recommended choosing `Given Name(s)`, `Family Name` and `Email Address` at a minimum.

The `Integration` tab - Here is where you specify the callback URL. This can be found on your Yoti settings page in your Drupal admin panel.

## Module Setup

To set things up, navigate on Drupal to the Yoti module.
You will be asked to add the following information:

* `Yoti App ID` is the unique identifier of your specific application.
* `Yoti Scenario ID` identifies the attributes associated with your Yoti application. This value can be found on your application page in Yoti Hub.
* `Yoti Client SDK ID` identifies your Yoti Hub application. This value can be found in the Hub, within your application section, in the keys tab.
* `Company Name` will replace Drupal wording in the warning message displayed on the custom login form.
* `Yoti PEM File` is the application pem file. It can be downloaded only once from the Keys tab in your Yoti Hub.

Please do not open the .pem file as this might corrupt the key and you will need to create a new application.

## Settings for new registrations

`Only allow existing Drupal users to link their Yoti account` - This setting allows a new user to register and log in by using their Yoti. If enabled, when a new user tries to scan the Yoti QR code, they will be redirected back to the login page with an error message displayed.

`Attempt to link Yoti email address with Drupal account for first time users` - This setting enables linking a Yoti account to a Drupal user if the email from both platforms is identical.

## How to retrieve user data provided by Yoti
Upon registration using Yoti, user data will be stored as serialized data into `users_yoti` table in the `data` field.

You can write a query to retrieve all data stored in `users_yoti.data`, which will return a list of serialized data.

## Docker

We provide a [Docker](https://docs.docker.com/) container that includes the Yoti module.

### Setup

Clone this repository, go into the yoti-drupal folder and checkout Drupal 8 branch by running the following commands:

```shell
$ git clone https://github.com/getyoti/yoti-drupal.git
$ cd yoti-drupal
$ git checkout 8.x-1.x
```

Rebuild the images if you have modified any Docker file:

>Note: Docker commands should be run from inside the `/docker/` directory.

```shell
$ docker-compose build --no-cache
```

#### Fetching the SDK

To fetch the latest SDK and place in `./yoti/sdk` directory:

```shell
$ ./checkout-sdk.sh
```

#### Quick Installation (Drush)

Install Drupal and enable Yoti module:

```shell
$ ./install-drupal.sh
```

Visit <https://localhost:8004> and follow the [module setup process](#module-setup)

#### Manual Installation

Build the containers:

```shell
$ docker-compose up -d drupal-8
```

After the command has finished running, go to <https://localhost:8004> and follow the instructions.

Enable the Yoti module and follow our [module setup process](#module-setup).

### Local Development

To install Drupal and enable the local working Yoti module:

```shell
$ ./install-drupal.sh drupal-8-dev
```

### Xdebug

To enable Xdebug, install using the debug container:
```shell
./install-drupal.sh drupal-8-debug
```

To use Xdebug in an IDE, map the `/var/www/html/modules/yoti` volume to the module directory on the host machine.

### Running Tests

To check coding standards and run unit tests:

```shell
$ ./run-tests.sh
```

### Removing the Docker containers

Run the following commands to remove docker containers:

```shell
$ docker-compose stop
$ docker-compose rm
```

## Support

For any questions or support please email [sdksupport@yoti.com](mailto:sdksupport@yoti.com).
Please provide the following to get you up and working as quickly as possible:

* Computer type
* OS version
* Version of Drupal being used
* Screenshot

Once we have answered your question we may contact you again to discuss Yoti products and services. If you’d prefer us not to do this, please let us know when you e-mail.
