<?php

// Very Basic RESTling Service responding to GET, PUT, POST and DELETE
// requests.
include('../contrib/Restling.auto.php');

class RestlingTest
      extends RESTling
{
    protected function get()
    {
        $this->data = 'get ok';
    }

    protected function post()
    {
        $this->data = 'post ok';
    }

    protected function put()
    {
        $this->data = 'put ok';
    }

    protected function delete()
    {
        $this->gone("delete ok");
    }
}

$service = new RestlingTest();

$service->run();
?>