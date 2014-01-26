<?php
/* Software by David Hollander, foxytools.com */

/*
Need to edit your client? You can do so with this code
*/

if (!include 'fc-config.php') die("Please setup fc-config.php before continuing."); //db settings to store your access and refresh tokens
require "fc-includes/foxycart-api.php"; //class to help communicate with the FoxyCart API
require "fc-includes/foxycart-check-tokens.php"; //function to renew your access tokens if they have expired via OAuth2

//Check access token expiration and refresh if necessary
$access_token = fc_check_token("client"); //<-------- Notice we get the client token, not the store token

//Initialize FoxyCart API
$fc = new FoxyCartApiClient($access_token);

//Get Homepage
$results = $fc->get();
echo "<pre>" . print_r($results, 1) . "</pre>";

//Now we can get client details
$uri = $fc->getLink("client");
if (!$uri) {
	die("Could Not Find Client URL");
}
$results = $fc->get($uri);
//echo "<pre>" . print_r($results, 1) . "</pre>";

//Let's Make a Change
$new_data = array(
	"company_name" => "Acme",
);

//Get URL
$uri = $fc->getLink("client");

//Patch (updating, POST for creating new)
//$results = $fc->patch($uri, $new_data);

//View Results
//echo "<pre>" . print_r($results, 1) . "</pre>";
