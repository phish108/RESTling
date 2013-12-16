<?php

// Very Basic RESTling Service responding to GET, PUT, POST and DELETE 
// requests. 
include('include/RESTling/contrib/Restling.auto.php');

class RestlingTest extends RESTling {
    protected function handle_GET() {
        $this->data = 'get ok';   
    }
    
    protected function handle_POST() {
        $this->data = 'post ok';
    }
    
    protected function handle_PUT() {
        $this->data = 'put ok';   
    }
    
    protected function handle_DELETE() {
        $this->gone("delete ok"); 
    }
}

$service = new RestlingTest();

$service->run();
?>