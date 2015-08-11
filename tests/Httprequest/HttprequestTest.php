<?php

class HttprequestTest extends \PHPUnit_Framework_TestCase {

    /**
     * @expectedException        Comodojo\Exception\HttpException
     */
    public function testInvalidHost() {
    
        $http = new \Comodojo\Httprequest\Httprequest(false, true);

        $http->setHost(42);

    }

    public function testParameters() {
    
        $http = new \Comodojo\Httprequest\Httprequest(false, true);

        $result = $http->setHost('https://comodojo.org')
            ->setCurl(true)
            ->setAuth('BASIC','marvin','robot')
            ->setUserAgent('test_ua')
            ->setTimeout(10)
            ->setHttpVersion('1.1')
            ->setContentType('application/x-www-form-urlencoded')
            ->setPort(8080)
            ->setHttpMethod('DELETE')
            ->setProxy('http://proxy.com:8080','test','test')
            ->setHeader('Custom','header');

        $this->assertInstanceOf('\Comodojo\Httprequest\Httprequest', $result);

    }

}