<?php namespace Comodojo\Httprequest\Tests;

class Base extends \PHPUnit_Framework_TestCase {

    protected $http = null;

    public function testGetHtml() {
    
        $body = $this->http->setHost("http://httpbin.org")->get();

        $return_code = $this->http->getHttpStatusCode();

        $received_headers = $this->http->getReceivedHeaders();

        $this->assertStringStartsWith("<!DOCTYPE html>", $body);

        $this->assertEquals(200, $return_code);

        $this->assertInternalType('array', $received_headers);

        $this->assertArrayHasKey("Content-Type", $received_headers);

    }

    public function testGetData() {

        $data = array('foo'=>'bar', 'baz'=>'boom');

        $body = $this->http->setHost("http://httpbin.org/get")->send($data);

        $return_code = $this->http->getHttpStatusCode();

        $this->assertEquals(200, $return_code);

        $result = json_decode($body,true);

        $this->assertEmpty(array_diff($result['args'], $data));

    }

    public function testPostData() {

        $data = array('foo'=>'bar', 'baz'=>'boom');

        $body = $this->http->setHost("http://httpbin.org/post")->setHttpMethod("POST")->send($data);

        $return_code = $this->http->getHttpStatusCode();

        $this->assertEquals(200, $return_code);

        $result = json_decode($body,true);

        $this->assertEmpty(array_diff($result['form'], $data));

    }

    public function testPutData() {

        $data = array('foo'=>'bar', 'baz'=>'boom');

        $body = $this->http->setHost("http://httpbin.org/put")->setHttpMethod("PUT")->send($data);

        $return_code = $this->http->getHttpStatusCode();

        $this->assertEquals(200, $return_code);

        $result = json_decode($body,true);

        $this->assertEmpty(array_diff($result['form'], $data));

    }

    public function testDeleteData() {

        $data = array('foo'=>'bar', 'baz'=>'boom');

        $body = $this->http->setHost("http://httpbin.org/delete")->setHttpMethod("DELETE")->send($data);

        $return_code = $this->http->getHttpStatusCode();

        $this->assertEquals(200, $return_code);

        $result = json_decode($body,true);

        $this->assertEmpty(array_diff($result['form'], $data));

    }

    public function testGzipEncoding() {

        $body = $this->http->setHost("http://httpbin.org/gzip")->get();

        $return_code = $this->http->getHttpStatusCode();

        $this->assertEquals(200, $return_code);

        $result = json_decode($body,true);

        $this->assertTrue($result['gzipped']);

    }

}
