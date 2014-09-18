<?php namespace Comodojo\Httprequest;

use \Comodojo\Exception\HttpException;

/**
 * HTTP requests library for comodojo   
 * 
 * @package     Comodojo Spare Parts
 * @author      Marco Giovinazzi <info@comodojo.org>
 * @license     GPL-3.0+
 *
 * LICENSE:
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
 
class Httprequest {

    /**
     * Remote host address (complete url)
     *
     * @var url
     */
    private $address = NULL;
    
    /**
     * Remote host port
     *
     * @var integer
     */
    private $port = 80;
    
    /**
     * Conversation method (GET or POST)
     *
     * @var string
     */
    private $method = 'GET';
    
    /**
     * Timeout for request, in seconds.
     *
     * @var integer
     */
    private $timeout = 30;
    
    /**
     * HTTP Version (1.0/1.1)
     *
     * @var string
     */
    private $httpVersion = "1.0";
    
    /**
     * Auth method to use. It currently support only:
     * - BASIC
     * - NTLM (only if CURL is available)
     *
     * @var string
     */
    private $authenticationMethod = false;
    
    /**
     * Remote host auth username
     *
     * @var string
     */
    private $user = NULL;
    
    /**
     * Remote host auth password
     *
     * @var string
     */
    private $pass = NULL;

    /**
     * Request user agent
     * 
     * @var string
     */
    private $userAgent = 'Comodojo-Dispatcher';
    
    /**
     * Content type
     * 
     * @var string
     */
    private $contentType = 'application/x-www-form-urlencoded';

    /**
     * array of headers to send
     *
     * @var array
     */
    private $headers = array(
        'Accept'            =>  'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language'   =>  'en-us,en;q=0.5',
        'Accept-Encoding'   =>  'deflate',
        'Accept-Charset'    =>  'UTF-8;q=0.7,*;q=0.7'
    );

    /**
     * Should we use a proxy?
     *
     * @var string
     */
    private $proxy = NULL;

    private $proxy_auth = NULL;

    /**
     * Allowed HTTP methods
     *
     * @var array
     */
    private $supported_auth_methods = array("BASIC","NTLM");

    /**
     * Allowed HTTP authentication
     *
     * @var array
     */
    private $supported_http_methods = array("GET","POST","PUT","DELETE");


    /**
     * Are we using curl?
     */
    private $curl = true;
    
    private $receivedHeaders = array();

    /**
     * Transfer channel
     * @var resource
     */
    private $ch = false;
    
    public final function __construct($address, $curl=true) {

        $curl = filter_var($curl, FILTER_VALIDATE_BOOLEAN);

        $url = filter_var($address, FILTER_VALIDATE_URL);

        if ( $url === false ) throw new HttpException("Invalid remote address");
        
        $this->address = $address;

        if ( !function_exists("curl_init") OR !$curl ) {
            
            $this->curl = false;
            
            // \comodojo\Dispatcher\debug("httprequest will use streams (compatibility mode)","DEBUG","httprequest");

        }
        else {

            $this->curl = true;

            // \comodojo\Dispatcher\debug("httprequest will use curl","DEBUG","httprequest");

        }

    }

    public final function __destruct() {

        if ( $this->ch !== false ) $this->close_transport();

    }

    /**
     * Set http authentication
     *
     * @param   string  $method Auth method (BASIC or NTLM)
     * @param   string  $user   Username to use
     * @param   string  $pass   User password
     *
     * @return  Object  $this
     */
    public final function setAuth($method, $user, $pass=NULL) {

        $method = strtoupper($method);

        if ( !in_array($method, $this->supported_auth_methods) ) {

            // \comodojo\Dispatcher\debug($method." is not a valid auth method", "ERROR", "httprequest");

            throw new HttpException("Unsupported authentication method");

        }

        if ( empty($user) ) {

            throw new HttpException("User name cannot be null");

        }

        $this->user = $user;
        $this->pass = $pass;
        
        // \comodojo\Dispatcher\debug("Using auth method: ".$method,"DEBUG","httprequest");
        
        return $this;

    }

    /**
     * Set user agent for request
     *
     * @param   string  $ua     User Agent
     *
     * @return  Object  $this
     */
    public final function setUserAgent($ua) {

        if ( empty($ua) ) throw new HttpException("Useragent cannot be null");

        $this->userAgent = $ua;

        // \comodojo\Dispatcher\debug("Using user agent: ".$ua, "DEBUG", "httprequest");

        return $this;

    }

    /**
     * Set connection timeout
     *
     * @param   int $sec    Timeout to wait for (in second)
     *
     * @return  Object  $this
     */
    public final function setTimeout($sec) {

        $time = filter_var($sec, FILTER_VALIDATE_INT);

        $this->timeout = $time;

        // \comodojo\Dispatcher\debug("Timeout: ".$time,"DEBUG","httprequest");

        return $this;

    }

    /**
     * Set http version (1.0/1.1)
     *
     * @param   string  $ver    1.0 or 1.1
     *
     * @return  Object  $this
     */
    public final function setHttpVersion($ver) {

        if ( !in_array($ver, array("1.0","1.1")) ) {
            
            $this->httpVersion = "NONE";
        
        }
        else {

            $this->httpVersion = $ver;

        }
        
        // \comodojo\Dispatcher\debug("Using http version: ".$version,"DEBUG","http");

        return $this;

    }

    /**
     * Set http content type
     *
     * @param   string  $type
     *
     * @return  Object  $this
     */
    public final function setContentType($type) {

        if ( empty($type) ) throw new HttpException("Conte Type cannot be null");

        $this->contentType = $type;

        // \comodojo\Dispatcher\debug("Using content type: ".$type,"DEBUG","httprequest");

        return $this;

    }

    /**
     * Set TCP port to connect to
     *
     * @param   integer $port   TCP port (default 80)
     *
     * @return  Object  $this
     */
    public final function setPort($port) {

        $this->port = filter_var($port, FILTER_VALIDATE_INT, array(
            "options" => array(
                "min_range" => 1,
                "max_range" => 65535,
                "default" => 80 )
            )
        );
        
        // \comodojo\Dispatcher\debug("Using port: ".$port,"DEBUG","httprequest");

        return $this;

    }

    /**
     * Set HTTP method to use
     *
     * @param   string  $mehod  HTTP METHOD
     *
     * @return  Object  $this
     */
    public final function setHttpMethod($method) {

        $method = strtoupper($method);

        if ( !in_array($method, $this->supported_http_methods) ) {

            // \comodojo\Dispatcher\debug($method." is not currently supported", "ERROR", "httprequest");

            throw new HttpException("Unsupported HTTP method");

        }

        $this->method = $method;

        // \comodojo\Dispatcher\debug("Using method: ".$method,"DEBUG","httprequest");

        return $this;

    }

    /**
     * Set HTTP method to use
     *
     * @param   string  $address    Proxy URL or IP address
     * @param   string  $user       (optional) User name for proy auth
     * @param   string  $pass       (optional) User password for proxy auth
     *
     * @return  Object  $this
     */
    public final function setProxy($address, $user=null, $pass=null) {

        $proxy = filter_var($address, FILTER_VALIDATE_URL);

        if ( $proxy == false ) throw new HttpException("Invalid proxy address or URL");
        
        $this->proxy = $proxy;

        if ( !is_null($user) AND !is_null($pass) ) {

            $this->proxy_auth = $user.':'.$pass;

            // \comodojo\Dispatcher\debug("Using proxy: ".$user."@".$address,"DEBUG","httprequest");

        }
        else if ( !is_null($user) ) {

            $this->proxy_auth = $user;

        }
        else $this->proxy_auth = NULL; 

        return $this;

    }

    /**
     * Set header component
     *
     * @param   string  $header     Header name
     * @param   string  $value      Header content (optional)
     *
     * @return  ObjectRequest   $this
     */
    public final function setHeader($header, $value=NULL) {

        $this->headers[$header] = $value;

        return $this;

    }


    public final function getReceivedHeaders() {

        return $this->receivedHeaders;

    }

    /**
     * Init transport and send data to the remote host.
     * 
     * @return  string  Received Data
     */
    public function send($data = NULL) {
        
        try {
        
            $init = $this->curl ? $this->init_curl($data) : $this->init_stream($data);

            $received = $this->curl ? $this->send_curl() : $this->send_stream();

        } catch (HttpException $ioe) {
            
            throw $ioe;

        }

        return $received;

    }

    /**
     * Init transport and get remote content
     * 
     * @return  string  Received Data
     */
    public function get() {
        
        try {
        
            $init = $this->curl ? $this->init_curl(NULL) : $this->init_stream(NULL);

            $received = $this->curl ? $this->send_curl() : $this->send_stream();

        } catch (HttpException $ioe) {
            
            throw $ioe;

        }

        return $received;

    }
    
    /**
     * Reset the data channel for new request
     * 
     */
    public final function reset() {

        $this->address = NULL;

        $this->port = 80;

        $this->method = 'GET';

        $this->timeout = 30;

        $this->httpVersion = "1.0";

        $this->authenticationMethod = false;

        $this->user = NULL;

        $this->pass = NULL;

        $this->userAgent = 'Comodojo-Dispatcher';

        $this->contentType = 'application/x-www-form-urlencoded';

        $this->headers = array(
            'Accept'            =>  'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language'   =>  'en-us,en;q=0.5',
            'Accept-Encoding'   =>  'deflate',
            'Accept-Charset'    =>  'UTF-8;q=0.7,*;q=0.7'
        );

        $this->proxy = NULL;

        $this->proxy_auth = NULL;

        $this->receivedHeaders = array();

        $this->buffer = 4096;

    }
    
    private function getHeaders() {

        return $this->headers;

    }

    private function parseHeader($header, $value) {

        if ( is_null($value) ) return $header;

        else return $header.': '.$value;

    }

    private function init_curl($data) {

        $this->ch = curl_init();
            
        if ( $this->ch === false ) throw new HttpException("Cannot init data channel");

        switch ($this->httpVersion) {

            case '1.0':
                curl_setopt($this->ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_0);
                break;

            case '1.1':
                curl_setopt($this->ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
                break;

            default:
                curl_setopt($this->ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_NONE);
                break;

        }

        switch ($this->authenticationMethod) {

            case 'BASIC':
                curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($this->ch, CURLOPT_USERPWD, $this->userName.":".$this->userPass); 
                break;

            case 'NTLM':
                curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
                curl_setopt($this->ch, CURLOPT_USERPWD, $this->userName.":".$this->userPass); 
                break;

        }

        if ( !is_null($this->proxy) ) {

            curl_setopt($this->ch, CURLOPT_PROXY, $this->proxy);

            if ( !is_null($this->proxy_auth) ) curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, $this->proxy_auth);

        }

        switch ($this->method) {
            
            case 'GET':
                curl_setopt($this->ch, CURLOPT_URL, $this->address);
                break;
            
            case 'PUT':
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "PUT"); 
                if ( !empty($data) ) {
                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    array_push($this->header, "Content-Type: ".$this->contentType);
                }
                curl_setopt($this->ch, CURLOPT_URL, $this->address);
                break;
            
            case 'POST':
                curl_setopt($this->ch, CURLOPT_POST, true);
                if ( !empty($data) ) {
                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
                    array_push($this->header, "Content-Type: ".$this->contentType);
                }
                curl_setopt($this->ch, CURLOPT_URL, $this->address);
                break;
            
            case 'DELETE':
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($this->ch, CURLOPT_URL, $this->address);
                break;

        }

        if ( sizeof($this->headers) != 0 ) {

            $headers = array();

            foreach ($this->getHeaders() as $header => $value) {
                
                if ( is_null($value) ) array_push($headers, $header);
            
                else array_push($headers, $header.': '.$value);

            }

        }
        else $headers = array();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER,  1);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION,  1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT,         $this->timeout);
        curl_setopt($this->ch, CURLOPT_PORT,            $this->port);
        curl_setopt($this->ch, CURLOPT_USERAGENT,       $this->userAgent);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER,      $headers);
        curl_setopt($this->ch, CURLOPT_HEADER,          1);

    }

    private function init_stream($data) {

        if ($this->authenticationMethod == 'NTLM') throw new HttpException("NTLM auth with streams is not supported");

        $stream_options = array(
            'http'  =>  array(
                'method'            =>  $this->method,
                'protocol_version'  =>  $this->httpVersion == "NONE" ? "1.0" : $this->httpVersion,
                'user_agent'        =>  $this->userAgent,
                'timeout'           =>  $this->timeout,
                'header'            =>  array(
                    'Connection: close'
                )
            )
        );

        if ( !is_null($this->proxy) ) {

            $stream_options['http']['proxy'] = $this->proxy;

            if ( !is_null($this->proxy_auth) ) array_push($stream_options['http']['header'], 'Proxy-Authorization: Basic '.base64_encode($this->proxy_auth));

        }

        if ($this->authenticationMethod == "BASIC") array_push($stream_options['http']['header'], 'Authorization: Basic  '.base64_encode($this->userName.":".$this->userPass));
        
        foreach ($this->getHeaders() as $header => $value) {

            if ( is_null($value) ) array_push($stream_options['http']['header'], $header);
            
            else array_push($stream_options['http']['header'], $header.': '.$value);

        }

        if ( !empty($data) ) {

            //$data = urlencode($data);

            array_push($stream_options['http']['header'], 'Content-Type: '.$this->contentType);
            array_push($stream_options['http']['header'], 'Content-Length: '.strlen($data));

            $stream_options['http']['content'] = $data;
            
        }

        $this->ch = stream_context_create($stream_options);

        if ( !$this->ch ) {

            // \comodojo\Dispatcher\debug("Cannot init data channel","ERROR","httprequest");

            throw new HttpException("Cannot init data channel");

        }

    }

    private function send_curl() {

        $request = curl_exec($this->ch);
        
        if ( $request === false ) {
                
            // \comodojo\Dispatcher\debug("Curl request error: ".curl_errno($this->ch)." - ".curl_error($this->ch),"ERROR","httprequest");

            throw new HttpException(curl_error($this->ch), curl_errno($this->ch));

        }

        $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);

        $headers = substr($request, 0, $header_size);

        $body = substr($request, $header_size);

        $this->receivedHeaders = $this->tokenize_headers($headers);

        return $body;

    }

    private function send_stream() {

        if ( $this->port == 80 ) {

            $host = $this->address;

        }
        else {

            $host = substr($this->address, -1) == "/" ? substr($this->address, 0, -1).':'.$this->port : $this->address.':'.$this->port;

        }

        $received = file_get_contents($host, false, $this->ch);
        
        if ( $received === false ) {
                
            // \comodojo\Dispatcher\debug("Stream request error","ERROR","httprequest");
            
            throw new HttpException("Cannot read stream socket");

        }
        
        $this->receivedHeaders = $this->tokenize_headers(implode("\r\n", $http_response_header));
        
        return $received;

    }

    private function tokenize_headers($headers) {

        $return = array();

        foreach (explode("\r\n", $headers) as $header) {
            
            if ( empty($header) ) continue;

            $header_components = explode(":", $header);

            if ( !isset($header_components[1]) OR @empty($header_components[1]) ) array_push($return, $header_components[0]);

            else $return[$header_components[0]] = $header_components[1];

        }

        return $return;

    }

    /**
     * Close transport layer
     */
    private function close_transport() {

        if ($this->curl) {

            curl_close($this->ch);

        }

    }

}