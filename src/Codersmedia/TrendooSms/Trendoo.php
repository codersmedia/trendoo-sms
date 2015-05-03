<?php namespace Codersmedia\TrendooSms;

use Config;

class Trendoo {
	/**
	 * @var string $debug
	 */
	protected $debug;

	/**
	 * @var string $username
	 */
	protected $username;

	/**
	 * @var string $password
	 */
	protected $password;

	/*
     * DON'T TOUCH THIS PARAM!!!!!!!!
     * Referer to: http://www.trendoo.it/pdf/API_http.pdf
     */

	protected $dateFormat       = 'yyyyMMddHHmmss';
	protected $method           = 'GET';
	protected $responseDivider  = '|';
	protected $responseError    = 'KO';
	protected $responseValid    = 'OK';
	protected $iso              = 'IT';    //ISO 3166
	protected $charset          = 'UTF-8';
	protected $base_url          = "https://api.trendoo.it/Trend/";
	protected $send_endpoint    = 'SENDSMS';

	public function __construct()
	{
		$this->username   = Config::get("trendoo.login");
		$this->password   = Config::get("trendoo.password");
		$this->debug      = Config::get("trendoo.debug");
	}

	protected function injectLoginParams(){
		return [
			'login' => $this->username,
			'password' => $this->password
		];
	}
	protected function doRequest($endpoint, Array $args = null){

		$loginParams = $this->injectLoginParams();

		/* Script URL */
		$url = $this->base_url . $endpoint;

		/* $_GET Parameters to Send */
		$params = ($args != null) ? array_merge($loginParams,$args) : $loginParams;

		/* Update URL to container Query String of Paramaters */
		$url .= '?' . http_build_query($params);

		/* cURL Resource */
		$ch = curl_init();

		/* Set URL */
		curl_setopt($ch, CURLOPT_URL, $url);

		/* Tell cURL to return the output */
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		/* Tell cURL NOT to return the headers */
		curl_setopt($ch, CURLOPT_HEADER, false);

		/* Execute cURL, Return Data */
		$this->responseData = curl_exec($ch);

		/* Check HTTP Code */
		$this->responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		/* Close cURL Resource */
		curl_close($ch);

	}
	protected function parseResponse($responseBody){
		$response = explode($this->responseDivider, $responseBody);
		if($response[0] == $this->responseError){
			return $this->responseWithError($response[1],$response[2]);
		}
		else {
			return $this->responseWithSuccess($response[1],$response[2]);
		}
	}

	protected function responseWithError($code,$message) {
		return response()->json(["success" => false, "data" => ['error' => $code, 'message' => urldecode($message)]]);
	}

	protected function responseWithSuccess($data) {
		return response()->json(["success" => false, "data" => [$data]]);
	}

	public function connect(){
		try {

			$this->doRequest($this->send_endpoint);

			if($this->responseStatus == 200) {
				return $this->parseResponse($this->responseData);
			}
			else { return 'General error'; }
		} catch (Exception $e) {

		}
	}

	public function sendMessage($to, $message)
	{
		$this->connectAndLogin();
		$this->whatsProt->sendMessageComposing($to);
		$id = $this->whatsProt->sendMessage($to, $message);
		$this->logoutAndDisconnect();
		return $id;
	}
}
