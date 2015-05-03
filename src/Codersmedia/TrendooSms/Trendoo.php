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
     * HTTP REQUEST
     */
	protected $client;
	protected $request;

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
	protected $base_url          = 'https://api.trendoo.it/Trend/';
	protected $send_endpoint    = 'SENDSMS';

	public function __construct()
	{
		$this->username   = Config::get("trendoo.login");
		$this->password   = Config::get("trendoo.password");
		$this->debug      = Config::get("trendoo.debug");
	}

	protected function buildRequest(Array $params = null){

		$this->client = new Client([
			'base_url' => ['{base_url}{request_url}',
				[
					'base_url' => $this->base_url,
					'request_url' => $this->request
				]
			],
			'defaults' => [
				'query'   => [
					'login' => $this->username,
					'password' => $this->password,
					$params
				],
			]
		]);
	}

	public function connect(){
		$this->buildRequest(null);
		echo 'prova';
		dd($this->client);
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
