<?php
require_once __DIR__."/../vendor/autoload.php";

// Document ID API example.

// This service listens to paths of different legths.
// It uses the same model as the complex API example
// If the path has 1 entry, the example method is called.
// If the path has 2 or more entries, the sample method is called.
//
// The values of the path are stored in the "path parameters" of the input
// object.
//
// All other method and path combinations will fail with a 405 response error

class DocApiService extends RESTling\Service {
    protected function findOperation() {
        $op = strtolower($_SERVER["REQUEST_METHOD"]);
        $this->operation = $op;

        if (array_key_exists("PATH_INFO", $_SERVER)) {
            $pi = explode("/",trim($_SERVER["PATH_INFO"], "/"));
            if (count($pi) == 1) {
                $ext = "example";
            }
            elseif (count($pi) == 2) {
                $ext = "sample";
            }
            $this->operation = $op ."_".$ext;
        }

        parent::findOperation();
    }
    protected function parseInput() {
        parent::parseInput();
        $pi = explode("/",trim($_SERVER["PATH_INFO"], "/"));
        $param = [];
        if (count($pi) >= 1) {
            $param["example"] = $pi[0];
        }
        if (count($pi) == 2) {
            $param["sample"] = $pi[1];
        }
        $this->inputHandler->setPathParameters($param);
    }
}


class ComplexAPIExample
      extends \RESTling\Model
{
    // this operation is called when no path parameters are available
    protected function get()
    {
        $this->data = 'get default ok';
    }

    // GET /example
    protected function get_example($input)
    {
        $param = $this->getParameter("example", "path");
        $this->data = 'get example ok ' . $param;
    }

    // POST /example
    protected function post_example($input)
    {
        $param = $this->getParameter("example", "path");
        $this->data = 'post example ok ' . $param;
    }

    // GET /sample
    protected function get_sample($input)
    {
        $param = $this->getParameter("sample", "path");
        $this->data = 'get sample ok ' . $param;
    }

    // PUT /sample
    protected function put_sample($input)
    {
        $param = $this->getParameter("sample", "path");
        $this->data = 'put sample ok ' . $param . " " . json_encode($input->getBody());
    }
}

$service = new DocApiService();

$service->run(new ComplexAPIExample());
?>
