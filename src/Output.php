<?php

namespace RESTling;

class Output implements Interfaces\Output {
    private $contentType = "text/plain";

    private $statusCode;

    private $traceback = [];
    private $dataSent= false;

    private $CORScontext = [];

    public function __construct() {
    }

    public function content_type() {
        return $this->contentType;
    }

    public function setStatus($code) {
        $this->statusCode = $code;
    }

    public function setContentType($ct) {
        $this->contentType = $ct;
    }

    public function addTraceback($message) {
        if (!empty($message)) {
            if (!is_array($message)) {
                $message = [$message];
            }

            $this->traceback = array_merge($this->traceback, $message);
        }
    }

    public function setCORSContext($origin, $methods) {
        if (!empty($methods) && !empty($origin) && is_string($origin)) {
            if (is_array($methods)) {
                $methods = join(", ", $methods);
            }
            $this->CORScontext = ["origin" => $orign, "methods" => $methods];
        }
    }

    public function process($model) {
        // unless any errors were reported, we assume OK
        if (!$this->statusCode) {

            $this->statusCode = 200;

            if (is_object($model) &&
                method_exists($model, "hasData") &&
                !$model->hasData()) {

                $this->statusCode = 204;
            }
        }

        // generate headers
        http_response_code($this->statusCode);

        if ($this->contentType) {
            // QUESTION might be a problem if used with bypassing?
            header('Content-Type: ' . strtolower($this->contentType));
        }

        header('X-UA-Compatible: IE=edge'); // force IE to obey!
        header('Cache-Control: no-cache');  // forbid caching

        if (!empty($this->CORScontext)){
            header('Access-Control-Allow-Origin: '  . $this->CORScontext["origin"]);
            header('Access-Control-Allow-Methods: ' . $this->CORScontext["methods"]);
        }

        if($model) {
            // extend trackback
            if (method_exists($model, "getErrors")) {
                $this->addTraceback($model->getErrors());
            }

            // check if there are extra headers
            if (method_exists($model, "getHeaders") &&
                !empty($headers = $model->getHeaders())) {
                foreach ($headers as $headername => $headervalue) {
                    header($headername . ": " . $headervalue);
                }
            }

            // let the model control the data stream
            if (method_exists($model, "handleData")) {
                $model->handleData($this);
            }
        }

        if (!$this->dataSent && !empty($this->traceback)) {
            // format traceback
            $this->formatTraceback();
        }
    }

    protected function formatTraceback() {
        $this->data(join(', ', $this->traceback));
    }


    /**
 	 * marks the beginning of a data stream
 	 *
 	 * @param $data - extra data to prepend the stream
	 */
	public function start($data="") {
        if (!empty($data)) {
            ob_end_flush();
            echo $data;
            ob_start();
        }
        $this->dataSent = false;
    }

    /**
 	 * marks the end of a data stream
 	 *
 	 * @param $data - extra data to add data the stream
	 */
	public function end($data="") {
        if (!empty($data)) {
            ob_end_flush();
            echo $data;
            ob_start();
        }
    }

    /**
     * the data() method sends a data chunk to the client
     */
    public function data($data, $seperator="") {
        ob_end_flush();
        // immediately send the data to the client
        if (!empty($seperator) && $this->dataSent) {
            echo($seperator);
        }
        if (!empty($data)) {
            echo($data);
        }

        ob_start();
        $this->dataSent = true;
    }

    /**
 	 * the bypass() method allows models to handle data delivery independently.
 	 *
 	 * @param type
	 */
	public function bypass() {
        $this->dataSent = true;
    }

    public function hasSentData() {
        return $this->dataSent;
    }
}

?>
