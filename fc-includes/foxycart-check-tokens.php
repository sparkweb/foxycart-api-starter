<?php
/* Software by David Hollander, foxytools.com */

function fc_check_token($token_type) {

	//Production or Sandbox Access
	$uri = "https://api.foxycart.com/token"; //Production Endpoint
	//$uri = "https://api-sandbox.foxycart.com/token"; //Sandbox Endpoint

	//Initialize DB
	global $dbhost, $dblogin, $dbpassword, $dbname;
	$db = Database::obtain($dbhost, $dblogin, $dbpassword, $dbname);
	$db->connect();

	//Get Token from DB
	$sql = "SELECT * FROM `fc_oauth_tokens` WHERE token_name = 'fc_api_" . mysql_real_escape_string($token_type) . "'";
	$row = $db->query_first($sql);

	//We didn't get anything back - a blank access token will throw an error when initializing the api class
	if (empty($row['access_token'])) {
		return "";
	}

	//This token needs to be renewed
	if (strtotime($row['token_expiration']) < strtotime("now")) {

		//Get Client Info From DB
		$sql = "SELECT * FROM `fc_oauth_tokens` WHERE token_name = 'fc_api_client'";
		$row = $db->query_first($sql);

		//Prepare Values
		$post_values = array(
			"grant_type" => "refresh_token",
			"refresh_token" => $row['refresh_token'],
			"client_id" => $row['client_id'],
			"client_secret" => $row['client_secret'],
		);

		//Send Request
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE); // Change to False if you have trouble connecting to the SSL
		curl_setopt($ch, CURLOPT_URL, $uri);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_values));
		$response = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		$data = json_decode($response, 1);

		//Success
		if (isset($data['access_token'])) {

			//Save to Database
			$new_settings = array(
				"access_token" => $data['access_token'],
				"token_expiration" => date("Y-m-d H:i:s", strtotime("+" . ($data['expires_in'] - 300) . " seconds")),
			);
			
			//If Refresh Token Was Returned
			if (isset($data['refresh_token'])) {
				$new_settings['refresh_token'] = $data['refresh_token'];
			}
			$db->update("fc_oauth_tokens", $new_settings, "token_name = 'fc_api_" . mysql_real_escape_string($token_type) . "'");

			//Return New Token
			return $data['access_token'];


		//Failure - Let's Debug
		} else {
			echo "Access Token Renewal Failed.";
			echo "<pre>" . print_r($data, 1) . "</pre>";
			die;
		}


	}

	//Return Token
	return $row['access_token'];

}
