<?php
/* Software by David Hollander, foxytools.com */

/*
This file will help you get started using FoxyCart's HyperMedia API

If you haven't completed the setup steps in setup.php, please do so before running this file.

*/

if (!include 'fc-config.php') die("Please setup fc-config.php before continuing."); //db settings to store your access and refresh tokens
require "fc-includes/foxycart-api.php"; //class to help communicate with the FoxyCart API
require "fc-includes/foxycart-check-tokens.php"; //function to renew your access tokens if they have expired via OAuth2

//Check access token expiration and refresh if necessary
$access_token = fc_check_token("store");

//Initialize FoxyCart API
$fc = new FoxyCartApiClient($access_token);

//Get Homepage
$results = $fc->get();
echo "<pre>" . print_r($results, 1) . "</pre>";

//Now we can get store details
$uri = $fc->getLink("store");
if (!$uri) {
	die("Could Not Find Store URL");
}
$results = $fc->get($uri);
//echo "<pre>" . print_r($results, 1) . "</pre>";

//Look at Transactions
$uri = $fc->getLink("transactions");
$results = $fc->get($uri);
//echo "<pre>" . print_r($results, 1) . "</pre>";

//Let's Loop Through Each Transaction
foreach ($results["_embedded"]["transactions"] as $transaction) {
	//Links at $transaction["links"]
	//Get a link like this:
	//$uri = $transaction['_links'][$fc->base_uri . "items"]['href'];
	//or...
	//$uri = $transaction['_links']["self"]['href'];
}

//Other things you can do to search, filter, and zoom
//All dates need to be searched from Pacific Time
//Searching between two dates is: transaction_date=[date]..[date]

$uri = $fc->getLink("transactions") . "?zoom=items&transaction_date=" . urlencode(date("Y-m-d H:i:s", strtotime("2014-01-01")) . "..x");
//$results = $fc->get($uri);
//echo "<pre>" . print_r($results, 1) . "</pre>";

//Shortcut to tranactions
//$fc->get(); //get homepage for links
//$fc->get($fc->getLink("store")); //get store for transaction link
//$transactions = $fc->get($fc->getLink("transactions"));

//See the stored links that the API class has accumulated from the various calls
//echo "<pre>" . print_r($fc->links, 1) . "</pre>";

