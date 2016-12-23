<?php

// Very Basic RESTling Service responding to GET, PUT, POST and DELETE
// requests.

require_once __DIR__."/../vendor/autoload.php";


// inherit data handling functions from RESTling\Model
// for complex data processing you may want to override the
// hasData() and getData() functions.
// If you want to provide complex error information, you want to
// implement  
class RestlingTest extends \RESTling\Model
{
    public function get()
    {
        $this->data = 'get ok';
    }

    public function post()
    {
        $this->data = 'post ok';
    }

    public function put()
    {
        $this->data = 'put ok';
        return "Created"; // return created error
    }

    public function delete()
    {
        error_log("delete");
        return "Gone"; // return Gone error
    }
}

$service = new RESTling\Service();
$service->setModel(new RestlingTest());

$service->run();
?>
