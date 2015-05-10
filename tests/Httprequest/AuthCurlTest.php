<?php

class AuthCurlTest extends \Comodojo\Httprequest\Tests\Auth {

    public function setUp() {

        $this->http = new \Comodojo\Httprequest\Httprequest(false, true);

    }

}
