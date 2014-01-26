<?php
/* Software by David Hollander, foxytools.com */

/*
This file will help you get started using FoxyCart's HyperMedia API

Step 1: Create a Client
Step 2: Ask FoxyCart to Enable Production Access For Your Client
Step 3: Come Back to the page and enter the Client ID and Client Secret (FoxyCart will provide this)
Step 4: Click the link to Authorize your Store at FoxyCart
Step 5: You now have access to the API. Go to index.php and start exploring

*/

if (!include 'fc-config.php') die("Please setup fc-config.php before continuing.");
require "fc-includes/foxycart-api.php";
require "fc-includes/foxycart-check-tokens.php";

//Check For Config
if (!$dblogin) {
	die("Please load your database settings into fc-config.php before continuing.");
}

//Check Setup Status
$db = Database::obtain($dbhost, $dblogin, $dbpassword, $dbname);
$db->connect();

//Create Table
$sql = "CREATE TABLE IF NOT EXISTS `fc_oauth_tokens` (`token_name` varchar(50) NOT NULL,`access_token` varchar(50) NOT NULL,`refresh_token` varchar(50) NOT NULL,`token_expiration` varchar(50) NOT NULL,`client_id` varchar(100) NOT NULL,`client_secret` varchar(100) NOT NULL,PRIMARY KEY (`token_name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
$db->query($sql);

//Check Token Status
$status = "";
$sql = "SELECT * FROM `fc_oauth_tokens` WHERE token_name = 'fc_api_client'";
$row = $db->query_first($sql);
if (empty($row['token_name'])) {
	$status = "no_client";
	$redirect_uri = isset($_POST['redirect_uri']) ? $_POST['redirect_uri'] : "http" . ($_SERVER['SERVER_PORT'] == 443 ? "s" : "") . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
} else {
	if ($row['client_id'] == "") {
		$status = "no_client_id";
	}
}

//Check Store Connection
if (!$status) {

	//Setup Store URL
	$authorize_url = "https://my.foxycart.com/authorize?response_type=code&scope=store_full_access&client_id=" . $row['client_id'];

	$sql = "SELECT * FROM `fc_oauth_tokens` WHERE token_name = 'fc_api_store'";
	$row = $db->query_first($sql);
	if (empty($row['token_name'])) {
		$status = "no_store";

	} else {
		$status = "store_exists";
	}
}


//Actions
//----------------------------------------------
$act = isset($_POST['act']) ? $_POST['act'] : "";
$error_message = array();

//Create Client
if ($act == "create_client") {

	$data = array(
		'redirect_uri' => $_POST['redirect_uri'],
		'project_name' => $_POST['project_name'],
		'project_description' => $_POST['project_description'],
		'company_name' => $_POST['company_name'],
		'company_url' => $_POST['company_url'],
		'company_logo' => $_POST['company_logo'],
		'contact_name' => $_POST['contact_name'],
		'contact_email' => $_POST['contact_email'],
		'contact_phone' => $_POST['contact_phone'],
	);

	$fc = new FoxyCartApiClient();
	$fc->get();
	$result = $fc->post($fc->getLink("create_client"), $data);


	//Error
	if ($fc->last_status_code == 400) {

		foreach ($result as $data) {
			$error_message[] = $data['message'];
		}

	//Success
	} else {

		//Set Tokens
		$sql = "SELECT * FROM `fc_oauth_tokens` WHERE token_name = 'fc_api_client'";
		$row = $db->query_first($sql);

		$data = array(
			"token_name" => "fc_api_client",
			"access_token" => $result['token']['access_token'],
			"refresh_token" => $result['token']['refresh_token'],
			"token_expiration" => date("Y-m-d H:i:s", strtotime("+" . $result['token']['expires_in'] . " seconds")),
		);

		if (empty($row['token_name'])) {
			$db->insert("fc_oauth_tokens", $data);
		} else {
			$db->update("fc_oauth_tokens", $data, "token_name = 'fc_api_client'");
		}

		$message = $result['message'];

		//Check New Client and See If We Have Production Access
		$fc_client = new FoxyCartApiClient($result['token']['access_token']);
		$fc_client->get();
		$result = $fc_client->get($fc_client->getLink("client"));

		//See if we have access to the store info
		if ($fc_client->last_status_code != 400) {

			//Set Client ID and Secret
			$client = $fc_client->get($fc_client->getLink("client"));
			$data = array(
				"client_id" => $result['client_id'],
				"client_secret" => $result['client_secret'],
			);
			$db->update("fc_oauth_tokens", $data, "token_name = 'fc_api_client'");

			header("Location: fc-setup.php");
			die;

		//We don't have production access
		} else {

			header("Location: fc-setup.php?message=" . urlencode($message));
			die;

		}
	}


//Enter client info
} elseif ($act == "enter_client_info") {

	//Set Client ID and Secret
	$data = array(
		"client_id" => $_POST['client_id'],
		"client_secret" => $_POST['client_secret'],
	);
	$db->update("fc_oauth_tokens", $data, "token_name = 'fc_api_client'");
	header("Location: fc-setup.php");
	die;


//Getting Code Back From Oauth Endpoint
} elseif (isset($_GET['code'])) {

	//Get Client Info From DB
	$sql = "SELECT * FROM `fc_oauth_tokens` WHERE token_name = 'fc_api_client'";
	$row = $db->query_first($sql);

	//Setup Request
	$post_values = array(
		"grant_type" => "authorization_code",
		"client_id" => $row['client_id'],
		"client_secret" => $row['client_secret'],
		"code" => $_GET['code'],
	);

	$uri = "https://api.foxycart.com/token"; //Production Endpoint
	//$uri = "https://api-sandbox.foxycart.com/token"; //Sandbox Endpoint

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
			"refresh_token" => $data['refresh_token'],
			"token_expiration" => date("Y-m-d H:i:s", strtotime("+" . ($data['expires_in'] - 300) . " seconds")),
		);

		//Set Tokens
		$sql = "SELECT token_name FROM `fc_oauth_tokens` WHERE token_name = 'fc_api_store'";
		$row = $db->query_first($sql);
		if (empty($row['token_name'])) {
			$db->insert("fc_oauth_tokens", $new_settings);
		} else {
			$db->update("fc_oauth_tokens", $new_settings, "token_name = 'fc_api_store'");
		}

		header("Location: fc-setup.php");
		die;


	//Failure - Let's Debug
	} else {
		echo "Access Token Renewal Failed.";
		echo "<pre>" . print_r($data, 1) . "</pre>";
		die;
	}


}




?><!DOCTYPE html>
<html>
<head>
<title>Setup</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container">
<div class="row">
<div class="col-md-12">

<?php
if (count($error_message) > 0) {
	echo "<br>";
	foreach ($error_message as $m) {
		echo '<div class="alert alert-danger">' . $m . '</div>' . "\n";
	}
}
?>


<?php if ($status == "no_client") { ?>

	<h1>Create Client</h1>
	<p>The first thing we need to do is create a client</p>

	<form role="form" action="fc-setup.php" method="post" class="form-horizontal">
		<input type="hidden" name="act" value="create_client">
		<div class="form-group">
			<label for="project_name" class="col-sm-2 control-label">Project Name<span class="text-danger">*</span></label>
			<div class="col-sm-3">
				<input type="text" class="form-control" id="project_name" name="project_name" maxlength="50" value="<?php echo isset($_POST['project_name']) ? htmlspecialchars($_POST['project_name']) : ""; ?>">
			</div>
		</div>
		<div class="form-group">
			<label for="project_description" class="col-sm-2 control-label">Project Description</label>
			<div class="col-sm-3">
				<input type="text" class="form-control" id="project_description" name="project_description" maxlength="50" value="<?php echo isset($_POST['project_description']) ? htmlspecialchars($_POST['project_description']) : ""; ?>">
			</div>
		</div>
		<div class="form-group">
			<label for="company_name" class="col-sm-2 control-label">Company Name<span class="text-danger">*</span></label>
			<div class="col-sm-3">
				<input type="text" class="form-control" id="company_name" name="company_name" maxlength="50" value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ""; ?>">
			</div>
		</div>
		<div class="form-group">
			<label for="company_url" class="col-sm-2 control-label">Company URL</label>
			<div class="col-sm-3">
				<input type="text" class="form-control" id="company_url" name="company_url" maxlength="50" value="<?php echo isset($_POST['company_url']) ? htmlspecialchars($_POST['company_url']) : ""; ?>">
			</div>
		</div>

		<div class="form-group">
			<label for="company_logo" class="col-sm-2 control-label">Company Logo</label>
			<div class="col-sm-3">
				<input type="text" class="form-control" id="company_logo" name="company_logo" maxlength="50" value="<?php echo isset($_POST['company_logo']) ? htmlspecialchars($_POST['company_logo']) : ""; ?>">
			</div>
		</div>

		<div class="form-group">
			<label for="contact_name" class="col-sm-2 control-label">Contact Name<span class="text-danger">*</span></label>
			<div class="col-sm-3">
				<input type="text" class="form-control" id="contact_name" name="contact_name" maxlength="50" value="<?php echo isset($_POST['contact_name']) ? htmlspecialchars($_POST['contact_name']) : ""; ?>">
			</div>
		</div>

		<div class="form-group">
			<label for="contact_email" class="col-sm-2 control-label">Contact Email<span class="text-danger">*</span></label>
			<div class="col-sm-3">
				<input type="email" class="form-control" id="contact_email" name="contact_email" maxlength="50" value="<?php echo isset($_POST['contact_email']) ? htmlspecialchars($_POST['contact_email']) : ""; ?>">
			</div>
		</div>

		<div class="form-group">
			<label for="contact_phone" class="col-sm-2 control-label">Contact Phone<span class="text-danger">*</span></label>
			<div class="col-sm-3">
				<input type="text" class="form-control" id="contact_phone" name="contact_phone" maxlength="50" value="<?php echo isset($_POST['contact_phone']) ? htmlspecialchars($_POST['contact_phone']) : ""; ?>">
			</div>
		</div>

		<div class="form-group">
			<label for="redirect_uri" class="col-sm-2 control-label">Redirect URI<span class="text-danger">*</span></label>
			<div class="col-sm-5">
				<input type="text" class="form-control" id="redirect_uri" name="redirect_uri" maxlength="50" value="<?php echo $redirect_uri; ?>">
				<small class="muted">This should be the current page's URL</small>
			</div>
		</div>

		<div class="form-group">
			<label for="javascript_origin_uri" class="col-sm-2 control-label">Javascript Origin URI</label>
			<div class="col-sm-5">
				<input type="text" class="form-control" id="javascript_origin_uri" name="javascript_origin_uri" maxlength="50" value="<?php echo isset($_POST['javascript_origin_uri']) ? htmlspecialchars($_POST['javascript_origin_uri']) : ""; ?>">
			</div>
		</div>


		<button type="submit" class="btn btn-primary">Create Client</button>
	</form>




	<?php } elseif ($status == "no_client_id") { ?>


	<h1>Enter Client ID and Client Secret</h1>
	<p>Your client has been successfully created, but FoxyCart still needs to allow your client to have production access. Please ask FoxyCart for production access and they'll give you back a Client ID and Client Secret value. Enter those values below.</p>

	<?php
	if (isset($_GET['message'])) {
		echo "<br>";
		echo '<div class="alert alert-info">Give FoxyCart This Information: <b>' . htmlentities($_GET['message']) . '</b></div>' . "\n";
	}
	?>

	<form role="form" action="fc-setup.php" method="post" class="form-horizontal">
		<input type="hidden" name="act" value="enter_client_info">
		<div class="form-group">
			<label for="client_id" class="col-sm-2 control-label">Client ID<span class="text-danger">*</span></label>
			<div class="col-sm-5">
				<input type="text" class="form-control" id="client_id" name="client_id" maxlength="100" value="">
			</div>
		</div>
		<div class="form-group">
			<label for="client_secret" class="col-sm-2 control-label">Client Secret<span class="text-danger">*</span></label>
			<div class="col-sm-5">
				<input type="text" class="form-control" id="client_secret" name="client_secret" maxlength="100" value="">
			</div>
		</div>

		<button type="submit" class="btn btn-primary">Save Client Data</button>
	</form>


<?php } elseif ($status == "no_store") { ?>

	<h1>Connect To Store</h1>
	<p>Your client has been successfully initialized. Now it's time to authorize the your store. Click the button below.</p>
	<p><a href="<?php echo $authorize_url; ?>" class="btn btn-primary">Authorize Store</a></p>
	<br><br><br><br><br><br>

<?php } elseif ($status == "store_exists") { ?>

	<h1>Store Link Established</h1>
	<p>The link to your store has been established. See <code>index.php</code> to start exploring the API.</p>
	<p> If you would like to re-connect at any time, you may do so by clicking the link below.</p>
	<p><a href="<?php echo $authorize_url; ?>" class="btn btn-primary">Authorize Store</a></p>
	<br><br><br><br><br><br>

<?php } ?>

<br><br>
<hr>
<p class="text-center"><small>This tool built by David Hollander, FoxyTools.com</small></p>



	</div>
	</div>
</div>

  </body>
</html>
