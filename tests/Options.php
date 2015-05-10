<?php namespace Comodojo\Httprequest\Tests;

class Options extends \PHPUnit_Framework_TestCase {

    protected $http = null;

    public function testUserAgent() {
    
        $user_agent = "My-Custom-User-Agent";

        $body = $this->http->setHost("http://httpbin.org/user-agent")->setUserAgent("My-Custom-User-Agent")->get();

        $return_code = $this->http->getHttpStatusCode();

        $received_headers = $this->http->getReceivedHeaders();

        $this->assertEquals(200, $return_code);

        $result = json_decode($body,true);

        $this->assertSame($user_agent, $result['user-agent']);

    }

    public function testInjectedHeaderCompact() {
    
        $header = "Comodojo-Header: 42";

        $body = $this->http->setHost("http://httpbin.org/headers")->setHeader($header)->get();

        $return_code = $this->http->getHttpStatusCode();

        $received_headers = $this->http->getReceivedHeaders();

        $this->assertEquals(200, $return_code);

        $result = json_decode($body,true);

        $this->assertArrayHasKey("Comodojo-Header", $result['headers']);

        $this->assertSame("42", $result['headers']['Comodojo-Header']);

    }

    public function testInjectedHeaderExtended() {
    
        $header = "Comodojo-Header";

        $header_content = 42;

        $body = $this->http->setHost("http://httpbin.org/headers")->setHeader($header, $header_content)->get();

        $return_code = $this->http->getHttpStatusCode();

        $received_headers = $this->http->getReceivedHeaders();

        $this->assertEquals(200, $return_code);

        $result = json_decode($body,true);

        $this->assertArrayHasKey("Comodojo-Header", $result['headers']);

        $this->assertSame("42", $result['headers']['Comodojo-Header']);

    }

    public function testContentType() {

        $body = $this->http->setHost("http://httpbin.org/post")->setHttpMethod("POST")->setContentType("text/xml")->send("<method>test</method>");

        $result = json_decode($body,true);

        $this->assertArrayHasKey("Content-Type", $result['headers']);

        $this->assertSame("text/xml", $result['headers']['Content-Type']);

    }

    /**
     * @expectedException        Comodojo\Exception\HttpException
     */
    public function testTimeoutException() {
    
        $body = $this->http->setHost("http://httpbin.org/delay/10")->setTimeout(2)->get();

    }

}
