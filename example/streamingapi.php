<?php
require_once __DIR__."/../vendor/autoload.php";

// slightly complex API example.
// This service listens to 2 REST paths: 'example' and 'sample'. other paths are not permitted.

// The sample path accepts only GET and PUT methods and the example path accepts
// only GET and POST methods.
//
// All other method and path combinations will fail with a 405 response error

class StreamingAPIExample
      extends \RESTling\Model
{
    // This property is used for passing on the stream.
    private $mystream;
    // this property only needed because we have different response types for the
    // service operations.
    private $response_type = "text/plain";

    // this operation is called when no path parameters are available
    protected function get()
    {
        $this->data = 'get default ok';
    }

    // GET /example < sends a plain text data stream
    protected function get_example($input)
    {
        // prepare the stream
        $input->setResponseType($this->response_type);
        $this->mystream = array("Example ", "Stream ", "Is ", "OK");
    }

    // GET /sample < sends a JSON data stream
    protected function get_sample($input)
    {
        $this->response_type = "application/json";
        $input->setResponseType($this->response_type);
        $this->mystream = array(array("chunk" =>"Sample"),
                                array("chunk" => "JSON"),
                                array("chunk" => "is"),
                                array("chunk" => "OK"));
    }

    /**
 	 * The streaming API needs to inform the service class that there is data to
     * handle.
 	 *
 	 * @return boolean
	 */
	public function hasData() {
        if (!empty($this->mystream)) {
            return true;
        }
        return parent::hasData();
    }

    /**
     * The streaming magic happens in the handleData function.
     * for large DB requests, you may want to defer the request until this
     * function.
     */
    protected function handleData($output)
    {
        $output = false;
        $json   = false; // only needed because we run multiple output types
        if ($this->response_type == "application/json") {
            $json = true;
            $output->data("]");
        }

        foreach ($this->mystream as $chunk)
        {
            if ($json && $output) {
                $output->data(","); // don't forget the separators
            }
            $output->data($chunk);
            $output = true;
        }
        if ($json) {
            $output->data("]");
        }
    }
}

$service = new RESTling\Service();
$service->run(new StreamingAPIExample());
?>
