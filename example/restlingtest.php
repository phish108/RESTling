<?php

// inherit data handling functions from RESTling\Model
// for complex data processing you may want to override the
// hasData() and getData() functions.
// If you want to provide complex error information, you want to
// implement
class RestlingTest
{
    public function get($input, $output)
    {
        $output->data = 'get ok ';

        if ($input->hasParameter("foo", "query")) {
            $d = $input->getParameter("foo", "query");
            if (is_array($d)) {
                $output->data .= "multiple " . join(", ", $d);
            }
            else {
                $output->data .= "singleton $d";
            }
        }
    }

    public function post($input, $output)
    {
        $output->data = 'post ok';
    }

    public function put($input, $output)
    {
        $output->data = 'put ok';
        throw new \RESTling\Exception\Created(); // return created error
    }

    public function delete($input, $output)
    {
        error_log("delete");
        throw new \RESTling\Exception\Gone(); // return Gone error
    }
}

?>
