<?php

// slightly complex API example.
// This service listens to 2 REST paths: 'example' and 'sample'. other paths are not permitted.

// The sample path accepts only GET and PUT methods and the example path accepts
// only GET and POST methods.
//
// All other method and path combinations will fail with a 405 response error

include('../contrib/Restling.auto.php');

class ComplexAPIExample
      extends \RESTling\RESTling
{
    // this operation is called when no path parameters are available
    protected function get()
    {
        $this->data = 'get default ok';
    }

    // GET /example
    protected function get_example()
    {
        $this->data = 'get example ok';
    }

    // POST /example
    protected function post_example()
    {
        $this->data = 'post example ok';
    }

    // GET /sample
    protected function get_sample()
    {
        $this->data = 'get sample ok';
    }

    // PUT /sample
    protected function put_sample()
    {
        $this->data = 'put sample ok ' . $this->input;
    }
}

$service = new ComplexAPIExample();

// $service->addValidator(new OauthSession($dbh)); // you may add some header validation at this point
// $service->addCORShost('*', 'Authorization');    // allow cross origin headers (carfully, this won't work with some clients)

$service->run();
?>
