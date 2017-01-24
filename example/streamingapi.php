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
    public function get()
    {
        $this->data = 'get default ok';
    }

    // GET /example < sends a plain text data stream
    public function get_example($input)
    {
        // prepare the stream
        $input->setResponseType("text/plain");
        $input->setResponseType($this->response_type);

        // IRL we would PREPARE a complex operation that yields data chunks
        // and EXECUTE the operation in handleData()
        $this->mystream = array("Example", "Stream", "Is", "OK");
    }

    // GET /sample < sends a JSON data stream
    public function get_sample($input)
    {
        $input->setResponseType("application/json");
        $this->mystream = [["chunk" =>"Sample"],
                           ["chunk" => "JSON"],
                           ["chunk" => "is"],
                           ["chunk" => "OK"]];
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
    public function handleData($output, $seperator="")
    {
        parent::handleData($output);

        if (!empty($this->mystream)) {
        // start streaming
            $output->start(); // creates an array wrapper for json objects

            foreach ($this->mystream as $chunk)
            {
                $output->data($chunk, ", "); // we need to tell the text output to separate the data chunks.
                // by default text data chunks have no separator!
                // by default json data chunks are seperated by comman
            }

            $output->end(); // closes the array wrapper again
        }
    }
}

$service = new RESTling\Service();
$service->run(new StreamingAPIExample());
?>
