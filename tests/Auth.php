<?php namespace Comodojo\Httprequest\Tests;

class Auth extends \PHPUnit_Framework_TestCase {

    public function testBasicAuth() {

        $body = $this->http->setHost("http://httpbin.org/basic-auth/user/passwd")->setAuth("BASIC", "user", "passwd")->get();

        $return_code = $this->http->getHttpStatusCode();

        $this->assertEquals(200, $return_code);

    }

}
