<?php namespace Comodojo\Httprequest;

use \Comodojo\Exception\HttpException;
use \League\Url\Url;

/**
 * HTTP requests library   
 * 
 * @package     Comodojo Spare Parts
 * @author      Marco Giovinazzi <marco.giovinazzi@comodojo.org>
 * @license     MIT
 *
 * LICENSE:
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
 
class Httprequest {

    /**
     * Remote host address (complete url)
     *
     * @var string
     */
    private $address = null;
    
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
    private $authenticationMethod = null;
    
    /**
     * Remote host auth username
     *
     * @var string
     */
    private $user = null;
    
    /**
     * Remote host auth password
     *
     * @var string
     */
    private $pass = null;

    /**
     * Request user agent
     * 
     * @var string
     */
    private $userAgent = 'Comodojo-Httprequest';
    
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
        'Accept-Encoding'   =>  'gzip,deflate',
        'Accept-Charset'    =>  'UTF-8;q=0.7,*;q=0.7'
    );

    /**
     * Should we use a proxy?
     *
     * @var string
     */
    private $proxy = null;

    private $proxy_auth = null;

    /**
     * Allowed HTTP methods
     *
     * @var array
     */
    private $supported_auth_methods = array("BASIC", "DIGEST", "SPNEGO", "NTLM");

    /**
     * Allowed HTTP authentication
     *
     * @var array
     */
    private $supported_http_methods = array("GET", "POST", "PUT", "DELETE");

    /**
     * Are we using curl?
     */
    private $curl = true;
    
    /**
     * Received headers
     *
     * @var array
     */
    private $receivedHeaders = array();

    /**
     * Received http status code
     *
     * @var int
     */
    private $receivedHttpStatus = null;

    /**
     * Transfer channel
     *
     * @var resource
     */
    private $ch = false;

    private $stream_get_data = null;
    
    /**
     * Class constructor
     *
     * @param   string  $address Remote host address
     * @param   bool    $curl    Use curl (true) or stream (false)
     * 
     * @throws \Comodojo\Exception\HttpException
     */
    final public function __construct($address = false, $curl = true) {

        if ( !empty($address) ) {
            
            try {
                
                $this->setHost($address);
                
            } catch (HttpException $he) {
                
                throw $he;
                
            }
            
        }
        
        $this->setCurl($curl);
        
    }

    /**
     * Class destructor
     *
     */
    final public function __destruct() {

        if ( $this->ch !== false ) $this->close_transport();

    }

    /**
     * Set remote host address
     *
     * @param   string  $address Remote host address
     *
     * @return  \Comodojo\Httprequest\Httprequest
     * 
     * @throws  \Comodojo\Exception\HttpException
     */
    final public function setHost($address) {
        
        $url = filter_var($address, FILTER_VALIDATE_URL);

        if ( $url === false ) throw new HttpException("Invalid remote address");
        
        $this->address = $address;
        
        return $this;
        
    }
    
    /**
     * Force lib to use curl (default if available) or stream
     *
     * @param   bool    $mode    Use curl (true) or stream (false)
     *
     * @return  \Comodojo\Httprequest\Httprequest
     */
    final public function setCurl($mode = true) {
        
        $curl = filter_var($mode, FILTER_VALIDATE_BOOLEAN);
        
        if ( !function_exists("curl_init") OR !$curl ) $this->curl = false;
        else $this->curl = true;
        
        return $this;
        
    }

    /**
     * Set http authentication
     *
     * @param   string  $method Auth method (BASIC or NTLM)
     * @param   string  $user   Username to use
     * @param   string  $pass   User password
     *
     * @return  \Comodojo\Httprequest\Httprequest
     * 
     * @throws \Comodojo\Exception\HttpException
     */
    final public function setAuth($method, $user, $pass = null) {

        $method = strtoupper($method);

        if ( !in_array($method, $this->supported_auth_methods) ) {

            throw new HttpException("Unsupported authentication method");

        }

        $this->authenticationMethod = $method;

        if ( empty($user) ) {

            throw new HttpException("User name cannot be null");

        }

        $this->user = $user;
        $this->pass = $pass;
                
        return $this;

    }

    /**
     * Set user agent for request
     *
     * @param   string  $ua     User Agent
     *
     * @return  \Comodojo\Httprequest\Httprequest
     * 
     * @throws \Comodojo\Exception\HttpException
     */
    final public function setUserAgent($ua) {

        if ( empty($ua) ) throw new HttpException("Useragent cannot be null");

        $this->userAgent = $ua;

        return $this;

    }

    /**
     * Set connection timeout
     *
     * @param   int $sec    Timeout to wait for (in second)
     *
     * @return  \Comodojo\Httprequest\Httprequest
     */
    final public function setTimeout($sec) {

        $time = filter_var($sec, FILTER_VALIDATE_INT);

        $this->timeout = $time;

        return $this;

    }

    /**
     * Set http version (1.0/1.1)
     *
     * @param   string  $ver    1.0 or 1.1
     *
     * @return  \Comodojo\Httprequest\Httprequest
     */
    final public function setHttpVersion($ver) {

        if ( !in_array($ver, array("1.0", "1.1")) ) {
            
            $this->httpVersion = "NONE";
        
        } else {

            $this->httpVersion = $ver;

        }
        
        return $this;

    }

    /**
     * Set http content type
     *
     * @param   string  $type
     *
     * @return  \Comodojo\Httprequest\Httprequest
     * 
     * @throws  \Comodojo\Exception\HttpException
     */
    final public function setContentType($type) {

        if ( empty($type) ) throw new HttpException("Conte Type cannot be null");

        $this->contentType = $type;

        return $this;

    }

    /**
     * Set TCP port to connect to
     *
     * @param   integer $port   TCP port (default 80)
     *
     * @return  \Comodojo\Httprequest\Httprequest
     */
    final public function setPort($port) {

        $this->port = filter_var($port, FILTER_VALIDATE_INT, array(
            "options" => array(
                "min_range" => 1,
                "max_range" => 65535,
                "default" => 80 )
            )
        );
        
        return $this;

    }

    /**
     * Set HTTP method to use
     *
     * @param   string  $method  HTTP METHOD
     *
     * @return  \Comodojo\Httprequest\Httprequest
     * 
     * @throws \Comodojo\Exception\HttpException
     */
    final public function setHttpMethod($method) {

        $method = strtoupper($method);

        if ( !in_array($method, $this->supported_http_methods) ) {

            throw new HttpException("Unsupported HTTP method");

        }

        $this->method = $method;

        return $this;

    }

    /**
     * Set HTTP method to use
     *
     * @param   string  $address    Proxy URL or IP address
     * @param   string  $user       (optional) User name for proy auth
     * @param   string  $pass       (optional) User password for proxy auth
     *
     * @return  \Comodojo\Httprequest\Httprequest
     * 
     * @throws \Comodojo\Exception\HttpException
     */
    final public function setProxy($address, $user = null, $pass = null) {

        $proxy = filter_var($address, FILTER_VALIDATE_URL);

        if ( $proxy == false ) throw new HttpException("Invalid proxy address or URL");
        
        $this->proxy = $proxy;

        if ( !is_null($user) AND !is_null($pass) ) {

            $this->proxy_auth = $user.':'.$pass;

        } else if ( !is_null($user) ) {

            $this->proxy_auth = $user;

        } else $this->proxy_auth = NULL; 

        return $this;

    }

    /**
     * Set header component
     *
     * @param   string  $header     Header name
     * @param   string  $value      Header content (optional)
     *
     * @return  \Comodojo\Httprequest\Httprequest
     */
    final public function setHeader($header, $value = NULL) {

        $this->headers[$header] = $value;

        return $this;

    }

    /**
     * Unset header component
     *
     * @param   string  $header     Header name
     *
     * @return  \Comodojo\Httprequest\Httprequest
     */
    final public function unsetHeader($header) {

        if ( array_key_exists($header, $this->headers) ) unset($this->headers[$header]);

        return $this;

    }

    /**
     * Get the whole headers array
     *
     * @return  array
     */
    final public function getHeaders() {

        return $this->headers;

    }

    /**
     * Get received headers
     *
     * @return  array
     */
    final public function getReceivedHeaders() {

        return $this->receivedHeaders;

    }

    /**
     * Get received headers
     *
     * @return  integer
     */
    final public function getHttpStatusCode() {

        return $this->receivedHttpStatus;

    }

    /**
     * Get transport channel (curl channel or stream context)
     *
     * @return  mixed
     */
    final public function getChannel() {

        return $this->ch;

    }

    /**
     * Init transport and send data to the remote host.
     * 
     * @return  string
     * 
     * @throws \Comodojo\Exception\HttpException
     */
    public function send($data = null) {
        
        try {

            if ( $this->curl ) {

                $this->init_curl($data);

                $received = $this->send_curl();

            } else {

                $this->init_stream($data);

                $received = $this->send_stream();

            }
        

        } catch (HttpException $ioe) {
            
            throw $ioe;

        }

        return $received;

    }

    /**
     * Init transport and get remote content
     * 
     * @return  string
     * 
     * @throws \Comodojo\Exception\HttpException
     */
    public function get() {
        
        try {

            if ( $this->curl ) {

                $this->init_curl(null);

                $received = $this->send_curl();

            } else {

                $this->init_stream(null);

                $received = $this->send_stream();

            }

        } catch (HttpException $ioe) {
            
            throw $ioe;

        }

        return $received;

    }
    
    /**
     * Reset the data channel for new request
     * 
     */
    final public function reset() {

        $this->address = null;

        $this->port = 80;

        $this->method = 'GET';

        $this->timeout = 30;

        $this->httpVersion = "1.0";

        $this->authenticationMethod = null;

        $this->user = null;

        $this->pass = null;

        $this->userAgent = 'Comodojo-Httprequest';

        $this->contentType = 'application/x-www-form-urlencoded';

        $this->headers = array(
            'Accept'            =>  'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language'   =>  'en-us,en;q=0.5',
            'Accept-Encoding'   =>  'deflate',
            'Accept-Charset'    =>  'UTF-8;q=0.7,*;q=0.7'
        );

        $this->proxy = null;

        $this->proxy_auth = null;

        $this->receivedHeaders = array();

        $this->receivedHttpStatus = null;

        $this->stream_get_data = null;
        
        if ( $this->ch !== false ) $this->close_transport();

    }
    
    /**
     * Parse a single header
     *
     * @param   string  $header
     * @param   string  $value
     * 
     * @return  string
     */
    private function parseHeader($header, $value) {

        if ( is_null($value) ) return $header;

        else return $header.': '.$value;

    }

    /**
     * Init the CURL channel
     *
     * @param   string  $data
     * 
     * @throws  \Comodojo\Exception\HttpException
     */
    private function init_curl($data) {

        $this->ch = curl_init();
            
        if ( $this->ch === false ) throw new HttpException("Cannot init data channel");

        switch ( $this->httpVersion ) {

            case '1.0':
                curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                break;

            case '1.1':
                curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                break;

            default:
                curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
                break;

        }

        switch ( $this->authenticationMethod ) {

            case 'BASIC':
                curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($this->ch, CURLOPT_USERPWD, $this->user.":".$this->pass); 
                break;

            case 'DIGEST':
                curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                curl_setopt($this->ch, CURLOPT_USERPWD, $this->user.":".$this->pass); 
                break;

            case 'SPNEGO':
                curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_GSSNEGOTIATE);
                curl_setopt($this->ch, CURLOPT_USERPWD, $this->user.":".$this->pass); 
                break;

            case 'NTLM':
                curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
                curl_setopt($this->ch, CURLOPT_USERPWD, $this->user.":".$this->pass); 
                break;

        }

        if ( !is_null($this->proxy) ) {

            curl_setopt($this->ch, CURLOPT_PROXY, $this->proxy);

            if ( !is_null($this->proxy_auth) ) curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, $this->proxy_auth);

        }

        switch ( $this->method ) {
            
            case 'GET':

                if ( empty($data) ) curl_setopt($this->ch, CURLOPT_URL, $this->address);

                else curl_setopt($this->ch, CURLOPT_URL, $this->address."?".((is_array($data) OR is_object($data)) ? http_build_query($data) : $data));

                break;
            
            case 'PUT':

                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "PUT"); 

                if ( !empty($data) ) {

                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, (is_array($data) OR is_object($data)) ? http_build_query($data) : $data);

                    $this->setHeader('Content-Type', $this->contentType);

                }

                curl_setopt($this->ch, CURLOPT_URL, $this->address);

                break;
            
            case 'POST':

                curl_setopt($this->ch, CURLOPT_POST, true);

                if ( !empty($data) ) {

                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, (is_array($data) OR is_object($data)) ? http_build_query($data) : $data);

                    $this->setHeader('Content-Type', $this->contentType);

                }

                curl_setopt($this->ch, CURLOPT_URL, $this->address);

                break;
            
            case 'DELETE':

                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "DELETE");

                if ( !empty($data) ) {

                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, (is_array($data) OR is_object($data)) ? http_build_query($data) : $data);

                    $this->setHeader('Content-Type', $this->contentType);

                }
                
                curl_setopt($this->ch, CURLOPT_URL, $this->address);

                break;

        }

        if ( sizeof($this->headers) != 0 ) {

            $headers = array();

            foreach ( $this->getHeaders() as $header => $value ) {
                
                if ( is_null($value) ) array_push($headers, $header);
            
                else array_push($headers, $header.': '.$value);

            }

        } else $headers = array();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->ch, CURLOPT_PORT, $this->port);
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        curl_setopt($this->ch, CURLOPT_ENCODING, "");

        //curl_setopt($this->ch, CURLOPT_VERBOSE, true);

    }

    /**
     * Init the STREAM channel
     *
     * @param   string  $data
     * 
     * @throws  \Comodojo\Exception\HttpException
     */
    private function init_stream($data) {

        if ( in_array($this->authenticationMethod, array("DIGEST", "SPNEGO", "NTLM")) ) throw new HttpException("Selected auth method not available in stream mode");

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

        if ( $this->authenticationMethod == "BASIC" ) array_push($stream_options['http']['header'], 'Authorization: Basic  '.base64_encode($this->user.":".$this->pass));
        
        foreach ( $this->getHeaders() as $header => $value ) {

            if ( is_null($value) ) array_push($stream_options['http']['header'], $header);
            
            else array_push($stream_options['http']['header'], $header.': '.$value);

        }

        if ( !empty($data) ) {

            $data_query = (is_array($data) OR is_object($data)) ? http_build_query($data) : $data;

            if ( $this->method == "GET" ) {

                $this->stream_get_data = $data_query;

            } else {

                array_push($stream_options['http']['header'], 'Content-Type: '.$this->contentType);

                array_push($stream_options['http']['header'], 'Content-Length: '.strlen($data_query));

                $stream_options['http']['content'] = $data_query;

            }
            
        }

        $this->ch = stream_context_create($stream_options);

        if ( !$this->ch ) {

            throw new HttpException("Cannot init data channel");

        }

    }

    /**
     * Send data via CURL
     *
     * @return  string
     * 
     * @throws  \Comodojo\Exception\HttpException
     */
    private function send_curl() {

        $request = curl_exec($this->ch);
        
        if ( $request === false ) {
                
            throw new HttpException(curl_error($this->ch), curl_errno($this->ch));

        }

        $this->receivedHttpStatus = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);

        $headers = substr($request, 0, $header_size);

        $body = substr($request, $header_size);

        $this->receivedHeaders = self::tokenizeHeaders($headers);

        return $body;

    }

    /**
     * Send data via STREAM
     *
     * @return  string
     * 
     * @throws  \Comodojo\Exception\HttpException
     */
    private function send_stream() {

        $url = Url::createFromUrl($this->address);

        if ( $this->port != 80 ) $url->setPort($this->port);

        if ( !is_null($this->stream_get_data) ) $url->setQuery($this->stream_get_data);

        $host = $url;

        set_error_handler( 

            function($severity, $message, $file, $line) {

                throw new HttpException($message);

            }

        );

        try {
        
            $received = file_get_contents($host, false, $this->ch);

        } catch (HttpException $he) {

            throw $he;
            
        }

        restore_error_handler();
        
        if ( $received === false ) {
                            
            throw new HttpException("Cannot read stream socket");

        }
        
        $this->receivedHeaders = self::tokenizeHeaders(implode("\r\n", $http_response_header));

        $content_encoding = array_key_exists('Content-Encoding', $this->receivedHeaders);

        list($version, $this->receivedHttpStatus, $msg) = explode(' ', $this->receivedHeaders[0], 3);

        if ( $content_encoding === true AND strpos($this->receivedHeaders['Content-Encoding'], 'gzip') !== false ) {

            return gzinflate(substr($received, 10, -8));

        } else return $received;

    }

    /**
     * Tokenize received headers
     *
     * @param   string  $headers
     *
     * @return  array
     */
    private static function tokenizeHeaders($headers) {

        $return = array();

        $headers_array = explode("\r\n", $headers);

        foreach ( $headers_array as $header ) {

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

        if ( $this->curl ) {

            curl_close($this->ch);

        }

    }

}