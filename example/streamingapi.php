<?php

// slightly complex API example.
// This service listens to 2 REST paths: 'example' and 'sample'. other paths are not permitted.

// The sample path accepts only GET and PUT methods and the example path accepts
// only GET and POST methods.
//
// All other method and path combinations will fail with a 405 response error

include('../contrib/Restling.auto.php');

class StreamingAPIExample
      extends \RESTling\Service
{
    // This property is used for passing on the stream.
    private $mystream;

    // this operation is called when no path parameters are available
    protected function get()
    {
        $this->data = 'get default ok';
    }

    // GET /example < sends a plain text data stream
    protected function get_example()
    {
        $this->streaming = true;
        // prepare the stream
        $this->response_type = "text";
        $this->mystream = array("Example ", "Stream ", "Is ", "OK");
    }

    // GET /sample < sends a JSON data stream
    protected function get_sample()
    {
        $this->streaming = true;
        $this->response_type = "json";
        $this->mystream = array(array("chunk" =>"Sample"),
                                array("chunk" => "JSON"),
                                array("chunk" => "is"),
                                array("chunk" => "OK"));
    }

    /**
     * if the stream is generated from a database, then start and end stream
     * help to complete the data structure.
     *
     * Start and end stream functions can be tailored to specific response
     * types.
     */
    protected function init_stream_json()
    {
        echo ("[");
    }

    protected function end_stream_json()
    {
        echo ("]");
    }

    /**
     * The streaming magic happens in the stream function. Note, that this function
     * does not use events.
     */
    protected function stream()
    {
        foreach ($this->mystream as $chunk)
        {
            $this->respond($chunk);
        }
    }

    /**
     * many streams require cleanup of some kind. The cleanup is called after
     * all data is sent. This method is called, both in document as well as
     * in streaming mode.
     *
     * This is useful if multiple response types can get streamed.
     */
    protected function cleanup()
    {}
}

$service = new StreamingAPIExample();

// $service->addValidator(new OauthSession($dbh)); // you may add some header validation at this point
// $service->addCORShost('*', 'Authorization');    // allow cross origin headers (carfully, this won't work with some clients)

$service->run();
?>
