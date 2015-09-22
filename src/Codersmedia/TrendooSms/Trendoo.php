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

    /**
     * @var string $type
     */
    protected $message_type;

    /**
     * @var mixed $sender
     */
    protected $sender;

    /**
     * @var DateTime $data
     */
    protected $data;

    /*****************************************************
     * DO NOT EDIT THE BELOW PARAM.
     * @link: http://www.trendoo.it/pdf/API_http.pdf
     *
     * SETTINGS
     *****************************************************/

    protected $dateFormat       		= 'yyyyMMddHHmmss';
    protected $method          			= 'GET';
    protected $responseColumnDivider  	= '|';
    protected $responseNewLineDivider  	= ';';
    protected $responseError    		= 'KO';
    protected $responseValid    		= 'OK';
    protected $iso              		= 'IT';    //ISO 3166
    protected $charset          		= 'UTF-8';
    protected $singleSmsChars   		= 160;
    protected $maxSmsChars      		= 1000;
    protected $specialChars     		= ['^', '{', '}', '\\', '[', '~', ']', '|', '€'];
    protected $base_url         		= "https://api.trendoo.it/Trend/";
    protected $send_endpoint    		= 'SENDSMS';
    protected $status_endpoint  		= 'SMSSTATUS';
    protected $remove_delayed_endpoint  = 'REMOVE_DELAYED';
    protected $history_endpoint  		= 'SMSDELAYED';
    protected $credits_endpoint  		= 'CREDITS';

    /*******************
     * Response Status
     ******************/

    protected $SCHEDULED 	= 'Positicipato, non ancora inviato';
    protected $SENT 		= 'Inviato, non attende delivery';
    protected $DLVRD 		= 'Sms correttamente ricevuto';
    protected $ERROR 		= 'Errore nella consegna SMS';
    protected $TIMEOUT 		= 'Operatore non ha fornito informazioni sullo stato del messaggio entro 48 ore';
    protected $TOOM4NUM 	= 'Troppi SMS per lo stesso destinatario nelle ultime 24 ore';
    protected $TOOM4USER 	= 'Troppi SMS inviati dall\'utente nelle ultime 24 ore';
    protected $UNKNPFX 		= 'Prefisso SMS non valido o sconosciuto';
    protected $UNKNRCPT 	= 'Numero del destinatario non valido o sconosciuto';
    protected $WAIT4DLVR 	= 'Messaggio inviato, in attesa di delivery';
    protected $WAITING 		= 'In attesa, non ancora inviato';
    protected $UNKNOWN 		= 'Stato sconosciuto';


    /*******************
     * Response slug
     ******************/

    protected $SI	= 'Sms SILVER';
    protected $GS	= 'Sms GOLD';
    protected $GP	= 'Sms GOLD+';

    /*****************************************************
     * END PARAM.
     * @link: http://www.trendoo.it/pdf/API_http.pdf
     *****************************************************/

    /*
     * Response Params
     */
    protected $requestUrl;
    protected $responseStatus = null;
    protected $responseData = null;

    protected $smsChars = 0;

    public function __construct()
    {
        $this->username   	= Config::get("trendoo.login");
        $this->password   	= Config::get("trendoo.password");
        $this->message_type = Config::get("trendoo.sms.message_type");
        $this->sender      	= Config::get("trendoo.sender");
        $this->debug      	= Config::get("trendoo.debug");
    }

    /**
     * @return array
     */
    protected function injectLoginParams(){
        return [
            'login' => $this->username,
            'password' => $this->password
        ];
    }


    /**************
     *   REQUEST  *
     **************/

    /**
     * @param $endpoint
     * @param array $args
     * @return string
     */
    protected function buildRequest($endpoint, Array $args = null){

        // Inject LOGIN and PASSWORD
        $loginParams = $this->injectLoginParams();

        // Define ENDPOINT
        $url = $this->base_url . $endpoint;

        // $_GET Parameters to Send
        $params = ($args != null) ? array_merge($loginParams,$args) : $loginParams;

        // Update URL to container Query String of Paramaters
        $url .= '?' . http_build_query($params);

        return $url;
    }

    protected function tryRequest($endpoint, Array $params = null){
        try {

            $this->doRequest($endpoint, $params);

            if($this->responseStatus == 200) {
                return $this->parseResponse($endpoint, $this->responseData);
            }
            else { return $this->responseWithError(0,'General error.'); }
        } catch (Exception $e) {
            return $this->responseWithError(0, $e->getMessage());
        }
    }

    /**
     * @param $endpoint
     * @param array $args
     */
    protected function doRequest($endpoint, Array $args = null){
        $this->requestUrl = $this->buildRequest($endpoint, $args);

        // cURL Resource
        $ch = curl_init();

        // Set Curl Option
        $options = [
            // Set URL
            CURLOPT_URL => $this->requestUrl,
            // Tell cURL to return the output
            CURLOPT_RETURNTRANSFER => true,
            // Tell cURL NOT to return the headers
            CURLOPT_HEADER => false
        ];

        curl_setopt_array($ch, $options);

        // Execute cURL, Return Data
        $this->responseData = curl_exec($ch);

        // Check HTTP Code
        $this->responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close cURL Resource
        curl_close($ch);

    }


    /**************
     *    CHECK   *
     **************/

    protected function charsCount($message = null){

        if ($message == null) return 0;
        $count = 0;
        foreach($this->specialChars as $special) {
            $count += substr_count($message, $special);
        }
        $count += strlen($message);
        $this->smsChars = $count;

    }

    /**************
    *  	RESPONSE  *
    **************/

    /**
     * @param $responseBody
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function parseResponse($endpoint, $responseBody){
        if(substr($responseBody, 0, 2) == $this->responseError){
            $response = explode($this->responseColumnDivider, $responseBody);
            return $this->responseWithError($response[1],$response[2]);
        }
        else {
            switch($endpoint){
                case 'SENDSMS':
                    return $this->sentParse($responseBody);
                    break;
                case 'SMSSTATUS':
                    return $this->statusParse($responseBody);
                    break;
                case 'CREDITS':
                    return $this->creditsParse($responseBody);
                    break;
                case 'REMOVE_DELAYED':
                    return $this->rmDelayParse($responseBody);
                    break;
                case 'SMSDELAYED':
                    return $this->delayedParse($responseBody);
                    break;
            }
            return $this->responseWithError(0,'No endpoint defined');
        }
    }

    protected function sentParse($response){
        // OK|1F11FEADCB6A4|1
        $data = explode($this->responseColumnDivider, $response);
        if($data[0] == $this->responseValid) {
            return $this->responseWithSuccess([
                'order_id' => $response[1],
                //TODO if returnCredits = true this return the credit used for request
                // and not the sensers count.
                'senders' => $response[2]
            ]);
        }
    }

    protected function statusParse($response){
        // OK;recipient_number|status|delivery_date;...;
        $data = explode($this->responseNewLineDivider, $response);
        $parsed=[];
        $i = 0;
        if($data[0] == $this->responseValid) {
            foreach($data as $element) {
                if ($i++ == 0) continue;
                if($i == count($data)) break;
                $tmp = explode($this->responseColumnDivider, $element);
                $parsed[] = [
                    'recipient' => $tmp[0],
                    'status' => $tmp[1],
                    'status_message' => $this->statusToMessagge($tmp[1]),
                    'delivery_date' => isset($tmp[0]) ?: 'N/A',
                ];
            }

            return $this->responseWithSuccess($parsed);
        }
    }

    protected function creditsParse($response){
        // OK;GS|IT|37;GP|IT|37;SI|IT|37;GS|ES|56;GP|ES|56;SI|ES|100;EE||81
        $data = explode($this->responseNewLineDivider, $response);
        $parsed = null;
        $i = 0;
        if($data[0] == $this->responseValid) {
            foreach($data as $element) {
                if (($i++ == 0)) continue;
                if($i == count($data)) break;
                $tmp = explode($this->responseColumnDivider, $element);
                $parsed[] = [
                    'type' => $tmp[0],
                    'type_human' => $this->{$tmp[0]},
                    'nation' => $tmp[1],
                    'count' => $tmp[2]
                ];
            }
            return $this->responseWithSuccess($parsed);
        }
    }

    protected function statusToMessagge($status){
        return $this->{$status};
    }

    /**
     * @param $code
     * @param $message
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function responseWithError($code,$message) {
        $response = [
            "success" => false,
            "data" => [
                'error' => $code,
                'message' => urldecode($message)]
        ];
        if($this->debug == true) $response['data']['request'] = $this->requestUrl;
        return response()->json($response);
    }

    /**
     * @param $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function responseWithSuccess($data) {
        return response()->json(["success" => true, "data" => [$data]]);
    }


    /*
     *
     * PUBLIC METHOD REFERER TO THIS
     *
     */

    /**
     * @param $to
     * @param $message
     * @return mixed
     */
    public function sendMessage(Array $recipients, $message, $option = null)
    {
        $params = [
            'message' => $message
        ];

        if($option) {
            $params['message_type'] = isset($option['message_type']) ?: $this->message_type;
            $params['sender'] = isset($option['sender']) ?: $this->sender;
            if (isset($option['scheduled_delivery_time'])) $params['scheduled_delivery_time'] = $option['scheduled_delivery_time'];
            if (isset($option['order_id'])) $params['order_id'] = $option['order_id'];
            if (isset($option['returnCredits'])) $params['returnCredits'] = $option['returnCredits'];
        }

        // $this->smsChars
        $this->charsCount($message);

        $this->tryRequest($this->send_endpoint, $params);

    }


    public function checkCredits(){
        return $this->tryRequest($this->credits_endpoint);
    }

    protected function createDateTime($data){
        $this->data = \DateTime::createFromFormat($this->dateFormat,$data);
    }
    public function getData($format){
        return date_format($this->data,$format);
    }

    protected function generateError($message, $code = 502){
        return $this->responseWithError($code,$message);
        die();
    }


}
