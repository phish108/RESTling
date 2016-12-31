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
    public function get($input)
    {
        $this->data = 'get ok ';

        if ($input->hasParameter("foo", "query")) {
            $d = $input->getParameter("foo", "query");
            if (is_array($d)) {
                $this->data .= "multiple " . join(", ", $d);
            }
            else {
                $this->data .= "singleton $d";
            }
        }
    }

    public function post()
    {
        $this->data = 'post ok';
    }

    public function put()
    {
        $this->data = 'put ok';
        throw new \RESTling\Exception\Created(); // return created error
    }

    public function delete()
    {
        error_log("delete");
        throw new \RESTling\Exception\Gone(); // return Gone error
    }
}

$service = new RESTling\Service();
$service->run(new RestlingTest());
?>
