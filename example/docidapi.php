<?php

// Document ID API example.
// This service listens to paths of different legths.
// If the path has 1 entry, the example method is called.
// If the path has 2 or more entries, the sample method is called.

// Moreover, the two path entries need to have values between 0 and 9.
// If the values are out of range, the service throws an error.

// Both methods accept GET and PUT methods. The put method is called on the
// parent path. THerefore, put_sample is called on /2 paths and put_example
// method is called on empty paths.
//
// All other method and path combinations will fail with a 405 response error
set_include_path(".." . PATH_SEPARATOR . get_include_path());

include('contrib/Restling.auto.php');

class OpVal
    extends \RESTling\Validator {

    // validate accepts no additional pathinfo for put methods
    // also the plain get request rejects additional pathinfo requests
    protected function validate() {
        switch ($this->operation) {
            case "put":
                if (!empty($this->path_info)) {
                    return false;
                }
                break;
            default:
                if (empty($this->path_info) ||
                    $this->path_info[0] <= 0 ||
                     $this->path_info[0] >= 10) {
                    return false;
                }
                break;
        }

        if ($this->operation == "get" && !empty($this->path_info)) {
            return false;
        }
        return true;
    }
}

class DocIDAPIExample
      extends \RESTling\Service
{
    protected function initializeRun() {
        $this->addHeaderValidator(new OpVal());
    }

    // this operation is called when no path parameters are available
    protected function get()
    {
        $this->data = 'get default ok';
    }

    // GET /1
    protected function get_example()
    {
        $this->data = 'get example ok';
    }

    // POST /
    protected function put_example()
    {
        $this->data = 'post example ok';
    }

    // GET /1/2
    protected function get_sample()
    {
        $this->data = 'get sample ok';
    }

    // PUT /1/
    protected function put_sample()
    {
        $this->data = 'put sample ok';
    }
}

$service = new DocIDAPIExample();

$service->run();
?>
