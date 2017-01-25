<?php

// Very Basic RESTling Service responding to GET, PUT, POST and DELETE
// requests.

require_once __DIR__."/../vendor/autoload.php";

/**
 * This example uses the OpenAPI Service loader based on the spec given in
 * simpleApi.json. The spec uses operationId attributes to determine which
 * model methods to call.
 *
 * The OpenAPI Service loader will select the RestlingTest model because of
 * Service Title to Classname mapping.
 */
class RestlingTest
{
    public function gimmeV($in, $out)
    {
        $out->data = 'get ok';
    }

    public function postbox($in, $out)
    {
        $out->data = 'post ok';
    }

    public function putMeDown($in, $out)
    {
        $out->data = 'put ok';
        throw new \RESTling\Exception\Created(); // return created "error"
    }

    public function erase($in, $out)
    {
        error_log("delete");
        throw new \RESTling\Exception\Gone();  // return Gone "error"
    }

    public function gimmeVmore($input, $out)
    {
        $out->data = 'get ok + ';
        if ($input &&
            $input->hasParameter("foo")) {
            $out->data .= $input->getParameter("foo");
        }
    }

    public function putMeFurtherDown($input, $out)
    {
        $out->data = 'put ok + ';
        if ($input &&
            $input->hasParameter("foo")) {
            $out->data .= $input->getParameter("foo");
        }
    }
}

$service = new RESTling\OpenAPI();

$service->loadConfigFile(__DIR__ . "/simpleApi.json");

$service->run();
?>
