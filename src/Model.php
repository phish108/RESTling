<?php
namespace RESTling;

abstract class Model implements ModelInterface {
    protected $data;
    protected $input;
    private $errors = [];

    public function __construct(){
    }

    public function setInput($inputObject) {
        $this->input = $inputObject;
    }

    public function getHeaders() {
        return [];
    }

    public function addError($message) {
        $this->errors[] = $message;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasData()
    {
        return !empty($this->data);
    }

    /**
 	 * passes any data that should be sent to the client to the output model.
     *
     * A model needs to implement a streaming API if this function runs until
     * all content has been delivered.
     *
     * This function runs a non-caching environment, so every data passed to
     * the ```$outputModel``` will be immediately sent to the client. This will
     * not work around any HTTP server level caching.
     *
     * if a model handles its own data streaming, then it should use the
     * $outputModel's ```bypass()``` method.
 	 *
 	 * @param RESTling\Output $outputModel
	 */
    final public function handleData($output)
    {
        if ($this->hasData()) {
            if (is_object($output) && method_exists($output, "data")) {
                $output->data($this->data);
            }
        }
        $this->data = null;
    }
}

?>
