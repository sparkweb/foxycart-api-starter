FoxyCart Hypermedia API Starter
=======

This tool is designed to jumpstart your developement with the [FoxyCart Hypermedia API](https://wiki.foxycart.com/v/0.0.0/hypermedia_api).

## Production Note

The API is currently in a limited private beta so you won't be able to get production access for your API Client without working out the details directly with the FoxyCart API support team. If you've already worked out those details, you can create your client and then request that they turn on production access for your client ID. Otherwise, you can use this tool with the API sandbox which will allow you to practice using the API and get familiar with it.

## Setup

1. Make a copy of `fc-config-sample.php` and call it `fc-config.php`. Load your database configuration settings into this file. The db is needed to hold and manage your OAuth tokens.
2. Go to `fc-setup.php` and create your client.
3. If using production, send the ID of the client to FoxyCart and ask for production access.
4. They will send back a Client ID and Client Secret. Load that information into `fc-setup.php` and save.
5. Now load up index.php and start exploring the API.

## Switching to Sandbox

By default, this is set to connect to production. If you want to create a client on the sandbox instead, swith the `$uri` field in `fc-setup.php` and `fc-includes/foxycart-check-tokens.php`. You also need to uncomment a line in the __construct() function of `fc-includes/foxycart-check-tokens.php`.

## Connection Trouble?

If you are having trouble connecting to the FoxyCart servers, your SSL certificate store may not be up to date. The easy way around this is to set the Verify Peer SSL feature to False in these three locations:

- `fc-setup.php`
- `fc-includes/foxycart-check-tokens.php`
- `fc-includes/foxycart-api.php`

## About

This tool was built by David Hollander of [SparkWeb Interactive](http://sparkweb.net) and [FoxyTools](http://foxytools.com). Write to web-AT-sparkweb-DOT-net with questions.
