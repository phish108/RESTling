<?php
require_once __DIR__."/../vendor/autoload.php";

// slightly complex API example.
// This service listens to 2 REST paths: 'example' and 'sample'. other paths are not permitted.

// The sample path accepts only GET and PUT methods and the example path accepts
// only GET and POST methods.
//
// All other method and path combinations will fail with a 405 response error

class StreamingAPIExample
{
    // this operation is called when no path parameters are available
    public function get($in, $out)
    {
        $this->data = 'get default ok';
    }

    // GET /example < sends a plain text data stream
    public function get_example($in, $out)
    {
        // prepare the stream
        // $input->setResponseType("text/plain");
        // input->setResponseType($this->response_type);

        // IRL we would PREPARE a complex operation that yields data chunks
        // and EXECUTE the operation in your data handler.
        $out->content_type = "application/json";
        $out->data = array("Example", "Stream", "Is", "OK");
        $out->dataHandler([$this, "handleData"]);
    }

    // GET /sample < sends a JSON data stream
    public function get_sample($in, $out)
    {
        $out->content_type = "application/json";
        $out->data = [["chunk" =>"Sample"],
                      ["chunk" => "JSON"],
                      ["chunk" => "is"],
                      ["chunk" => "OK"]];

        $out->dataHandler([$this, "handleData"]);
    }

    /**
     * The streaming magic happens in the handleData function.
     * for large DB requests, you may want to defer the request until this
     * function.
     */
    public function handleData($proc, $out)
    {
        if (!empty($out->data)) {
        // start streaming
            $proc->start(); // creates an array wrapper for json objects

            foreach ($out->data as $chunk)
            {
                $proc->data($chunk, ", "); // we need to tell the text output to separate the data chunks.
                // by default text data chunks have no separator!
                // by default json data chunks are seperated by comman
            }

            $proc->end(); // closes the array wrapper again
        }
    }
}

$service = new RESTling\Service();
$service->run(new StreamingAPIExample());
?>
