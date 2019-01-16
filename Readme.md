# Yoti Drupal 7 Module

This repository contains the tools you need to quickly integrate your Drupal 7 backend with Yoti so that your users can share their identity details with your application in a secure and trusted way. The module uses the Yoti PHP SDK. If you're interested in finding out more about the SDK, click [here](https://github.com/getyoti/yoti-php-sdk).

## Installing the module

To import the Yoti Drupal 7 module inside your project:

1. Log on to the admin console of your Drupal website. e.g. `https://www.drupalurl.org.uk/admin`
2. Navigate to `Modules`, click the `contributed modules` link and search for Yoti - you can also download the package from [https://www.drupal.org/project/yoti](https://www.drupal.org/project/yoti)
3. Install and enable the module. Make sure you have the `file_private_path` setting enabled for your website. This should be set in `Configuration -> File system -> Private file system path`

## Setting up your Yoti Application

After you registered your [Yoti](https://www.yoti.com/), access the [dashboard](https://www.yoti.com/dashboard/login) to create a new application.

Specify the basic details of your application such as the name, description and optional logo. These details can be whatever you like and will not affect the module's functionality.

The `Data` tab - Specify any attributes you'd like users to share. You must select at least one. If you plan to allow new user registrations, we recommended choosing `Given Name(s)`, `Family Name` and `Email Address` at a minimum.

The `Integration` tab - Here is where you specify the callback URL. This can be found on your Yoti settings page in your Drupal admin panel.

## Module Setup

To set things up, navigate on Drupal to the Yoti module.
You will be asked to add the following information:

* `Yoti App ID` is the unique identifier of your specific application.
* `Yoti Scenario ID` is used to render the inline QR code.
* `Yoti SDK ID` is the SDK identifier generated by Yoti Dashboard in the Key tab when you create your app. Note this is not your Application Identifier, which is needed by your client-side code.
* `Company Name` will replace Drupal wording in the warning message displayed on the custom login form.
* `Yoti PEM File` is the application pem file. It can be downloaded only once from the Keys tab in your Yoti Dashboard.

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

Clone this repository, go into the `yoti-drupal` folder and checkout Drupal 7 branch by running the following commands:

```shell
$ git clone https://github.com/getyoti/yoti-drupal.git
$ cd yoti-drupal
$ git checkout 7.x-1.x
```

Rebuild the images if you have modified the `docker-compose.yml` file:

```shell
$ docker-compose build --no-cache
```

Build the containers:

```shell
$ docker-compose up -d
```

After the command has finished running, go to [https://localhost:7007](https://localhost:7007) and follow the instructions.

### Database Configuration

When prompted, enter the following database details:

* Name `drupal`
* Username `drupal`
* Password `drupal`
* Host `drupal-7-db`

The Yoti module will be installed alongside Drupal. Activate it and follow our [module setup process](#module-setup).

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
