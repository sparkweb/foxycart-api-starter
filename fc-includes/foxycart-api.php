<?php
/* Software by David Hollander, foxytools.com */

class FoxyCartApiClient
{

	//App Specific
	private $config = "";
	private $ch = "";
	public $access_token = "";

	//Vars
	public $last_status_code = "";

	//FoxyCart API Endpoints
	public $api_home_page = "https://api.foxycart.com";

	//Link Vars
	public $links = array();
	public $rel_base = "https://api.foxycart.com/rels/";
	public $registered_link_relations = array('self', 'first', 'prev', 'next', 'last');

	//Required Headers
	private $required_headers = array(
		'FOXYCART-API-VERSION' => 1,
	);

	public function __construct($access_token = "") {

		//Set Access Token
		$this->access_token = $access_token;

		//Using Sandbox? Uncomment next line
		//$this->api_home_page = "https://api-sandbox.foxycart.com"; //Sandbox Endpoint

		//Setup cURL
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_AUTOREFERER, TRUE);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->ch, CURLOPT_FRESH_CONNECT, TRUE);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, TRUE); // Change to False if you have trouble connecting to the SSL
	}

	public function __destruct() {

		//Keeping it tidy
		curl_close($this->ch);
	}

	public function get($uri = "", $post = null) {
		return $this->go("GET", $uri, $post);
	}

	public function post($uri, $post = null) {
		return $this->go("POST", $uri, $post);
	}

	public function patch($uri, $post = null) {
		return $this->go("PATCH", $uri, $post);
	}

	public function delete($uri, $post = null) {
		return $this->go("DELETE", $uri, $post);
	}

	public function go($method, $uri, $post) {
		if (!is_array($post)) $post = null;
		if (!$uri) $uri = $this->api_home_page;
		$headers = $this->getHeaders();
		$post_fields = null;

		//PATCH Override
		if ($method == "PATCH") {
			$headers['X-HTTP-Method-Override'] = "PATCH";
			$method = "POST";
		}

		$formatted_headers = array();
		foreach ($headers as $key => $val) {
			$formatted_headers[] = "{$key}: {$val}";
		}

		//GET Override
		if ($method == "GET" && $post != null) {
			if (strpos($uri, "?") === false) {
				$uri .= "?" . http_build_query($post);
			} else {
				$uri .= "&" . http_build_query($post);
			}

		//Everything else
		} else {
			if (is_array($post)) {
				$post_fields = json_encode($post);
			}
		}

		//Send Request
		curl_setopt($this->ch, CURLOPT_URL, $uri);
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $formatted_headers);
		$response = curl_exec($this->ch);
		$info = curl_getinfo($this->ch);


		$data = json_decode($response, 1);
		$this->last_status_code = $info['http_code'];
		$this->saveLinks($data);
		return $data;
	}


	public function saveLinks($data) {
		if (!isset($data['_links'])) return;
		foreach ($data['_links'] as $key => $val) {
			$this->links[$key] = $val;
		}
	}
	public function getLink($str) {
		$search_string = in_array($str, $this->registered_link_relations) ? $str : $this->rel_base . $str;
		if (isset($this->links[$search_string])) {
			return $this->links[$search_string]['href'];
		} else {
			return "";
		}
	}

	public function checkForErrors($data) {
		if ($this->last_status_code > 201) {
			if (is_array($data) && isset($data['error_description'])) {
				return array($data['error_description']);
			} elseif (is_array($data) && isset($data[0]['message'])) {
				return array($data[0]['message']);
			} else {
				return array("No data returned.");
			}
		}
		return false;
	}

	public function getHeaders() {
		$headers = array_merge($this->required_headers, array(
			'Accept' => 'application/hal+json',
			'Content-Type' => 'application/json',
		));
		if ($this->access_token) {
			$headers['Authorization'] = "Bearer " . $this->access_token;
		}
		return $headers;
	}

	//Set Fields on Init
	public function setApiHomepage($val) {
		$this->api_home_page = $val;
	}

}
