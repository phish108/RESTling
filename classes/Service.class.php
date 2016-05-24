<?php

namespace RESTling;

/**
 * @class: RESTling\Service
 *
 * This is a basic service class for RESTful services that provides the basic
 * logic for many RESTful Web-services.
 * RESTling is a simple class for developing structured and extensible
 * web-services in object-oriented PHP.
 * This base class provides a generic precessing pipeline for RESTful services.
 * This processing pipeline focuses on handling one request. The pipeline should
 * simplify the business logic of RESTful web-services written in PHP and support
 * the debugging process.
 *
 * ## Pipeline logic
 *
 * The RESTling pipeline has four basic phases.
 *
 * 1. Initialization Phase
 * 2. Request Verification Phase
 * 3. Request Operation Phase
 * 4. Response Generation Phase
 *
 * Each phase is covered by one or more methods of a RESTling class. The idea of RESTling is that is only required
 * to implement the business logic for the phases that affect the service.
 *
 * ## Running RESTling Services
 *
 * Running service instances are designed to be minimalistic. A typical service script is called as following:
 *
 *     $service = new MyServiceClass();
 *     // do some additional initialization if needed
 *     $service->run();
 *
 * Most of the RESTling magic is happening in the run() method. However, no service classs will have to overload this method.
 * Instead, all business logic will be implemented in the related phases, which typically boils down to implementing the
 * Request Operation Phase logic.
 *
 * ## Implementing a RESTling Service.
 *
 * A RESTling implementation requires a service class that implements the handler functions for the service operations.
 * By default the format for service operations is
 *
 *     protected function handle_HTTPREQUESTMETHOD()
 *     {
 *          // Service operation code goes here.
 *     }
 *
 * In practice this means that if a service supports a GET and a PUT request it needs to implement the
 * following functions:
 *
 *     protected function handle_GET(){}
 *     protected function handle_PUT(){}
 *
 * However, RESTling provides a flexible approach for implementing complex RESTful APIs through the
 * prepareOperation() method.
 *
 * ## Debugging RESTling
 *
 * RESTling inherits from the Logger class. Logger provides basic logging functions that should simplify the
 * debugging process on a server.
 *
 * When in debug mode, RESTling generates markers for the start and the end of a request handling. In case of
 * pipeline errors RESTling also provides an indication at what level the service failed.
 *
 * ## Cross Origin Resource Sharing (CORS) management functions
 *
 * RESTling implements basic CORS functions.
 *
 * - @method void allowCORS()
 * - @method void forbidCORS()
 * - @method void addCORShost($origin, $methods)
 *
 * In order to use CORS one needs to allow CORS requests. Using the allowCORS()
 * method. This will activate the CORS operations in the service. However, in order
 * to respond to CORS requests it is necessary to add CORS hosts using the
 * addCORSHost() method. This method allows to activate different operations
 * for different hosts unless one sets '*' as a hostname. the '*' hostname
 * automatically overwrites all other CORS specfications.
 *
 * By default CORS is activated. In order to completely deactivate CORS, a service
 * can invoke the forbidCORS() method. Subsequent addCORSHost() calls will not
 * overwrite this flag.
 *
 * CORS operations should be initialized during the intializeRun phase or from the
 * external business logic.
 *
 * Note, CORS requests may not include headers on all browsers.
 *
 * Cross Origin Resource Sharing is currently unreliable due to massive browser differences.
 * Therefore, the present state of RESTling's CORS support is likely to remain rudimentary.
 *
 */
class Service extends Logger
{
    const OK                  = 0;
    const UNINITIALIZED       = 1;

    // request level errors
    const BAD_HEADER          = 2;
    const BAD_URI             = 3;
    const BAD_METHOD          = 4;

    // application level errors
    const BAD_OPERATION       = 5;
    const OPERATION_FORBIDDEN = 6;
    const OPERATION_FAILED    = 7;
    const BAD_DATA            = 8;

    protected $response_code; ///< integer, three digit response code for the client response
    protected $response_type; ///< string, content type alias of the respose. This determines the response function to encode the data stash.

    /**
     * @property misc $data: Internal Service Data Stash.
     *
     * This is used to generate the service response.
     *
     * You can set this property directly or by using either of the setData() or setDataStash() functions.
     *
     * This property can hold arbitary data and the response_*() function will choose how to send it to the
     * client.
     */
    protected $data;          ///< result data

    protected $method;        ///< string, contains the request method.

    protected $path_info;     ///< array, contains the path operands for the request.

    protected $input;         ///< raw input data
    protected $inputData;     ///< processed input data if input was structured information
    protected $inputDataType; ///< MIME type of the processed input dats

    protected $query;         ///< raw query paramter string, use this value if you expect a single string.
    protected $queryParam;    ///< processed query paramter object (note that this can handle multiple parameters)

    protected $status;        ///< integer, contains the service's pipeline status.

    protected $withCORS    = true;
    protected $withCaching = false;
    protected $corsHosts;     ///< array, list of referer hosts that are allowed to call this service via CORS.

    protected $operation;     ///< string, function name of the operation to be called.

    private $headerValidators; ///< array, list of validators to be used for incoming headers
    private $dataValidators; ///< array, list of validators to be used for incoming data
    private $keepRawDataFlag = false;

    protected $streaming; ///< boolean value that determines whether operations should be "processed" or "streamed". Only set this property if you want to stream!

    public function __construct()
    {
        $this->mark( "********** NEW SERVICE REQUEST (" . get_class($this) . ") ***********");
        $this->corsHosts = array();
        $this->headerValidators = array();
        $this->dataValidators = array();

        $this->query = $_SERVER["QUERY_STRING"]; // normally ends in _GET, but qstring parsing in php sucks

        $this->queryParam = array();
        $this->path_info  = array();

        $this->method = $_SERVER['REQUEST_METHOD'];

        // secure get string handling
        $query = array();
        if (!empty($this->query))
        {
            $query  = preg_split('/[&;]+/', $this->query); // split at & or ;. Ignore sequences of separators
        }

        foreach( $query as $param )
        {
            list($name, $value) = explode('=', $param);
            $pn = urldecode($name);
            if (array_key_exists($pn, $this->queryParam))
            {
                if (gettype($this->queryParam[$pn]) != "array")
                {
                    $this->queryParam[$pn] = array($this->queryParam[$pn]);
                }
                $this->queryParam[$pn][] = urldecode($value);
            }
            else
            {
                $this->queryParam[$pn] = urldecode($value);
            }
        }

        // pull up the path info
        $path_info = "";
        if (array_key_exists("PATH_INFO", $_SERVER))
        {
            $path_info = $_SERVER['PATH_INFO'];
        }
        else
        {
            // newer PHP instances won't fillup the PATH_INFO from the REQUEST_URI under apache.
            $path_info = preg_replace('/^.*\.php/', '', $_SERVER["REQUEST_URI"]);
        }

        if (!empty($path_info))
        {
            // strip any potential querystring that is left in the url
            $path_info = preg_replace('/\?.*$/',  '', $path_info);

            // remove any leading or trailing slashes
            $path_info = preg_replace('/^\/*|\/*$/', '', $path_info);
            // condense multiple slashes to one
            $path_info = preg_replace('/\/+/', '/', $path_info);

            // don't explode empty paths
            if (!empty($path_info))
            {
                $this->path_info = explode('/', $path_info);
            }
        }

        $this->status = Service::OK;
    }

    /**
     * Desctructor()
     *
     * Sets the end marker after the service is completed. It serves only debugging purposes.
     */
    public function __destruct()
    {
        $this->mark( "********** END SERVICE REQUEST (" . get_class($this) . ")************");
    }

    /**
     */
    public function allowCORS()
    {
        $this->withCORS = true;
    }

    public function forbidCORS()
    {
        $this->withCORS = false;
    }

    public function keepRawData()
    {
        $this->keepRawDataFlag = true;
    }

    public function dropRawData()
    {
        $this->keepRawDataFlag = false;
    }

    /**
     * @method void addCORSHost($host, $methods)
     *
     * @param mixed $host: hostname string or array of hostnames
     * @param mixed $methods: method string or array of methods
     *
     * Adds a host that is an valid referrer for CORS requests.
     * If a service never adds a host but activates CORS requests, then RESTling will accept requests from
     * all sources.
     */
    public function addCORSHost($host, $methods)
    {
        // host can be an array. The methods are an array too. Note that this is not associative and
        // that the methods are allowed for the provided hosts.
        if (gettype($methods) === 'string')
        {
            $methods = array($methods);
        }
        else
        {
            foreach ($methods as $k => $m)
            {
                $methods[$k]= strtoupper($m);
            }
        }

        if ( gettype($host) === 'string' )
        {
            $host = array($host);
        }

        if (in_array('*', $host))
        {
            $this->corsHosts = array('*'=> $methods);
        }
        else
        {
            foreach ($host as $h)
            {
                $this->corsHosts[$h] = $methods;
            }
        }
    }

    /**
     * Cache handling
     *
     * @method void allowCache()
     * @method void forbidCache()
     *
     * Triggers the flag wether or not to set the Cache-control header. By default the
     * Cache-control is forbidden, which informs the clients that the requests
     * must not get chached (the Cache-control: no-cache header is set). If a
     * service allows caching, it can do so by calling allowCache(). This will
     * surpress the default cache-control header.
     */
    public function allowCache()
    {
        $this->withCaching = true;
    }

    public function forbidCache()
    {
        $this->withCaching = false;
    }

    /**
     * run()
     *
     * The  power horse of the service. This function decides which handler methods should be
     * called for the different HTTP request methods.
     *
     * The run process has 6 phases
     *
     * 0. internal run initialization (including loading of external configuration files)
     * 1. Operation preparation
     * 2. Operation verification
     * 3. Header validation
     * 4. Input Validation
     * 5. Operation handling
     * 6. Response generation
     *
     * Phase 1-5 are sequential based on the success of the previous operation.
     * Phase 6 is always executed and has 3 sub-steps
     *
     * 1. HTTP Response Code generation
     * 2. HTTP Header generation
     * 3. Response data generation
     *
     * The phases allow to organize your code logically as a process. This allows
     * you to focus on the business logic at hand.
     *
     * The internal run initialization is used to setup everything that is independent
     * to the service request, such as checking the validity of of the data base or testing
     * for initialization errors when a class is not loaded or a global property is not
     * initialized.
     *
     * The operation preparation checks if the service is called for an accepted URI. This also takes
     * over the path_info extraction, so you can switch your service into different modes.
     * The operation preparation identifies the method names for running a specific operation.
     *
     * The operation verification phase tests if the method should be accepted for the request
     * URI. This phase decides which handler function should be called. The method validation
     * is typically responsible for detecting protocol level errors.
     *
     * The header validation analyzes the request headers. This phase is typically responsible
     * for session management. During this phase are all registered header validators run.
     *
     * The input validation phase allows one to check the correctness of the incoming data.
     *
     * The operation handling calls the handler function for the request method that has been
     * determinated during the method validation phase.
     *
     * Finally the response generation generates the appropriate output for the internal
     * service state.
     *
     * Under normal circumstances a subclass will only need to implement the
     * method handling. Under normal conditions a subclass adds simply the
     * protected property $uri with an appropriate value.
     *
     *      protected $uri = 'my-service-path/my-service.php';
     *
     * By overriding the different validate* functions it is possible to implement
     * more complex validation procedures.
     *
     * The method validation checks if the service class implements a handler for the
     * requested HTTP operation.
     *
     * The header validation allows a header introspection before entering the
     * method handling. This is very useful for cookie validation or for session
     * management.
     *
     * The method handling calls the handler for the requested HTTP operation.
     * A sub-class needs to implement a handle_(METHODNAME) method for each
     * HTTP operation the service needs to handle. For example if a service
     * should handle GET and POST requests the service class needs to implement
     * a handle_GET() amd a handle_POST() method. Please note that the OPTIONS
     * operation is always supported for CORS interoperability.
     *
     * The response generation is separated into three substeps that are always
     * performed. These steps are responsible for returning the data in the
     * appropriate formats. Currently, three response types are supported:
     *
     * - plain text
     * - url-form-encoded
     * - JSON
     *
     * The preferred data type is determined by the response_type property.
     * If a service needs to support other data types the responseData()
     * method has to be overridden.
     */
    public function run()
    {
        $this->prepareRun();

        if ($this->status == Service::OK
            && !empty($this->operation))
        {
            // now call the operation
            call_user_func(array($this, $this->operation)); // try catch?
        }

        $this->stream_headers();

        // ensure that the response code and the headers are properly set
        if (isset($this->streaming) && $this->streaming)
        {
            $this->init_stream();
            $this->stream();
        }

        // ensure that the client actually receives all data.
        if (isset($this->data))
        {
            // $this->mark('default ' . $this->data);
            $this->respond();
        }

        if (isset($this->streaming) && $this->streaming)
        {
            $this->end_stream();
        }

        $this->cleanup();
    }

    protected function init_stream()
    {
        $init_func = "init_stream_". $this->response_type;

        if (method_exists($this, $init_func))
        {
            call_user_func(array($this, $init_func));
        }
    }

    protected function end_stream()
    {
        $init_func = "end_stream_" . $this->response_type;

        if (method_exists($this, $init_func))
        {
            call_user_func(array($this, $init_func));
        }
    }

    protected function cleanup()
    {}

    protected function stream_headers()
    {
        $this->handleStatus();

        // generate the response
        $this->responseCode();
        $this->responseHeaders();
    }

    /**
     * @protected @method stream
     *
     * stream() handles all headers and response code handling.
     *
     * Use this function when the service operation seeks to continuily forward
     * data to the client.
     *
     * Note that as soon as you use the streaming API, you cannot change the
     * the response headers during the operation. Therefore, stream() is best called
     * during the validateOperation() or operation execution.
     */
    protected function stream() {}

    private function prepareRun()
    {

        if ( $this->status == Service::OK)
        {
            $this->initializeRun();
        }

        if ($this->status == Service::OK)
        {
            // code level verification of the API method
            // this function normally sets $this->operation.
            $this->prepareOperation();

            // ensure that options() works even with exotic prepareOperation methods
            if ($this->method == 'OPTIONS')
            {
                $this->operation = 'options';
            }
            else if ($this->status == Service::OK)
            {
                // verify that the operation exists
                $this->checkOperation();

                // ensure protocol level header validation, e.g., for access authorization
                if ($this->status == Service::OK)
                {
                    $this->validateHeader();
                }
            }
        }

        if ($this->status === Service::OK)
        {
            $this->loadData();
        }

        if ($this->status === Service::OK &&
            in_array($this->method, array("PUT", "POST")))
        {
            $this->validateData();
        }

        // after this point the business logic needs to define error messages

        if ($this->status == Service::OK)
        {
            // the application logic level verification whether an API method
            // should be executed or not, e.g. ACL verification

            $oper = $this->operation; // ensure that the operation cannot be overwritten at this point

            $this->validateOperation();

            $this->operation = $oper;
        }
    }

    protected function handleStatus()
    {
        if ($this->status != Service::OK
            && empty($this->response_code))
        {
            /**
             * Before entering the response generation phase, RESTling evaluates the pipeline status and
             * prepares the responses for pipeline errors. Therefore, the application logic will not have
             * to bother about standard error handling, such as bad headers or request URLs.
             *
             * If debugging is enabled, this mechanism will also log any pipeline errors.
             */
            $this->log("service failed in stage " . $this->status);

            switch($this->status)
            {
            case Service::UNINITIALIZED:
                /**
                 * If the service or the request cannot be initialized, RESTling will automatically respond
                 * the 503 Unavailable response to indicate that the service is currently not available.
                 */
                $this->log('setup error!');
                $this->unavailable();
                break;
            case Service::BAD_URI:
                /**
                 * If at any stage the service identifies a bad URI it will always respond 404 Not Found.
                 * This indicates that the requested URL is not available on the server.
                 */
                $this->log('malformed URI detected!');
                $this->not_found();
                break;
            case Service::BAD_DATA:
                /**
                 * If the data object sent in PUT or POST requests is malformed, then RESTling will send
                 * a 400 Bad Request error to the client.
                 */
                $this->log('malformed data detected!');
                $this->bad_request();
                break;
            case Service::BAD_HEADER:
                /**
                 * If the request headers cannot be validated, then RESTling will send
                 * a 412 Precondition Failed response to the client.
                 */
                $this->log('malformed header detected!');
                $this->precondition_failed();
                break;
            case Service::BAD_METHOD:
                /**
                 * If the request method cannot be validated, then RESTling will send
                 * a 405 Method Not Allowed response to the client.
                 *
                 * Note, that RESTling will not extend the headers for this response.
                 */
                $this->log('wrong request method detected!');
                $this->not_allowed();
                break;
            case Service::BAD_OPERATION:
                /**
                 * If the request tries to access an operation that is not implemented by the service,
                 * RESTling will automatically generate a 400 Bad Request response.
                 */
                $this->log("not allowed by RESTling");
                $this->bad_request();
                break;
            case Service::OPERATION_FORBIDDEN:
                /**
                 * If the application logic forbids the access to the requested operation, RESTling will
                 * automatically generate a 403 Forbidden response. This behaviour can be changed for example
                 * by calling authentication_required() during validateOperation().
                 */
                $this->log('access forbidden by application logic');
                if (empty($this->request_code))
                {
                    $this->forbidden();
                }
                break;
            case Service::OPERATION_FAILED;
                $this->log('operation failed');
                // the operation must set the return code.
                break;
            default:
                // case Service::OK
                break;
            }
        }
    }

     /**
     * @method void initializeRun()
     *
     * This function is used to prepare all internal setup that is required BEFORE the
     * request is actually handled. This may include setting up a database handler and other
     * parameters of the process
     *
     * If the internal initialization fails, this function must set the service status to
     * Service::UNINITIALIZED.
     *
     * If the service cannot be initialized, all other steps will be avoided.
     */
    protected function initializeRun()
    {
    	// $this->log("enter intializeRun in RESTling");
    }

    /**
     * @method void loadData()
     *
     * This method is triggered when the service catches a PUT or POST request in order to
     * load structured (non url-encoded) data into the business logic. By default this method
     * expects a JSON string as input. If the data fails to parse as JSON this method sets
     * the status property to Service::BAD_DATA.
     *
     * In case a service expects different data formats of input this method has to be
     * overwridden.
     */
    protected function loadData() {
        if (($this->method === "PUT" ||
             $this->method === "POST") &&
            !isset($this->inputData))
        {
            // ensure that there is always some inputData for POST and PUT requests
            $this->inputData = array();
            $data = file_get_contents("php://input");
            if (isset($data))
            {

                if ($this->keepRawDataFlag)
                {
                    $this->input = $data;
                }
                if (strlen($data))
                {
                    $ct = explode(";", $_SERVER['CONTENT_TYPE']);
                    $ct = $ct[0];
                    $this->inputDataType = $ct;
                    switch ($ct)
                    {
                        case 'application/json':
                            $this->inputData = json_decode($data, true);
                            break;
                        case 'text/plain':
                        case 'text/html':
                            $this->inputData = $data;
                            break;
                        case 'application/x-www-form-urlencoded':
                            // all form data is stored in $_POST
                            if (empty($_POST)) {
                                // populate POST
                                $_POST = array();
                                parse_str($data, $_POST);
                            }
                            $this->inputData = $_POST;
                            break;
                        default;
                            break;
                    }
                }
                else if (!empty($_POST)) {
                    // we endup here in multipart formdata
                    $this->inputDataType = "multipart/form-data";
                    $this->inputData = $_POST;
                }
            }
            else
            {
                $this->status = Service::BAD_DATA;
            }
        }
    }

     /**
      * @method void prepareOperation()
      *
      * This method builds the method name or the service handler and test the
      * logical presence of this handler. If the service class does not implement
      * a method handler for the requested operation this method sets the status property
      * to Service::BAD_OPERATION.
      *
      * prepareOperation() automatically checks for REST protocol functions in
      * the format METHOD + "_" + PATH_INFO. All protocol functions are
      * lower case! Therefore, the request
      *
      * ```HTTP
      * GET /mysservice.php/foo
      * ```
      *
      * translates into the following function
      *
      * ```php
      * $myService->get_foo()
      * ```
      *
      * Note: that path names will remain in the form they are written
      *
      * perpareOperation() tests for most significant match, first. Given the
      * service class implementsthe following functions
      *
      * ```php
      * Class MyService
      *       extends RESTling
      * {
      *      protected function get()
      *      {
      *         $this->data = "call get";
      *      }
      *
      *      protected function get_foo()
      *      {
      *         $this->data = "call for foo";
      *      }
      *
      *      protected function get_foo_bar()
      *      {
      *         $this->data = "call for foo_bar";
      *      }
      * }
      *```
      *
      * And given a request
      *
      * ```HTTP
      * GET /myservice.php/foo/bar/baz
      * ```
      *
      * Will return in the response "call for foo_bar", because there is no handler
      * for the full path_info implemented and the most significant match is foo/bar.
      *
      * The function get_foo() will not get called.
      *
      * Whereas, given a request
      *
      * ```HTTP
      * GET /myservice.php/foo/baz
      * ```
      *
      * will return the response "call for foo", because there is no handler
      * implemented for foo/baz. Therefore, get_foo() is the most significant
      * match.
      *
      * If the service needs no special pathinfo handling, only method handlers
      * are required. Thus, the request
      *
      * ```HTTP
      * GET /myservice.php/bar/foo
      * ```
      *
      * will return the response "call get".
      */
    protected function prepareOperation()
    {
        // $this->operation = "handle_" . $this->method;

        $m = strtolower($this->method);

        $c = $this->path_info;

        $this->operation = $this->findOperation($m, $c);
    }

    protected function findOperation($m, $p)
    {
        return $this->findMostSignificantOpMatch($m,$p);
    }

    protected function findMostSignificantOpMatch($method, $pathinfo)
    {
        $p = array();
        $c = $pathinfo;
        $this->status = Service::BAD_OPERATION;

        while (!empty($c) &&
               $this->status == Service::BAD_OPERATION)
        {
            $op = $method . "_" . implode("_", $c);

            if (!method_exists($this, $op))
            {
                $this->status = Service::BAD_OPERATION;
                array_unshift($p, array_pop($c));
            }
            else
            {
                $this->status = Service::OK;
                $this->path_info = $p;
                return $op;
            }
        }

        // test if there is a plain HTTP verb function
        if ($this->status == Service::BAD_OPERATION &&
            method_exists($this, $method))
        {
            $this->status = Service::OK;
            $this->path_info = $p;
        }
        return $method;
    }

    /**
     * @method void validateOperation()
     *
     * This method provides the application level verification whether an operation should be
     * executed or not. This might be the case because a caller might not have sufficient
     * privileges for running the operation.
     *
     * If a service requires operation verification as part of the business logic it must
     * implement this method.
     *
     * If the operation is forbidden in the context of the given request, then this method
     * must set the status Service::OPERATION_FORBIDDEN.
     */
    protected function validateOperation()
    {}

    /**
     * @method void validateData()
     *
     * This is the last step before the actual operation is called.
     *
     * The method checks if the data is valid for the given operation.
     * By default this method confirms all incoming data. For complex applications
     * the data validation should get checked here, instead of testing during the operation.
     *
     * This function uses any DataValidator
     *
     * If the operation fails it must set the Service::BAD_DATA status.
     */
    protected function validateData()
    {
        $anyOK = true;

        // automatically accept non data methods

        if (!empty($this->dataValidators))
        {
            $anyOK = false;
            foreach ($this->dataValidators as $validator) {
                $validator->setMethod($this->operation);
                $validator->setData($this->inputData, $this->inputDataType);

                $res = $validator->run();

                if (!$res && $validator->mandatory()) {
                    $this->status = Service::BAD_DATA;
                    $this->data = $validator->error();
                    break;
                }

                $anyOK = $res || $anyOK;
            }

            if (!$anyOK) { // ALL NON-MANDATORY VALIDATORS FAILED
                $this->status = Service::BAD_DATA;
            }
        }
    }

    /**
     * private @method void checkOperation()
     *
     * Convinience function so we can implement mode complex protocol level validation.
     *
     * The function will test for the presence of a certain function in the calling class.
     */
    private function checkOperation()
    {
        if (empty($this->operation) || !method_exists($this, $this->operation))
        {
            $this->status = Service::BAD_OPERATION;
        }
    }

    /**
     * @method void validateHeader()
     *
     * Handles the second phase of the run process. At this level you would implement
     * Cookie validation or OAuth. The header validation just confirms the correctness of
     * the header but no ACL or method level validation. This should be done by
     * the validateOperation() method
     *
     * RESTling expects header validator objects that have at least two functions.
     * Ideally, one would subclass the RESTlingValidator class. These validator objects are
     * added to the RESTling class by using the addValidator($validator) method.
     *
     * If the header validation is not successful the validator method should also set the
     * response information that is sent to the client. (by default 401 in RESTlingValidator)
     */
    private function validateHeader()
    {
        $anyOK = true;
        if (!empty($this->headerValidators))
        {
            $anyOK = false;
            foreach ($this->headerValidators as $validator) {
                $validator->setMethod($this->operation);

                $res = $validator->run();

                if (!$res && $validator->mandatory()) {
                    $this->status = Service::BAD_HEADER;
                    $this->data = $validator->error();
                    break;
                }

                $anyOK = $res || $anyOK;
            }

            if (!$anyOK) { // ALL NON MANDATORY VALIDATORS FAILED
                $this->status = Service::BAD_HEADER;
            }
        }
    }

    /**
     * addHeaderValidator($validatorObject)
     *
     * Adds a header Validator. These validators are run during the header validation phase.
     */
    public function addHeaderValidator($validatorObject)
    {
        if (isset($validatorObject))
        {
            $this->headerValidators[] = $validatorObject;
            $validatorObject->setService($this);
        }
    }

    /**
     * addDataValidator($validatorObject)
     *
     * Adds a data Validator. These validators are run during the data validation phase.
     */
    public function addDataValidator($validatorObject)
    {
        if (isset($validatorObject))
        {
            $this->dataValidators[] = $validatorObject;
            $validatorObject->setService($this);
        }
    }

    protected function ignoreHeaderValidationForOperation() {
        return array();
    }

    protected function ignoreDataValidationForOperation() {
        return array();
    }

    /**
     * @method void responseCode()
     *
     * Sets the correct response code for error messages. This is a convenience
     * method for older PHP versions that do not handle this automatically.
     *
     * If a service whishes to use a different response code than 200, then it
     * should set the value to the response_code property.
     */
    protected function responseCode()
    {
        if ( ($this->response_code === 200 || empty($this->response_code)) &&
            ($this->streaming || (!isset($this->streaming) &&
            (!isset($this->data) ||
             (!(is_array($this->data) || is_object($this->data)) &&
              empty($this->data))
             ))) )
        {
            // the status is OK but no data is set by the service, so we respond 204
            // but only if the service did not request to stream data. In this case the
            // data property might be empty at this point.
            $this->respond_code = 204;
        }

        // if the native php function is present we use it!
        if (function_exists('http_response_code'))
        {
            http_response_code($this->response_code);
        }
        else
        {
            $text;
            switch ($this->response_code)
            {
            case 100: $text = 'Continue'; break;
            case 101: $text = 'Switching Protocols'; break;
            case 201: $text = 'Created'; break;
            case 202: $text = 'Accepted'; break;
            case 203: $text = 'Non-Authoritative Information'; break;
            case 204: $text = 'No Content'; break;
            case 205: $text = 'Reset Content'; break;
            case 206: $text = 'Partial Content'; break;
            case 300: $text = 'Multiple Choices'; break;
            case 301: $text = 'Moved Permanently'; break;
            case 302: $text = 'Moved Temporarily'; break;
            case 303: $text = 'See Other'; break;
            case 304: $text = 'Not Modified'; break;
            case 305: $text = 'Use Proxy'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 402: $text = 'Payment Required'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 405: $text = 'Method Not Allowed'; break;
            case 406: $text = 'Not Acceptable'; break;
            case 407: $text = 'Proxy Authentication Required'; break;
            case 408: $text = 'Request Time-out'; break;
            case 409: $text = 'Conflict'; break;
            case 410: $text = 'Gone'; break;
            case 411: $text = 'Length Required'; break;
            case 412: $text = 'Precondition Failed'; break;
            case 413: $text = 'Request Entity Too Large'; break;
            case 414: $text = 'Request-URI Too Long'; break;
            case 415: $text = 'Unsupported Media Type'; break;
            case 429: $text = 'Too Many Requests'; break; // RFC 6585 defined response code for twitter's 420 code
            case 501: $text = 'Not Implemented'; break;
            case 502: $text = 'Bad Gateway'; break;
            case 503: $text = 'Service Unavailable'; break;
            case 504: $text = 'Gateway Time-out'; break;
            case 505: $text = 'HTTP Version not supported'; break;
            default:  break;
            }

            if (!empty($text))
            {
                header('HTTP/1.1 '. $this->response_code . ' '. $text);
            }
        }
    }

    /**
     * @method void CORSHeader($origin, $methods)
     *
     * @param string $origin hostname that is confirmed for CORS requests
     * @param string $methods the list of methods that is confirmed for CORS requests
     *
     * This method is triggered if a service allows CORS requests for the current
     * referrer. This function sets only the Access-Control-Allow-Origin and the
     * Access-Control-Allow-Methods headers. If a service requires additional CORS
     * headers it should override this function.
     */
    protected function CORSHeader($origin, $methods)
    {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . $methods);
    }

    /**
     * @method void responseHeader()
     *
     * Handles the second step of the service response phase. This function contains
     * a bunch of convenience functions for chache control, CORS management, and
     * Internet Explorer functionality.
     *
     * If a service needs specific headers for request methods, it should implement
     * a header_(METHODNAME) method. E.g. if a service needs to respond a special
     * header only for POST requests then it should implement a header_POST() method.
     *
     * This function should be overridden ONLY if the additional headers are
     * required for all HTTP methods. In this case, this method should called first
     * via parent::responseHeader();
     *
     * IMPORTANT NOTE: due to legal regulations regarding cookie handling, it
     * is recommended NOT TO USE cookie headers at all.
     */
    protected function responseHeaders()
    {
        // force IE to obey!
        header('X-UA-Compatible: IE=edge');

        header('content-type: ' . strtolower($this->mapContentType($this->response_type)));

        if ($this->withCORS)
        {
            $origin = '';
            $methods = '';
            if (array_key_exists('*', $this->corsHosts))
            {
                $origin = '*';
                $methods = join(', ', $this->corsHosts['*']);
            }
            elseif (array_key_exists('HTTP_REFERRER', $_SERVER))
            {
                $origin = $_SERVER['HTTP_REFERRER'];
                $methods = join(', ', $this->corsHosts[$_SERVER['HTTP_REFERRER']]);
            }

            if (strlen($origin)){
                $this->CORSHeader($origin, $methods);
            }
        }

        if (!$this->withCaching )
        {
            header('Cache-Control: no-cache');
        }

        // sub classes may implement special header hander for each method.
        if ( method_exists($this, 'header_'.$this->method) )
        {
            call_user_func(array($this, 'header_'.$this->method));
        }
    }

    // if you need
    protected function mapContentType($type)
    {
        $rv = 'content-type: text/plain';
        if (!empty($type)) {
            switch ( strtolower($type) )
            {
            case 'json':
                $rv = 'application/json';
                break;
            case 'form':
                $rv = 'application/x-www-form-urlencoded';
                break;
            case 'text':
                break;
            default:
                break;
            }
        }
        return $rv;
    }

    /**
     * @method void respond()
     *
     * This method will generate the response data for the request.
     *
     * For correctly handled requests it uses the response_type property
     * to determine how to gerenate the output format.
     *
     * By default JSON, Form-encoded and plain text responses are supported.
     * If a service whishes to support other response types, it needs to implement
     * a response_type response method with the name respond_(responsetypename).
     *
     * Handler methods for extended response types need to be written lower case.
     * The handler method is responsible for the content-type header and the
     * data delivery.
     *
     * For error messages this method uses the "respond_with_message" method
     * for automatically determinating what information will be sent to the user.
     */
    protected function respond($data=null)
    {
        if (isset($data))
        {
            $this->data = $data;
        }

        if (isset($this->data))
        {
            if ( $this->status == Service::OK &&
                ($this->response_code == 200 || empty($this->response_code)) )
            {
                $outputfunction = 'text_plain';
                if (!empty($this->response_type)) {
                    switch ( strtolower($this->response_type) )
                    {
                        case 'json':
                            $outputfunction = 'json_data';
                            break;
                        case 'form':
                            $outputfunction = 'form_encoded';
                            break;
                        case 'text':
                            break;
                        default:
                            $outputfunction = strtolower($this->response_type);
                            break;
                    }
                }

                if ( method_exists($this, 'respond_'. $outputfunction) )
                {
                    call_user_func(array($this, 'respond_'. $outputfunction));
                }
            }
            else
            {
                $this->respond_with_message($this->data);
            }
        }

        // empty the data property!
        $this->data = null;
    }

    /**
     * @method void options()
     *
     * Default OPTIONS handler.
     *
     * function for client interaction. Typically used for client interaction, such
     * as CORS negotiations.
     *
     * Some clients seem to refuse 204 responses for OPTIONS requests. Therefore,
     * This function always respondes OK by default.
     *
     * A service class may overload this method if more complex options need to
     * get returned to your clients.
     *
     * If you need to overload the options, you should call this function, too.
     */
    protected function options()
    {
        $this->data = "OK";
    }


    public function getRawInput()
    {
        return $this->input;
    }

    /** **********************************************************************
     * COMMON HTTP RESPONSES
     ********************************************************************** **/

    /**
     * @method respond_json_data()
     *
     * returns the service internal data stash in JSON format. This method sets the Content-type header to "application/json".
     */
    protected function respond_json_data()
    {
        if (isset($this->data))
        {
            if (is_array($this->data) || is_object($this->data))
            {
                echo(json_encode($this->data));
            }
            else if (!empty($this->data))
            {
                echo($this->data);
            }
            else
            {
                $this->no_content();
            }
        }
        else
        {
            $this->no_content();
        }
    }

    /**
     * @method respond_form_encoded()
     *
     * responds the service internal data stash in the form encoded format.
     *
     * NOTE: the data property needs to contain either a urlencoded string or an object.
     * Only object properties with scalar values (strings, booleans, and numbers) are included
     * into the response.
     */
    protected function respond_form_encoded()
    {
        $this->log('respond FORM encoded data');
        $retval = "";
        if (!empty($this->data))
        {
            if (is_object($this->data))
            {
                foreach ($this->data as $k => $v)
                {
                    if (is_scalar($v))
                    {
                        if (!empty($retval))
                        {
                            $retval .= "&";
                        }
                        $retval .= urlencode($k) . '=' . urlencode($v);
                    }
                }
            }
            elseif (is_scalar($this->data))
            {
                $retval = $this->data;
            }

        }
        if (empty($retval))
        {
            $this->no_content();
        }
        else
        {
            echo($retval);
        }
    }

    /**
     * @method void respond_text_plain($message)
     *
     * @param String $message: the message to respond to the client
     *
     * A simple helper function that generates the content-type header for plain text messages.
     * Very convinient for error messages.
     */
    protected function respond_text_plain()
    {
        if (empty($this->data))
        {
            $this->no_content();
        }
        else
        {
            $this->respond_with_message($this->data);
        }
    }

    /**
     * @method no_content()
     *
     * sends the 204 No Content response to the client on successfull operations that do not include data.
     */
    public function no_content()
    {
           $this->log("204 OK but No Content ");
          // newer PHP version would use
          // http_response_code(204);
          // our old server requires
          // header("HTTP/1.1 204 No Content");
           $this->response_code = 204;
           $this->data = "";
    }

    /**
     * @method bad_request([$message])
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * returns the 400 Bad Request error for all call errors (e.g. if a bad item id has been passed).
     */
    public function bad_request($message="")
    {
        $this->log("bad request");
        // newer PHP version would use
        // http_response_code(400);
        $this->response_code = 400;
        $this->data = $message;
    }

    /**
     * @method not_implemented([$message])
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * returns the 501 Not Implemented error for all call errors.
     *
     * this method is handy for prototyping a service API.
     */
    public function not_implemented($message="")
    {
        $this->log("not implemented");
        // newer PHP version would use
        $this->response_code = 501;
        $this->data = $message;
    }

    protected function missing() {
        $this->not_implemented();
    }

    /**
     * @method unavailable([$message])
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * Sends the 503 error message to the client. This method is typically triggered in the
     * service fails with a Service::UNINITIALIZED status.
     */
    public function unavailable($message="")
    {
        $this->log("service unavailable");
        // newer PHP version would use
        $this->response_code = 503;
        $this->data = $message;
    }

    /**
     * @method authentication_required([$message])
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * responds a 401 Authentication Required to show the login screen
     */
    public function authentication_required($message="")
    {
           $this->log("401 Authentication required ");
          // newer PHP version would use
          // http_response_code(204);
          // our old server requires
           $this->response_code = 401;
           $this->data = $message;
    }

    /**
     * @method forbidden($message)
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * returns 403 errors. This should be used if the user is not allowed to
     * access a function or resource
     */
    public function forbidden($message="")
    {
           $this->log("forbidden");
           $this->response_code = 403;
           $this->data = $message;
    }

    /**
     * @method not_found($message)
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * Responds the 404 Not Found code to the client. Used to indicate that one or more requested
     * resources are not available to the system.
     */
    public function not_found($message="")
    {
           $this->log("Item not found");

           $this->response_code = 404;
           $this->data = $message;
    }

     /**
     * @method not_allowed([$message])
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * This method generates the 405 Not Allowed HTTP code. This method is used
     * to indicate that the service has been called with a forbidden HTTP method by the
     * run() method.
     */
    public function not_allowed($message="")
    {
        $this->log("not allowed in RESTling");
        // newer PHP version would use
        // http_response_code(405);
        // our old server requires
        $this->response_code = 405;
        $this->data = $message;
    }

    /**
     * @method gone([$message])
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * returns the 410 Gone response. Used to indicate successfull DELETE operations.
     */
    public function gone($message="")
    {
           $this->log("requested object is gone!");
          // newer PHP version would use
          // http_response_code(410);
          // our old server requires
          $this->response_code = 410;
          $this->data = $message;
    }

    /**
     * @method precondition_failed([$message])
     *
     * This function should be used in case of errors during validateHeader().
     *
     * Typically this function is automatically called if the service $status is set to
     * Service::BAD_HEADER.
     */
    public function precondition_failed($message="")
    {
        $this->response_code = 412;
        if (empty($this->data))
        {
            $this->data = $message;
        }
    }

    /**
     * PRIVATE HELPER METHODS
     **/

    /**
     * @method respond_with_message($message)
     *
     * @param misc $message: the response message object
     *
     * Helper method for the error responses. It allows to pass a string or a complex datastructure
     * to inform the caller about the problem that caused the message.
     *
     * This method will automatically change the content-type of the message depending on the
     * complexity of the data structure. For scalars (strings, numbers etc.) It will use text/plain.
     *
     * For arrays or objects this method will respond a application/json typed object. This allows
     * to respond complex and machine readable information to the client.
     */
    private function respond_with_message($message)
    {
        if (!empty($message))
        {
            if (is_scalar($message))
            {
                echo($message);
            }
            elseif (is_array($message) ||
                    is_object($message))
            {
                echo(json_encode($message));
            }
        }
    }
}

?>
