<?php

class BaseStreamTest extends \Comodojo\Httprequest\Tests\Base {

    public function setUp() {

        $this->http = new \Comodojo\Httprequest\Httprequest(false, false);

    }

    public function testGetChannel() {

        $this->http->setHost("http://httpbin.org")->get();

        $channel = $this->http->getChannel();

        $this->assertInternalType('resource', $channel);

        $this->assertEquals('stream-context', get_resource_type($channel));

    }

}
