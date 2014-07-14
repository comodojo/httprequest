<?php namespace comodojo\DispatcherLibrary;

/**
 * HTTP requests library for comodojo/dispatcher.framework	
 * 
 * @package		Comodojo dispatcher (Spare Parts)
 * @author		comodojo <info@comodojo.org>
 * @license		GPL-3.0+
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

use \comodojo\Exception\IOException;
use \comodojo\Dispatcher\debug;
 
class httprequest {

/********************** PRIVATE VARS *********************/
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
	 * Array of headers to send
	 *
	 * @var array
	 */
	private $headers = Array(
		'Accept'			=>	'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		'Accept-Language'	=>	'en-us,en;q=0.5',
		'Accept-Encoding'	=>	'deflate',
		'Accept-Charset'	=>	'UTF-8;q=0.7,*;q=0.7'
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
	private $supported_auth_methods = Array("BASIC","NTLM");

	/**
	 * Allowed HTTP authentication
	 *
	 * @var array
	 */
	private $supported_http_methods = Array("GET","POST","PUT","DELETE");


	/**
	 * Are we using curl?
	 */
	private $curl = true;
	
	/**
	 * Remote host 
	 * @var string
	 */
	private $remoteHost = NULL;

	/**
	 * Remote host path
	 * @var string
	 */
	private $remotePath = NULL;
	
	/**
	 * Remote query string
	 * @var string
	 */
	private $remoteQuery = NULL;

	private $receivedHeaders = NULL;

	/**
	 * Transfer channel
	 * @var resource
	 */
	private $ch = false;

	private $buffer = 4096;
/********************** PRIVATE VARS *********************/
	
/********************* PUBLIC METHODS ********************/
	
	public final function __construct($address, $curl=true) {

		if ( empty($address) ) throw new IOException("Invalid remote host");
		
		$curl = filter_var($curl, FILTER_VALIDATE_BOOLEAN);

		if ( !function_exists("curl_init") OR !$curl ) {
			
			$this->curl = false;
			
			$this->address = $address;
			
			$url = parse_url($address);
			
			$this->remoteHost = isset($url['host']) ? $url['host'] : '';
			$this->remotePath = isset($url['path']) ? $url['path'] : '';
			$this->remoteQuery = isset($url['query']) ? $url['query'] : '';

			debug("httprequest will use fsock (compatibility mode)","DEBUG","httprequest");

		}
		else {

			$this->curl = true;

			$this->address = $address;

			debug("httprequest will use curl","DEBUG","httprequest");

		}

	}

	public final function __destruct() {

		if ( $this->ch !== false ) $this->close_transport;

	}

	/**
	 * Set http authentication
	 *
	 * @param	string	$method	Auth method (BASIC or NTLM)
	 * @param	string	$user	Username to use
	 * @param	string	$pass	User password
	 *
	 * @return 	Object 	$this
	 */
	public final function setAuth($method, $user, $pass=NULL) {

		$method = strtoupper($method);

		if ( !in_array($method, $this->supported_auth_methods) ) {

			debug($method." is not a valid auth method", "ERROR", "httprequest");

			throw new IOException("Unsupported authentication method");

		}

		if ( empty($user) ) {

			throw new IOException("User name cannot be null");

		}

		$this->user = $user;
		$this->pass = $pass;
		
		//debug("Using auth method: ".$method,"DEBUG","httprequest");
		
		return $this;

	}

	/**
	 * Set user agent for request
	 *
	 * @param	string	$ua		User Agent
	 *
	 * @return 	Object 	$this
	 */
	public final function setUserAgent($ua) {

		if ( empty($ua) ) throw new IOException("Useragent cannot be null");

		$this->userAgent = $ua;

		//debug("Using user agent: ".$ua, "DEBUG", "httprequest");

		return $this;

	}

	/**
	 * Set connection timeout
	 *
	 * @param	int	$sec	Timeout to wait for (in second)
	 *
	 * @return 	Object 	$this
	 */
	public final function setTimeout($sec) {

		$time = filter_var($sec, FILTER_VALIDATE_INT);

		$this->timeout = $time;

		//debug("Timeout: ".$time,"DEBUG","httprequest");

		return $this;

	}

	/**
	 * Set http version (1.0/1.1)
	 *
	 * @param	string	$ver	1.0 or 1.1
	 *
	 * @return 	Object 	$this
	 */
	public final function setHttpVersion($ver) {

		if ( !in_array($ver, Array("1.0","1.1")) ) {
			
			$this->httpVersion = "NONE";
		
		}
		else {

			$this->httpVersion = $ver;

		}
		
		//debug("Using http version: ".$version,"DEBUG","http");

		return $this;

	}

	/**
	 * Set http content type
	 *
	 * @param	string	$type
	 *
	 * @return 	Object 	$this
	 */
	public final function setContentType($type) {

		if ( empty($type) ) throw new IOException("Conte Type cannot be null");

		$this->contentType = $type;

		//debug("Using content type: ".$type,"DEBUG","httprequest");

		return $this;

	}

	/**
	 * Set TCP port to connect to
	 *
	 * @param	integer	$port	TCP port (default 80)
	 *
	 * @return 	Object 	$this
	 */
	public final function setPort($port) {

		$this->port = filter_var($port, FILTER_VALIDATE_INT, array(
			"options" => array(
				"min_range" => 1,
				"max_range" => 65535,
				"default" => 80 )
			)
		);
		
		//debug("Using port: ".$port,"DEBUG","httprequest");

		return $this;

	}

	/**
	 * Set FSOCK buffer size
	 *
	 * @param	integer	$size
	 *
	 * @return 	Object 	$this
	 */
	public final function setBuffer($size) {

		$this->buffer = filter_var($size, FILTER_VALIDATE_INT, array(
			"options" => array(
				"min_range" => 128,
				"default" => 4096
				)
			)
		);
		
		return $this;

	}

	/**
	 * Set HTTP method to use
	 *
	 * @param	string	$mehod	HTTP METHOD
	 *
	 * @return 	Object 	$this
	 */
	public final function setHttpMethod($method) {

		$method = strtoupper($method);

		if ( !in_array($method, $this->supported_http_methods) ) {

			debug($method." is not currently supported", "ERROR", "httprequest");

			throw new IOException("Unsupported HTTP method");

		}

		$this->method = $method;

		//debug("Using method: ".$method,"DEBUG","httprequest");

		return $this;

	}

	/**
	 * Set HTTP method to use
	 *
	 * @param	string	$address	Proxy URL or IP address
	 * @param	string	$user		(optional) User name for proy auth
	 * @param	string	$pass		(optional) User password for proxy auth
	 *
	 * @return 	Object 	$this
	 */
	public final function setProxy($address, $user=null, $pass=null) {

		$proxy = filter_var($address, FILTER_VALIDATE_URL);

		if ( $proxy == false ) throw new IOException("Invalid proxy address or URL");
		
		$this->proxy = $proxy;

		if ( !is_null($user) AND !is_null($pass) ) {

			$this->proxy_auth = $user.':'.$pass;

			//debug("Using proxy: ".$user."@".$address,"DEBUG","httprequest");

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
	 * @param 	string 	$header 	Header name
	 * @param 	string 	$value 		Header content (optional)
	 *
	 * @return 	ObjectRequest 	$this
	 */
	public final function setHeader($header, $value=NULL) {

		$this->headers[$header] = $value;

		return $this;

	}


	public final function getReceivedHeaderss() {

		return $this->receivedHeaders;

	}

	/**
	 * Init transport and send data to the remote host.
	 * 
	 * @return	string	Received Data
	 */
	public function send($data = null) {
		
		debug("------ Ready to send data ------ ","DEBUG","httprequest");
		debug("-> Remote address: ".$this->address,"DEBUG","httprequest");
		debug("------- Start Data Dump ------ ","DEBUG","httprequest");
		debug($data,"DEBUG","httprequest");
		debug("-------- End Data Dump ------- ","DEBUG","httprequest");

		try {
		
			$init = $this->curl ? $this->init_curl($data) : $this->init_fsock($data);

			$received = $this->curl ? $this->send_curl() : $this->send_fsock($init);

		} catch (IOException $ioe) {
			
			throw $ioe;

		}

		$this->close_transport();
		
		debug("-------- Received data --------- ","DEBUG","httprequest");
		debug("------- Start Data Dump ------ ","DEBUG","httprequest");
		debug($received,"DEBUG","httprequest");
		debug("-------- End Data Dump ------- ","DEBUG","httprequest");

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

		$this->headers = Array(
			'Accept'			=>	'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language'	=>	'en-us,en;q=0.5',
			'Accept-Encoding'	=>	'deflate',
			'Accept-Charset'	=>	'UTF-8;q=0.7,*;q=0.7'
		);

		$this->proxy = NULL;

		$this->proxy_auth = NULL;

		$this->supported_auth_methods = Array("BASIC","NTLM");

		$this->supported_http_methods = Array("GET","POST","PUT","DELETE");

		$this->curl = true;

		$this->remoteHost = NULL;

		$this->remotePath = NULL;

		$this->remoteQuery = NULL;

		$this->receivedHeaders = NULL;

		$this->buffer = 4096;

	}
/********************* PUBLIC METHODS ********************/

/********************* PRIVATE METHODS *******************/
	
	private function getHeaders() {

		return $this->headers;

	}

	private function parseHeader($header, $value) {

		if ( is_null($value) ) return $header;

		else return $header.': '.$value;

	}

	private function init_curl($data) {

		$this->ch = curl_init();
			
		if ( $this->ch === false ) throw new IOException("Cannot init data channel");

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
				curl_setopt($this->ch, CURLOPT_URL, $this->address.'?'.http_build_query($data));
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

			$headers = Array();

			foreach ($this->getHeaders as $header => $value) {
				
				array_push($headers, $this->parseHeader($header, $value));

			}

		}
		else $headers = Array();

		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER,	1);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION,	1);
		curl_setopt($this->ch, CURLOPT_TIMEOUT,			$this->timeout);
		curl_setopt($this->ch, CURLOPT_PORT,			$this->port);
		curl_setopt($this->ch, CURLOPT_USERAGENT,		$this->userAgent);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER,		$headers);
		curl_setopt($this->ch, CURLOPT_HEADER,			1);

		return NULL;

	}

	private function init_fsock($data) {

		if ($this->authenticationMethod == 'NTLM') throw new IOException("NTLM auth with FSOCKS is not supported");

		debug("Using httprequest in compatible mode; some features will not be available","WARNING","httprequest");

		$httpVersion = $this->httpVersion == "NONE" ? "1.0" : $this->httpVersion;

		$crlf = "\r\n";

		$header  = $this->method.' '.$this->remotePath.$this->remoteQuery.' HTTP/'.$httpVersion.$crlf;
		$header .= "User-Agent: ".$this->userAgent.$crlf;
		$header .= "Host: ".$this->remoteHost.$crlf;

		if ($this->authenticationMethod == "BASIC") $header .= "Authorization: Basic ".base64_encode($this->userName.":".$this->userPass).$crlf;
		
		if ($this->proxy_auth !== null) $header .= "Proxy-Authorization: Basic ".base64_encode($this->proxy_auth).$crlf;

		foreach ($this->getHeaders as $header => $value) {

			$header .= $this->parseHeader($header, $value).$crlf;

		}

		if ( !empty($data) ) {

			//$data = urlencode($data);

			$header .= "Content-Type: ".$this->contentType.$crlf;
			$header .= "Content-Length: ".strlen($data).$crlf.$crlf;

			$return = $header.$data;

		}

		else $return = $header.$crlf;

		if ( is_null($this->proxy) ) $this->ch = fsockopen($this->remoteHost, $this->port, $errno, $errstr, $this->timeout);
		
		else {

			$proxy = parse_url($this->proxy);
			$proxy_host = $url['host'];
			$proxy_port = isset($url['port']) ? $url['port'] : '80';

			$this->ch = fsockopen($proxy_host, $proxy_port, $errno, $errstr, $this->timeout);

		}

		if ( !$this->ch ) {

			debug("Cannot init data channel: (".$errno.") ".$errstr,"ERROR","httprequest");

			throw new IOException("Cannot init data channel");

		}

		stream_set_timeout($this->ch, $this->timeout); 

		return $return;

	}

	private function send_curl() {

		$body = curl_exec($this->ch);
		
		if ( $body === false ) {
				
			debug("Curl request error: ".curl_errno($this->ch)." - ".curl_error($this->ch),"ERROR","httprequest");

			throw new IOException(curl_error($this->ch), curl_errno($this->ch));

		}

		$header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);

		$this->receivedHeaders = substr($body, 0, $header_size);

		return substr($body, $header_size);

	}

	private function send_fsock($data) {

		$body = '';
		
		$receiver = fwrite($this->ch, $data, strlen($data));
		
		if ( $receiver === false ) {
				
			debug("FSOCK request error","ERROR","httprequest");
			
			throw new Exception("Cannot write to socket");

		}
		
		while( !feof($this->ch) ) $body .= fgets($this->ch, $this->buffer); 
			
		list($this->receivedHeaders, $received) = preg_split("/\R\R/", $body, 2);

		return $received;

	}

	/**
	 * Close transport layer
	 */
	private function close_transport() {

		if ($this->curl) {

			curl_close($this->ch);

		}
		else {

			fclose($this->ch);

		}

	}
/********************* PRIVATE METHODS *******************/

}

?>