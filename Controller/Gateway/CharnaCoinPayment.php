<?php

namespace CharnaCoin\Custompayment\Controller\Gateway;

use Magento\Framework\App\Action\Context;

// Monero_Library is just the contents of library.php. It's super messy but works for now
class Charna_Library
{
    protected $url = null, $is_debug = false, $parameters_structure = 'array';
        
    protected $curl_options = array(
                                    CURLOPT_CONNECTTIMEOUT => 8,
                                    CURLOPT_TIMEOUT => 8
                                    );
    
        
    private $httpErrors = array(
                                400 => '400 Bad Request',
                                401 => '401 Unauthorized',
                                403 => '403 Forbidden',
                                404 => '404 Not Found',
                                405 => '405 Method Not Allowed',
                                406 => '406 Not Acceptable',
                                408 => '408 Request Timeout',
                                500 => '500 Internal Server Error',
                                502 => '502 Bad Gateway',
                                503 => '503 Service Unavailable'
                                );
        
    public function __construct($pUrl)
    {
        $this->validate(false === extension_loaded('curl'), 'The curl extension must be loaded for using this class!');
        $this->validate(false === extension_loaded('json'), 'The json extension must be loaded for using this class!');
            
        $this->url = $pUrl;
    }
        
    private function getHttpErrorMessage($pErrorNumber)
    {
        return isset($this->httpErrors[$pErrorNumber]) ? $this->httpErrors[$pErrorNumber] : null;
    }
        
    public function setDebug($pIsDebug)
    {
        $this->is_debug = !empty($pIsDebug);
        return $this;
    }
        
        /*  public function setParametersStructure($pParametersStructure)
         {
         if (in_array($pParametersStructure, array('array', 'object')))
         {
         $this->parameters_structure = $pParametersStructure;
         }
         else
         {
         throw new UnexpectedValueException('Invalid parameters structure type.');
         }
         return $this;
         } */
        
    public function setCurlOptions($pOptionsArray)
    {
        if (is_array($pOptionsArray))
        {
            $this->curl_options = $pOptionsArray + $this->curl_options;
        }
        else
        {
            throw new InvalidArgumentException('Invalid options type.');
        }
        return $this;
    }
        
    public function _run($pMethod, $pParams=null)
    {
        static $requestId = 0;
        // generating uniuqe id per process
        $requestId++;
        // check if given params are correct
        $this->validate(false === is_scalar($pMethod), 'Method name has no scalar value');
        // $this->validate(false === is_array($pParams), 'Params must be given as array');
        // send params as an object or an array
        //$pParams = ($this->parameters_structure == 'object') ? $pParams[0] : array_values($pParams);
        // Request (method invocation)
        $request = json_encode(array('jsonrpc' => '2.0', 'method' => $pMethod, 'params' => $pParams, 'id' => $requestId));
        // if is_debug mode is true then add url and request to is_debug
        $this->debug('Url: ' . $this->url . "\r\n", false);
        $this->debug('Request: ' . $request . "\r\n", false);
        $responseMessage = $this->getResponse($request);
        // if is_debug mode is true then add response to is_debug and display it
        $this->debug('Response: ' . $responseMessage . "\r\n", true);
        // decode and create array ( can be object, just set to false )
        $responseDecoded = json_decode($responseMessage, true);
        // check if decoding json generated any errors
        $jsonErrorMsg = $this->getJsonLastErrorMsg();
        $this->validate( !is_null($jsonErrorMsg), $jsonErrorMsg . ': ' . $responseMessage);
        // check if response is correct
        $this->validate(empty($responseDecoded['id']), 'Invalid response data structure: ' . $responseMessage);
        $this->validate($responseDecoded['id'] != $requestId, 'Request id: ' . $requestId . ' is different from Response id: ' . $responseDecoded['id']);
        if (isset($responseDecoded['error']))
        {
            $errorMessage = 'Request have return error: ' . $responseDecoded['error']['message'] . '; ' . "\n" .
                'Request: ' . $request . '; ';
            if (isset($responseDecoded['error']['data']))
            {
                $errorMessage .= "\n" . 'Error data: ' . $responseDecoded['error']['data'];
            }
            $this->validate( !is_null($responseDecoded['error']), $errorMessage);
        }
        return $responseDecoded['result'];
    }
    protected function & getResponse(&$pRequest)
    {
        // do the actual connection
        $ch = curl_init();
        if ( !$ch)
        {
            throw new RuntimeException('Could\'t initialize a cURL session');
        }
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $pRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ( !curl_setopt_array($ch, $this->curl_options))
        {
            throw new RuntimeException('Error while setting curl options');
        }
        // send the request
        $response = curl_exec($ch);
        // check http status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (isset($this->httpErrors[$httpCode]))
        {
            throw new RuntimeException('Response Http Error - ' . $this->httpErrors[$httpCode]);
        }
        // check for curl error
        if (0 < curl_errno($ch))
        {
            throw new RuntimeException('Unable to connect to '.$this->url . ' Error: ' . curl_error($ch));
        }
        // close the connection
        curl_close($ch);
        return $response;
    }
        
    public function validate($pFailed, $pErrMsg)
    {
        if ($pFailed)
        {
            throw new RuntimeException($pErrMsg);
        }
    }
        
    protected function debug($pAdd, $pShow = false)
    {
        static $debug, $startTime;
        // is_debug off return
        if (false === $this->is_debug)
        {
            return;
        }
        // add
        $debug .= $pAdd;
        // get starttime
        $startTime = empty($startTime) ? array_sum(explode(' ', microtime())) : $startTime;
        if (true === $pShow and !empty($debug))
        {
            // get endtime
            $endTime = array_sum(explode(' ', microtime()));
            // performance summary
            $debug .= 'Request time: ' . round($endTime - $startTime, 3) . ' s Memory usage: ' . round(memory_get_usage() / 1024) . " kb\r\n";
            echo nl2br($debug);
            // send output imidiately
            flush();
            // clean static
            $debug = $startTime = null;
        }
    }
        
    function getJsonLastErrorMsg()
    {
        if (!function_exists('json_last_error_msg'))
        {
            function json_last_error_msg()
            {
                static $errors = array(
                                        JSON_ERROR_NONE           => 'No error',
                                        JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
                                        JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
                                        JSON_ERROR_CTRL_CHAR      => 'Unexpected control character found',
                                        JSON_ERROR_SYNTAX         => 'Syntax error',
                                        JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
                                        );
                $error = json_last_error();
                return array_key_exists($error, $errors) ? $errors[$error] : 'Unknown error (' . $error . ')';
            }
        }
        
        // Fix PHP 5.2 error caused by missing json_last_error function
        if (function_exists('json_last_error'))
        {
            return json_last_error() ? json_last_error_msg() : null;
        }
        else
        {
            return null;
        }
    }
    
    public function address()
    {
        $address = $this->_run('getaddress');
        return $address;
    }
    
    public function getbalance()
    {
        $balance = $this->_run('getbalance');
        return $balance;
    }
    public function getheight()
    {
        $height = $this->_run('getheight');
        return $height;
    }
    public function incoming_transfer($type)
    {
        $incoming_parameters = array('transfer_type' => $type);
        $incoming_transfers = $this->_run('incoming_transfers', $incoming_parameters);
        return $incoming_transfers;
    }
    public function get_transfers($input_type, $input_value)
    {
        $get_parameters = array($input_type => $input_value);
        $get_transfers = $this->_run('get_transfers', $get_parameters);
        return $get_transfers;
    }
    public function view_key()
    {
        $query_key = array('key_type' => 'view_key');
        $query_key_method = $this->_run('query_key', $query_key);
        return $query_key_method;
    }
    public function make_integrated_address($payment_id)
    {
        $integrate_address_parameters = array('payment_id' => $payment_id);
        $integrate_address_method = $this->_run('make_integrated_address', $integrate_address_parameters);
        return $integrate_address_method;
    }
    /* A payment id can be passed as a string
     A random payment id will be generatd if one is not given */
    public function split_integrated_address($integrated_address)
    {
        if (!isset($integrated_address)) {
            echo "Error: Integrated_Address mustn't be null";
        } else {
            $split_params = array('integrated_address' => $integrated_address);
            $split_methods = $this->_run('split_integrated_address', $split_params);
                return $split_methods;
        }
    }
    public function make_uri($address, $amount, $recipient_name = null, $description = null)
    {
        // If I pass 1, it will be 0.0000001 chrc. Then
        $new_amount = $amount * 100000000;
        $uri_params = array('address' => $address, 'amount' => $new_amount, 'payment_id' => '', 'recipient_name' => $recipient_name, 'tx_description' => $description);
        $uri = $this->_run('make_uri', $uri_params);
        return $uri;
    }
    public function parse_uri($uri)
    {
        $uri_parameters = array('uri' => $uri);
        $parsed_uri = $this->_run('parse_uri', $uri_parameters);
        return $parsed_uri;
    }
    public function transfer($amount, $address, $mixin = 4)
    {
        $new_amount = $amount * 100000000;
        $destinations = array('amount' => $new_amount, 'address' => $address);
        $transfer_parameters = array('destinations' => array($destinations), 'mixin' => $mixin, 'get_tx_key' => true, 'unlock_time' => 0, 'payment_id' => '');
        $transfer_method = $this->_run('transfer', $transfer_parameters);
        return $transfer_method;
    }
    public function get_payments($payment_id)
    {
        $get_payments_parameters = array('payment_id' => $payment_id);
        $get_payments = $this->_run('get_payments', $get_payments_parameters);
        return $get_payments;
    }
    public function get_bulk_payments($payment_id, $min_block_height)
    {
        $get_bulk_payments_parameters = array('payment_id' => $payment_id, 'min_block_height' => $min_block_height);
        $get_bulk_payments = $this->_run('get_bulk_payments', $get_bulk_payments_parameters);
        return $get_bulk_payments;
    }
}

class Monero
{
    private $charna_daemon;
    
    public function __construct($rpc_address, $rpc_port)
    {
        $this->charna_daemon = new CharnaCoin_Library('http://' . $rpc_address . ':'. $rpc_port . '/json_rpc'); // TODO: Get address:port from admin panel
    }
    
    public function retriveprice($currency)
    {
        $xmr_price = file_get_contents('https://www.southxchange.com/api/prices');
        $price = json_decode($chrc_price, TRUE);
        switch ($currency) {
            case 'USD':
                return $price['USD'];
            case 'EUR':
                return $price['EUR'];
            case 'GBP':
                return $price['GBP'];
            case 'LTC':
                return $price['LTC'];
            case 'CHRC':
                $price = '1';
                return $price;
        }
    }
    
    public function paymentid_cookie()
    {
        if (!isset($_COOKIE['payment_id']))
        {
            $payment_id = bin2hex(openssl_random_pseudo_bytes(8));
            setcookie('payment_id', $payment_id, time() + 2700);
        }
        else
            $payment_id = $_COOKIE['payment_id'];
        return $payment_id;
    }
    
    public function changeto($amount, $currency)
    {
        $rate = $this->retriveprice($currency);
        $price_converted = $amount / $rate;
        $converted_rounded = round($price_converted, 8);
            return $converted_rounded;
    }
    
    public function verify_payment($payment_id, $amount)
    {
        $message = "We are waiting for your payment to be confirmed";
        $amount_atomic_units = $amount * 100000000;
        $get_payments_method = $this->charna_daemon->get_payments($payment_id);
        if (isset($get_payments_method["payments"][0]["amount"]))
        {
            if ($get_payments_method["payments"][0]["amount"] >= $amount_atomic_units)
            {
                $message = "Payment has been received and confirmed. Thanks!";
                return true;
            }
        }
        return false;
    }
    public function integrated_address($payment_id)
    {
        $integrated_address = $this->charna_daemon->make_integrated_address($payment_id);
        $parsed_address = $integrated_address['integrated_address'];
        return $parsed_address;
    }
}
    
class CharnaCoinPayment extends \Magento\Framework\App\Action\Action
{
    public function __construct(\CharnaCoin\Custompayment\Helper\Data $helper,\Magento\Checkout\Model\Session $checkoutSession, \Magento\Framework\App\Action\Context $context)
    {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }
    public $charna;
    
    public function execute()
    {
        $rpc_address = $this->helper->grabConfig('payment/custompayment/rpc_address');
        $rpc_port = $this->helper->grabConfig('payment/custompayment/rpc_port');
        $charna = new CharnaCoin($rpc_address, $rpc_port);
        
        $currency = 'USD';
        $grandTotal = $this->checkoutSession->getQuote()->getGrandTotal();
        
        $price = $charna->changeto($grandTotal, $currency);
        $payment_id = $charna->paymentid_cookie();
        $integrated_address = $charna->integrated_address($payment_id);
        $status = $charna->verify_payment($payment_id, $price);
        if($status)
        {
            $status_message = "Payment has been received and confirmed. Thanks!";
        }
        else{
            $status_message =  "we are waiting for your payment to be confirmed";
        }
        echo "
        <head>
        <!--Import Google Icon Font-->
        <link href='https://fonts.googleapis.com/icon?family=Material+Icons' rel='stylesheet'>
        <link href='https://fonts.googleapis.com/css?family=Montserrat:400,800' rel='stylesheet'>
        
        <link href='http://cdn.monerointegrations.com/style.css' rel='stylesheet'>
        
        <!--Let browser know website is optimized for mobile-->
            <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
            </head>
            
            <body>
            <!-- page container  -->
            <div class='page-container'>
            
            
            <!-- monero container payment box -->
            <div class='container-chrc-payment'>
            
            
            <!-- header -->
            <div class='header-chrc-payment'>
            <span class='logo-chrc'><img src='http://cdn.monerointegrations.com/logomonero.png' /></span>
            <span class='chrc-payment-text-header'><h2>CharnaCoin Payment $status_message</h2></span>
            </div>
            <!-- end header -->
            
            <!-- chrc content box -->
            <div class='content-chrc-payment'>
            
            <div class='chrc-amount-send'>
            <span class='chrc-label'>Send:</span>
            <div class='chrc-amount-box'>$price</div><div class='chrc-box'>CHRC</div>
            </div>
            
            <div class='chrc-address'>
            <span class='chrc-label'>To this address:</span>
            <div class='chrc-address-box'>$integrated_address</div>
            </div>
            <div class='chrc-qr-code'>
            <span class='chrc-label'>Or scan QR:</span>
            <div class='chrc-qr-code-box'><img src='https://api.qrserver.com/v1/create-qr-code/? size=200x200&data=charna:$integrated_address' /></div>
            </div>
            
            <div class='clear'></div>
            </div>
            
            <!-- end content box -->
            
            <!-- footer chrc payment -->
            <div class='footer-chrc-payment'>
            <a href='https://www.charnacoin.com'>About CharnaCoin</a>
            </div>
            <!-- end footer chrc payment -->
            
            </div>
            <!-- end charna container payment box -->
            
            </div>
            <!-- end page container  -->
            </body>
            ";
            echo "<script type='text/javascript'>setTimeout(function () { location.reload(true); }, 30000);</script>";
    }
}
