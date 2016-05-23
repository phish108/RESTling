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

include('../contrib/Restling.auto.php');

class DocIDAPIExample
      extends \RESTling\RESTling
{
    /**
     * findOperation() helps to determin the correct operation handler.
     * This function MUST return a method name. RESTling will later test
     * if this function name actually exists.
     *
     * At this level the service just tests the structure of the pathinfo.
     * It does not VALIDATE if the reuqest path is OK.
     */
    protected function findOperation($method, $path)
    {
        $ops = ["example", "sample"];
        $cop = count($ops);
        $rel = ($method == "put" ? 0 : 1);
        $cnt = (empty($path) ? 0 : count($path)) - $rel;

        if ($cnt >= 0 && $cnt < $cop )
        {
            return $method . '_' . $ops[$cnt];
        }
        else if ($cnt >= count($ops)) {
            return $method . '_' . $ops[$cop - 1];
        }

        return $method;
    }

    /**
     * validateOperation() should get used to test the validity of the data.
     * validateOperation() is only called of the operation method exists.
     *
     * At this level the requested path data is actually validated.
     *
     * In this example the first 2 path entries need to be between 0 and 9.
     * Otherwise, the service responds with an error.
     */
    protected function validateOperation()
    {
        $op = explode("_", $this->operation);
        if (count($op) > 1)
        {
            switch ($op[1]) {
            case "sample":
                $this->log("test sample id");

                if ($op[0] == 'put' && // put must have a sample id (in this case)
                    !empty($this->path_info[1]))
                {
                    $this->status = \RESTling\RESTling::OPERATION_FORBIDDEN;
                    $op[0] = 'get'; // the example ID needs to be valid
                }
                else if ($this->path_info[1] < 0 || $this->path_info[1] >= 10)
                {
                    $this->log("sample id forbidden");
                    $this->status = \RESTling\RESTling::OPERATION_FORBIDDEN;
                    $op[0] = 'get'; // the example ID needs to be valid
                }

                // no break here,  need to test the first path entry's validity, too
            case "example":
                $this->log("test example id '" . $this->path_info[0] . "'");

                if ($op[0] != "put" && // put has no id
                    ($this->path_info[0] < 0 || $this->path_info[0] >= 10))
                {
                    $this->log("example id forbidden");
                    $this->status = \RESTling\RESTling::OPERATION_FORBIDDEN;
                }
                // again no break, let the default stop the switch.
            default:
                break;
            }
        }
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

// $service->addValidator(new OauthSession($dbh)); // you may add some header validation at this point
// $service->addCORShost('*', 'Authorization');    // allow cross origin headers (carfully, this won't work with some clients)

$service->run();
?>
