<?php namespace Codersmedia\TrendooSms;

use Config;
use Exception;

class Trendoo {
    /**
     * @var string $debug
     */
    protected static $debug;

    /**
     * @var string $username
     */
    protected static $username;

    /**
     * @var string $password
     */
    protected static $password;

    /**
     * @var string $type
     */
    protected static $message_type;

    /**
     * @var mixed $sender
     */
    protected static $sender;

    /**
     * @var DateTime $data
     */
    protected static $data;

    /**
     * @var mixed $base_url
     */
    protected static $base_url;

    /*****************************************************
     * DO NOT EDIT THE BELOW PARAM.
     * @link: http://www.trendoo.it/pdf/API_http.pdf
     *
     * SETTINGS
     *****************************************************/

    protected static $dateFormat                = 'yyyyMMddHHmmss';
    protected static $method                    = 'GET';
    protected static $responseColumnDivider     = '|';
    protected static $responseNewLineDivider    = ';';
    protected static $responseError             = 'KO';
    protected static $responseValid             = 'OK';
    protected static $iso                       = 'IT';    //ISO 3166
    protected static $charset                   = 'UTF-8';
    protected static $singleSmsChars            = 160;
    protected static $maxSmsChars               = 1000;
    protected static $specialChars              = ['^', '{', '}', '\\', '[', '~', ']', '|', '€'];
    protected static $send_endpoint             = 'Trend/SENDSMS';
    protected static $status_endpoint           = 'Trend/SMSSTATUS';
    protected static $remove_delayed_endpoint   = 'Trend/REMOVE_DELAYED';
    protected static $history_endpoint          = 'Trend/SMSHISTORY';
    protected static $credits_endpoint          = 'Trend/CREDITS';
    protected static $sms_new_endpoint          = 'OESRs/SRNEWMESSAGE';
    protected static $sms_history_endpoint      = 'OESRs/SRHISTORY';
    protected static $sms_historyid_endpoint    = 'OESRs/SRHISTORYBYID';

    /*******************
     * Response Status
     ******************/

    protected static $SCHEDULED     = 'Positicipato, non ancora inviato';
    protected static $SENT          = 'Inviato, non attende delivery';
    protected static $DLVRD         = 'Sms correttamente ricevuto';
    protected static $ERROR         = 'Errore nella consegna SMS';
    protected static $TIMEOUT       = 'Operatore non ha fornito informazioni sullo stato del messaggio entro 48 ore';
    protected static $TOOM4NUM      = 'Troppi SMS per lo stesso destinatario nelle ultime 24 ore';
    protected static $TOOM4USER     = 'Troppi SMS inviati dall\'utente nelle ultime 24 ore';
    protected static $UNKNPFX       = 'Prefisso SMS non valido o sconosciuto';
    protected static $UNKNRCPT      = 'Numero del destinatario non valido o sconosciuto';
    protected static $WAIT4DLVR     = 'Messaggio inviato, in attesa di delivery';
    protected static $WAITING       = 'In attesa, non ancora inviato';
    protected static $UNKNOWN       = 'Stato sconosciuto';


    /*******************
     * Response slug
     ******************/

    protected static $SI     = 'Sms SILVER';
    protected static $GS     = 'Sms GOLD';
    protected static $GP     = 'Sms GOLD+';
    protected static $EE     = 'Sms Esteri';

    /*****************************************************
     * END PARAM.
     * @link: http://www.trendoo.it/pdf/API_http.pdf
     *****************************************************/

    /*
     * Response Params
     */
    protected static $requestUrl;
    protected static $responseStatus = null;
    protected static $responseData = null;
    protected static $parsedResponse = null;

    protected static $smsChars = 0;

    public function __construct()
    {
	    self::$base_url		= Config::get("trendoo.base_url");
        self::$username   	= Config::get("trendoo.login");
        self::$password   	= Config::get("trendoo.password");
        self::$message_type = Config::get("trendoo.sms.message_type");
        self::$sender      	= Config::get("trendoo.sms.sender");
        self::$debug      	= Config::get("trendoo.debug");
    }

    /**
     * @return array
     */
    protected static function injectLoginParams(){
        return [
            'login' => self::$username,
            'password' => self::$password
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
    protected static function buildRequest($endpoint, Array $args = null){

        // Inject LOGIN and PASSWORD
        $loginParams = self::injectLoginParams();

        // Define ENDPOINT
        $url = self::$base_url . $endpoint;

        // $_GET Parameters to Send
        $params = ($args != null) ? array_merge($loginParams,$args) : $loginParams;

        // Update URL to container Query String of Paramaters
        $url .= '?' . http_build_query($params);

        return $url;
    }

    protected static function tryRequest($endpoint, Array $params = null){
        try {
            self::doRequest($endpoint, $params);

            if(self::$responseStatus == 200) {
                self::$parsedResponse = self::parseResponse($endpoint, self::$responseData);
            }
            else {
                self::$parsedResponse = self::responseWithError(0,'General error.');
            }
    
            return self::$parsedResponse;
        }
        catch (Exception $e) {
            self::$parsedResponse = self::responseWithError(0, $e->getMessage());
            return self::$parsedResponse;
        }
    }

    /**
     * @param $endpoint
     * @param array $args
     */
    protected static function doRequest($endpoint, Array $args = null){
        self::$requestUrl = self::buildRequest($endpoint, $args);

        // cURL Resource
        $ch = curl_init();

        // Set Curl Option
        $options = [
            // Set URL
            CURLOPT_URL => self::$requestUrl,
            // Tell cURL to return the output
            CURLOPT_RETURNTRANSFER => true,
            // Tell cURL NOT to return the headers
            CURLOPT_HEADER => false
        ];

        curl_setopt_array($ch, $options);

        // Execute cURL, Return Data
        self::$responseData = curl_exec($ch);

        // Check HTTP Code
        self::$responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close cURL Resource
        curl_close($ch);

    }


    /**************
     *    CHECK   *
     **************/

    protected static function charsCount($message = null){

        if ($message == null) return 0;
        $count = 0;
        foreach(self::$specialChars as $special) {
            $count += substr_count($message, $special);
        }
        $count += strlen($message);
        self::$smsChars = $count;

    }

    /**************
    *  	RESPONSE  *
    **************/

    /**
     * @param $responseBody
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected static function parseResponse($endpoint, $responseBody){
        if(substr($responseBody, 0, 2) == self::$responseError){
            $response = explode(self::$responseColumnDivider, $responseBody);
            return self::responseWithError($response[1],$response[2]);
        }
        else {
            switch($endpoint){
                case 'Trend/SENDSMS':
                    return self::sentParse($responseBody);
                    break;
                case 'Trend/SMSSTATUS':
                    return self::statusParse($responseBody);
                    break;
                case 'Trend/CREDITS':
                    return self::creditsParse($responseBody);
                    break;
                case 'Trend/REMOVE_DELAYED':
                    return self::rmDelayParse($responseBody);
                    break;
                case 'Trend/SMSHISTORY':
                    return self::historyParse($responseBody);
                    break;
                case 'OESRs/SRNEWMESSAGES':
                    return self::smsNewParse($responseBody);
                    break;
                case 'OESRs/SRHISTORY':
                    return self::smsHistoryParse($responseBody);
                    break;
                case 'OESRs/SRHYSTORYBYID':
                    return self::smsHistoryIdParse($responseBody);
                    break;
            }
            return self::responseWithError(0,'No endpoint defined');
        }
    }

    protected static function sentParse($response){
        // OK|1F11FEADCB6A4|1
        $data = explode(self::$responseColumnDivider, $response);
        if($data[0] == self::$responseValid) {
            return self::responseWithSuccess([
                'order_id' => $data[1],
                //TODO if returnCredits = true this return the credit used for request
                // and not the sensers count.
                'senders' => $data[2]
            ]);
        }
    }

    protected static function statusParse($response){
        // OK;recipient_number|status|delivery_date;...;
        $data = explode(self::$responseNewLineDivider, $response);
        $parsed=[];
        $i = 0;
        if($data[0] == self::$responseValid) {
            foreach($data as $element) {
                if ($i++ == 0) continue;
                if($i == count($data)) break;
                $tmp = explode(self::$responseColumnDivider, $element);
                $parsed[] = [
                    'recipient' => $tmp[0],
                    'status' => $tmp[1],
                    'status_message' => self::statusToMessage($tmp[1]),
                    'delivery_date' => isset($tmp[0]) ?: 'N/A',
                ];
            }

            return self::responseWithSuccess($parsed);
        }
    }

    protected static function creditsParse($response){
        // OK;GS|IT|37;GP|IT|37;SI|IT|37;GS|ES|56;GP|ES|56;SI|ES|100;EE||81
        $data = explode(self::$responseNewLineDivider, $response);
        $parsed = null;
        $i = 0;
        if($data[0] == self::$responseValid) {
            foreach($data as $element) {
                if (($i++ == 0)) continue;
                if($i == count($data)) break;
                $tmp = explode(self::$responseColumnDivider, $element);
                $parsed[] = [
                    'type' => $tmp[0],
                    'type_human' => self::${$tmp[0]},
                    'nation' => $tmp[1],
                    'count' => $tmp[2]
                ];
            }
            return self::responseWithSuccess($parsed);
        }
    }

    protected static function rmDelayParse($response)
    {
        $data = explode(self::$responseNewLineDivider, $response);
        $parsed = null;
        if($data[0] == self::$responseValid) {
            return self::responseWithSuccess($parsed);
        }
    }


    protected static function historyParse($response)
    {
        //OK;
        //|20090217164232|GS||2|20090219164153; {6}
        //1F11FEAD08FE0|20090309113910|GP|test|1|; {6}
        //|20090309120139|GP|mItTeNtE|2| {6}

        $data = explode(self::$responseNewLineDivider, $response);
        $parsed = null;
        $i = 0;
        if($data[0] == self::$responseValid) {
            foreach($data as $element) {
                if (($i++ == 0)) continue;
                if($i == count($data)) break;
                $tmp = explode(self::$responseColumnDivider, $element);
                $parsed[] = [
                    'message_id' => $tmp[0],
                    'creation_date' => $tmp[1],
                    'type' => $tmp[2],
                    'type_human' => self::${$tmp[2]},
                    'sender' => $tmp[3],
                    'recipient_count' => $tmp[4],
                    'send_at' => $tmp[5]
                ];
            }
            return self::responseWithSuccess($parsed);
        }
    }

    protected static function smsNewParse($response)
    {
        //OK;

        $data = explode(self::$responseNewLineDivider, $response);
        $parsed = null;
        if($data[0] == self::$responseValid) {
            return self::responseWithSuccess($parsed);
        }
    }
    protected static function smsHistoryParse($response)
    {
        // OK;83342|%2B393407249303|%2B393493888678|FURTO+NEROCUBO|20150930101353|;

        $data = explode(self::$responseNewLineDivider, $response);
        $parsed = null;
        $i = 0;
        if($data[0] == self::$responseValid) {
            foreach($data as $element) {
                if (($i++ == 0)) continue;
                if($i == count($data)) break;
                $tmp = explode(self::$responseColumnDivider, $element);
                $parsed[] = [
                    'message_id' => $tmp[0],
                    'recipient' => $tmp[1],
                    'sender' => $tmp[3],
                    'message' => $tmp[4],
                    'send_at' => $tmp[5]
                ];
            }
            return self::responseWithSuccess($parsed);
        }
    }

    protected static function smsHistoryIdParse($response)
    {
        $data = explode(self::$responseNewLineDivider, $response);
        $parsed = null;
        if($data[0] == self::$responseValid) {
            return self::responseWithSuccess($parsed);
        }
    }

    protected static function smsPostParse($request)
    {
        // OK;83342|%2B393407249303|%2B393493888678|FURTO+NEROCUBO|20150930101353|;

        $data = explode(self::$responseNewLineDivider, $request);
        $parsed = null;
        $i = 0;
        
        foreach($data as $element) {
            if($i == count($data)) break;
            $tmp = explode(self::$responseColumnDivider, $element);
            $parsed[] = [
                'message_id' => $tmp[0],
                'message' => $tmp[1],
                'sender' => $tmp[3],
                'recipient' => $tmp[4],
                'delivery_date' => $tmp[5]
            ];
        }
        return self::responseWithSuccess($parsed);
    }

    protected static function statusToMessage($status){
        return self::${$status};
    }

    /**
     * @param $code
     * @param $message
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected static function responseWithError($code,$message) {
        $response = [
            "success" => false,
            "data" => [
                'error' => $code,
                'message' => urldecode($message)]
        ];
        if(self::$debug == true) $response['data']['request'] = self::$requestUrl;
        return response()->json($response);
    }

    /**
     * @param $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected static function responseWithSuccess($data) {
        return response()->json(["success" => true, "data" => [$data]]);
    }

    protected static function createDateTime($data){
        self::$data = \DateTime::createFromFormat(self::$dateFormat,$data);
    }

    protected static function generateError($message, $code = 502){
        return self::responseWithError($code,$message);
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
    public static function sendMessage(Array $recipients, $message, $option = null)
    {

        $recipients = implode(',',$recipients);

        $params = [
            'message' => $message,
            'recipient' => $recipients
        ];

        if($option) {
            $params['message_type'] = isset($option['message_type']) ? $option['message_type'] : self::$message_type;
            $params['sender'] = isset($option['sender']) ? $option['sender'] : self::$sender;
            if (isset($option['scheduled_delivery_time'])) $params['scheduled_delivery_time'] = $option['scheduled_delivery_time'];
            if (isset($option['order_id'])) $params['order_id'] = $option['order_id'];
            if (isset($option['returnCredits'])) $params['returnCredits'] = $option['returnCredits'];
        }
        else {
            $params['message_type'] = self::$message_type;
            $params['sender'] = self::$sender;
        }

        // $this->smsChars
        self::charsCount($message);

        self::tryRequest(self::$send_endpoint, $params);

    }

    public static function checkCredits(){
        return self::tryRequest(self::$credits_endpoint);
    }

    public static function getData($format){
        return date_format(self::$data,$format);
    }

    public static function SMSByPost($request){
        return self::smsPostParse($request);
    }

    public static function historySMS(){
        return self::tryRequest(self::$sms_history_endpoint);
    }
    
    /**
     * @param $order_id
     * @return mixed
     */
    public static function removeDelayed($order_id)
    {
        $params = [
            'order_id' => $order_id
        ];
        
        self::tryRequest(self::$remove_delayed_endpoint, $params);
        
    }
    
    /**
     * @param $order_id
     * @return mixed
     */
    public static function smsStatus($order_id)
    {
        $params = [
            'order_id' => $order_id
        ];
        
        self::tryRequest(self::$status_endpoint, $params);
        
    }
    
    /**
     * @param       $endpoint
     * @param array $params
     *
     * @return mixed
     */
    public static function genericRequest($endpoint, Array $params)
    {
        self::tryRequest($endpoint, $params);
        
    }
    
    public static function getResponseData(  )
    {
       return self::$responseData;
    }
        
    public static function getResponseCode(  )
    {
        return self::$responseCode;
    }
        
    public static function getParsedResponse(  )
    {
        return self::$parsedResponse;
    }


}
