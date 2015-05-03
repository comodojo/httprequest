<?php namespace Comodojo\Httprequest\Tests;

class BaseTest extends \PHPUnit_Framework_TestCase {

    protected $http = null;

    protected function setUp() {
        
        $this->http = new \Comodojo\Httprequest\Httprequest("http://www.example.com");

    }

    public function testGetSimpleHttp() {
        
        $body = $this->http->get();

        $return_code = $this->http->getHttpStatusCode();

        $received_headers = $this->http->getReceivedHeaders();

        $this->assertStringStartsWith("<!doctype html>", $body);

        $this->assertEquals(200, $return_code);

        $this->assertInternalType('array', $received_headers);

        $this->assertArrayHasKey("Content-Type", $received_headers);

    }

}
