<?php

class BaseStreamTest extends \Comodojo\Httprequest\Tests\Base {

    public function setUp() {

        $this->http = new \Comodojo\Httprequest\Httprequest(false, false);

    }

}
