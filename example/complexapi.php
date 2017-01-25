<?php
require_once __DIR__."/../vendor/autoload.php";

// slightly complex API example.
// This service listens to 2 REST paths: 'example' and 'sample'. other paths are not permitted.

// The sample path accepts only GET and PUT methods and the example path accepts
// only GET and POST methods.
//
// All other method and path combinations will fail with a 405 response error

class ComplexAPIExample
{
    // this operation is called when no path parameters are available
    public function get($in, $out)
    {
        $out->data = 'get default ok';
    }

    // GET /example
    public function get_example($in, $out)
    {
        $out->data = 'get example ok';
    }

    // POST /example
    public function post_example($in, $out)
    {
        $out->data = 'post example ok';
    }

    // GET /sample
    public function get_sample($in, $out)
    {
        $out->data = 'get sample ok';
    }

    // PUT /sample
    public function put_sample($in, $out)
    {
        $out->data = 'put sample ok ' . json_encode($in->getBody());
    }
}

$service = new RESTling\Service();
$service->run(new ComplexAPIExample());

?>
