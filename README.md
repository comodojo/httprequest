## comodojo/httprequest

HTTP request library for comodojo.

Main features:

- BASIC or NTLM (requires [php curl library](http://php.net/manual/en/book.curl.php)) authentication support
- proxy support
- allowed http methods: GET, POST, PUT, DELETE
- CURL or stream working mode
- request customization (useragent, http version, content type, ...)

## Installation

- Using Composer

	Install [composer](https://getcomposer.org/), then:

	`` composer require comodojo/httprequest 1.1.* ``

-	Manually

	Download zipball from GitHub, extract it, include `src/Httprequest.php` and `src/Exception/HttpException.php` in your project.

## Basic usage

Library usage is trivial: first create an instance of Httprequest specifing remote host address, then use `get` or `send` method to start request. It's important to wrap code in a try/catch block to handle exceptions (if any).

Constructor accepts two parameters: remote host address (required) and a boolean value (optional) that, if false, will force lib to use streams instead of curl. 

- Using get:

    ```php
    try {
	
	    // create an instance of Httprequest
        $http = new \Comodojo\Httprequest\Httprequest("www.example.com");
    
        // or:
        // $http = new \Comodojo\Httprequest\Httprequest();
        // $http->setHost("www.example.com");
        
        // get remote data
        $result = $http->get();
        
	} catch (\Comodojo\Exception\HttpException $he) {

		/* handle http specific exception */

	} catch (\Exception $e) {
		
		/* handle generic exception */

	}

	```

- Using send:

    ```php
    $data = array('foo'=>'bar', 'baz'=>'boom');
    
    try {
	
	    // create an instance of Httprequest
        $http = new \Comodojo\Httprequest\Httprequest("www.example.com");
        
        // get remote data
        $result = $http->setHttpMethod("POST")->send($data);
        
	} catch (\Comodojo\Exception\HttpException $he) {

		/* handle http specific exception */

	} catch (\Exception $e) {
		
		/* handle generic exception */

	}

	```

## Class setters (chainable methods)

- Set destination port (default 80)

    ```php
    $http->setPort(8080);
    
    ```

- Set timeout (in secs)

    ```php
    $http->setTimeout(10);
    
    ```
    
- Set a custom user agent (default to 'Comodojo-Httprequest')

    ```php
    $http->setUserAgent("My-Custom-User-Agent");
    
    ```
    
- Set HTTP version (1.0 or 1.1)

    ```php
    $http->setHttpVersion("1.1");
    
    ```
    
- Set content type (default to 'application/x-www-form-urlencoded' and used only with `send` method)

    ```php
    $http->setContentType("multipart/form-data");
    
    ```
    
- Set additional/custom headers:

    ```php
    $http->setHeader("My-Header","foo");
    
    ```    
- Set authentication:

    ```php
    // NTLM
    $http->setAuth("NTLM", "myusername", "mypassword");
    
    // BASIC
    $http->setAuth("BASIC", "myusername", "mypassword");
    
    ```

- Set proxy:

    ```php
    // No authentication
    $http->setProxy(proxy.example.org);
    
    // Authentication
    $http->setProxy(proxy.example.org, "myusername", "mypassword");
    
    ```

## Class getters

- Get response headers:

    ```php
    // After a request...
    
    $headers = $http->getReceivedHeaders();
    
    ```
    
- Get HTTP received status code:

    ```php
    // After a request...
    
    $code = $http->getHttpStatusCode();
    
    ```

## Multiple requests

The `reset` method helps resetting options and data channel; for example:

```php
try {

    // create an instance of Httprequest
    $http = new \Comodojo\Httprequest\Httprequest();
    
    // first request
    $first_data = $http->setHost("www.example.com")->get();
    
    // channel reset
    $http->reset();
    
    // second request
    $second_data = $http->setHost("www.example2.com")->setHttpMethod("POST")->send(array("my"=>"data"));
    
} catch (\Comodojo\Exception\HttpException $he) {

	/* handle http specific exception */

} catch (\Exception $e) {
	
	/* handle generic exception */

}

```
