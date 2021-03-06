# Yoti

Welcome to the Yoti Drupal SDK. This repo contains the tools you need to
quickly integrate your Drupal back-end with Yoti, so that your users can
share their identity details with your application in a secure and trusted
way.

## Table of Contents

1) [An Architectural view](#an-architectural-view) -
High level overview of integration

2) [References](#references) -
Guides before you start

3) [Installing the SDK](#installing-the-sdk) -
How to install our SDK

4) [Module Setup](#module-setup)-
How to set up the module in Drupal

5) [Linking existing accounts to use Yoti authentication
](#linking-existing-accounts-to-use-yoti-authentication)

6) [Customising User Profiles](#customising-user-profiles)

7) [API Coverage](#api-coverage) -
Attributes defined

8) [Support](#support) -
Please feel free to reach out

## An Architectural view

Before you start your integration, here is a bit of background on how the
integration works. To integrate your application with Yoti, your back-end
must expose a GET endpoint that Yoti will use to forward tokens.

The endpoint can be configured in the Yoti Hub when you create/update
your application. For more information on how to create an application please
check our [developer
page](https://developers.yoti.com/yoti-app/web-integration#step-2-creating-an-application).

The image below shows how your application back-end and Yoti integrate into
the context of a Login flow.

Yoti SDK carries out for you steps 6, 7 and the profile decryption in step 8.

![Login flow](https://git.io/fj8Qi "Login flow")

Yoti also allows you to enable user details verification from your mobile app
by means of the Android (TBA) and iOS (TBA) SDKs. In that scenario, your
Yoti-enabled mobile app is playing both the role of the browser and the Yoti
app. Your back-end doesn't need to handle these cases in a significantly
different way. You might just decide to handle the `User-Agent` header in order
to provide different responses for desktop and mobile clients.

## References

* [AES-256 symmetric encryption][]
* [RSA pkcs asymmetric encryption][]
* [Protocol buffers][]
* [Base64 data][]

[AES-256 symmetric encryption]:
https://en.wikipedia.org/wiki/Advanced_Encryption_Standard

[RSA pkcs asymmetric encryption]:
https://en.wikipedia.org/wiki/RSA_(cryptosystem)

[Protocol buffers]:
https://en.wikipedia.org/wiki/Protocol_Buffers

[Base64 data]:
https://en.wikipedia.org/wiki/Base64

## Installing the SDK

To import the Yoti SDK inside your project:

1) Log on to the admin console of your Drupal website. e.g.
   https://www.drupalurl.org.uk/admin
2) Navigate to at 'Modules' and Search for Yoti - you can also download the
   package from here.
3) Install and enable the module.

## Module Setup

To set things up, navigate on Drupal to the module

Here you will be asked to add the following information:

* `Yoti App ID`
* `Yoti Client SDK ID`
* `Yoti Scenario ID`
* `Yoti PEM File`

Where:

* `Yoti Client SDK ID` identifies your Yoti Hub application. This value can
  be found in the Hub, within your application section, in the keys tab.
* `Yoti PEM File` is the application pem file. It can be downloaded only once
   from the Keys tab in your Yoti Hub.
* `Yoti App ID` is unique identifier for your specific application.
* `Yoti Scenario ID` identifies the attributes associated with your Yoti
  application. This value can be found on your application page in Yoti Hub.

Please do not open the pem file as this might corrupt the key and you will
need to create a new application.

## Linking existing accounts to use Yoti authentication

To allow your existing users to log in using Yoti instead of entering their
username/password combination, there is a tick box when installing the module
which allows Yoti accounts to link to email addresses.

## Customising User Profiles

By default, all shared attributes are displayed on user profile pages.
This can be customised at `/admin/config/people/accounts/display`.

You can also control who can view user profiles using permissions
at `/admin/people/permissions`.

## Permissions

* `administer yoti`: Allow users to configure the Yoti module.
* `view yoti selfie images`: Allow users to view other user selfie images.
  Users can always view their own selfie images.

## API Coverage

* Activity Details
  * [X] User ID `user_id`
  * [X] Profile
    * [X] Photo `selfie`
    * [X] Given Names `given_names`
    * [X] Family Name `family_name`
    * [X] Mobile Number `phone_number`
    * [X] Email address `email_address`
    * [X] Date of Birth `date_of_birth`
    * [X] Address `postal_address`
    * [X] Gender `gender`
    * [X] Nationality `nationality`

## Support

For any questions or support please email
[sdksupport@yoti.com](mailto:sdksupport@yoti.com).
Please provide the following the get you up and
working as quick as possible:

* Computer Type
* OS Version
* Screenshot
