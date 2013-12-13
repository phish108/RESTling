<?php

/**
 * @class: RESTling
 *
 * This is a basic service class for REST services. This class provides the basic logic for all our Web-services.
 *
 * Instances are called as following:
 *
 *    $service = new MyServiceClass();
 *    // do some additional initialization if needed
 *    $service->run();
 *
 * An implementation of this class requires to implement a handler function for each method that is supported
 * of the service of the following format:
 *
 *    protected function handler_METHOD(){}
 *
 * In practice this means that if your service supports a GET and a PUT request you need to implement the
 * following functions:
 *
 *    protected function handler_GET(){}
 *    protected function handler_PUT(){}
 *
 * Furthermore this class implements some basic response codes.
 */
class RESTling extends Logger
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

    protected $response_code;
    protected $response_type;
    
    /**
     * @property $data: Internal Service Data Stash.
     *
     * This is used to generate the service response.
     *
     * You can set this property directly or by using either of the setData() or setDataStash() functions.
     *
     * This property can hold arbitary data and the response_*() function will choose how to send it to the
     * client.
     */
    protected $data;
    
    protected $uri;
    protected $bURIOK = true; // helper variable for identifying valid service calls
    protected $path_info;
    protected $status;
    protected $method;

    protected $withCORS = true;
    protected $withCaching = false;
    protected $corsHosts;

    protected $action;

    protected config;
    protected config_file = 'config.ini'; // the config file default should be overridden by the actual service constructor
    
    public function __construct()
    {
        $this->mark( "********** NEW SERVICE REQUEST ***********");
        $this->corsHosts = array();
    }

    /**
     * Desctructor()
     *
     * Sets the end marker after the service is completed. It serves only debugging purposes.
     */
    public function __destruct()
    {
        $this->mark( "********** END SERVICE REQUEST ************");
    }


    /**
     * CORS management functions
     *
     * RESTService implements basic CORS functions.
     *
     * @method void allowCORS()
     * @method void forbidCORS()
     * @method void addCORSHost($host, $methods)
     *
     * @param mixed $host: hostname string or array of hostnames
     * @param mixed $methods: method string or array of methods
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
     * CORS operations should be initialized during the intializeRun phase.
     */
    public function allowCORS()
    {
        $this->withCORS = true;
    }

    public function forbidCORS()
    {
        $this->withCORS = false;
    }

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
     * The main power horse of the service. This function decides which handler methods should be
     * called for the different HTTP request methods.
     *
     * For unsupported HTTP request methods the service responds with a 405 Not Allowed code.
     *
     * The run process has 5 phases
     *
     *     0. internal run initialization (including loading of external configuration files
     *     1. URI validation
     *     2. Method validation
     *     3. Header validation
     *     4. Method handling
     *     5. Response generation
     *
     * Phase 1-4 are sequential based on the success of the previous operation.
     * Phase 5 is always executed and has 3 sub-steps
     *
     *     5.1. HTTP Response Code generation
     *     5.2. HTTP Header generation
     *     5.3. Response data generation
     *
     * The phases allow to organize your code logically as a process. This allows
     * you to focus on the business logic at hand. 
     *
     * The internal run initialization is used to setup everything that is independent
     * to the service request, such as checking the validity of of the data base or testing
     * for initialization errors when a class is not loaded or a global property is not
     * initialized.
     * 
     * The URI validation checks if the service is called for an accepted URI. This also takes
     * over the path_info extraction, so you can switch your service into different modes.
     *
     * The method validation phase tests if the method should be accepted for the request
     * URI. This phase decides which handler function should be called. The method validation
     * is typically responsible for detecting protocol level errors. 
     *
     * The header validation analyzes the request headers. This phase is typically responsible
     * for session management.
     *
     * The method handling calls the handler function for the request method that has been
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
     *     - plain text
     *     - url-form-encoded
     *     - JSON
     *
     * The preferred data type is determined by the response_type property.
     * If a service needs to support other data types the responseData()
     * method has to be overridden.
     *
     * This function handles the response code handling if the URI and method
     * validation fail. In these cases the service will always respond with a
     * 405 error.
     */
    public function run()
    {
    	$this->log("enter run in RESTling");
    	
        // split the process into phases
        $this->status = RESTling::OK;

        $this->loadConfiguration();
        
        if ( $this->status == RESTling::OK)	
        {
            $this->initializeRun();
        }

        if ($this->status == RESTling::OK)
        {
            $this->validateHeader();
        }
        
        if ( $this->status == RESTling::OK)	
        {
            $this->validateURI();
        }

        if ($this->status == RESTling::OK)
        {
            $this->validateMethod();
        }
             
        if ($this->status == RESTling::OK)
        {
            // code level verification of the API method
            $this->prepareOperation();
        }
        
        // after this point the business logic needs to define error messages

        if ($this->status == RESTling::OK)
        {
            // the application logic level verification whether an API method should be executed or not
            // e.g. ACL verification
            $this->verifyOperation();
        }

        if ($this->status == RESTling::OK)
        {
            if(method_exists($this, $this->action))
            {
                call_user_func(array($this, $this->action)) ;
            }
            else
            {
            	$this->log("method does not exist and status gets a bad method");
                $this->status = RESTling::BAD_METHOD;
            }
        }
        		
        if ($this->status != RESTling::OK &&
            $this->status <= RESTling::BAD_METHOD)
        {
        	$this->log("not allowed in run RESTling");
            $this->not_allowed();
        }

        // generate the response
        $this->responseCode();
        $this->responseHeaders();
        $this->responseData();
    }

    /**
     * @method void loadConfiguration()
     * 
     * This function loads the service configuration from the file that is defined under
     * $this->config_file. The function checks for the existance of the file and tries to 
     * load it using parse_ini_file(). 
     *
     * This function expects your configuration file to be in the php .ini format.
     */
    protected function loadConfiguration() {
        // TODO: config_file may contain an array with alternative paths
        // the function should check if the either one of the alternative paths exists.
        // if one of them exists it should try to read them 
        // if none exists it should just move on
        
        if (!empty($this->config_file) && file_exists($this->config_file)) {
            $cfg = parse_ini_file($this->config_file, true); // try catch?
            if (empty($cfg)) {
                $this->status = RESTling::UNINITIALIZED;
            }
            else {
                $this->log("configuration successfully loaded");
                $this->config = $cfg;                
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
     * RESTService::UNINITIALIZED.
     *
     * If the service cannot be initialized, all other steps will be avoided.
     */
    protected function initializeRun()
    {
    	$this->log("enter intializeRun in RESTling");
    }

    /**
     * @method void validateURI()
     *
     * Handles the first phase of the run process. This method determines if
     * the service is correctly called and extracts the path_info value correctly
     * for further processing (PHP's native PATH_INFO property gets confused from
     * time to time).
     *
     * If the URI cannot be validated correctly, this method has to set the
     * status property to RESTling::BAD_URI in order to avoid further processing.
     */
    protected function validateURI()
    {	
    	$this->log('enter validateURI');
        $uri = $_SERVER['REQUEST_URI'];
        // decides whether or not to run the service
        if (!empty($this->uri) &&
            strncmp($uri, $this->uri, strlen($this->uri)) !== 0)
        {
            // we test the URI only if the service has the URI set
            $this->log('invalid URI');
            $this->status = RESTling::BAD_URI;
        }
        else {
            $this->log('strip the uri');
            // now strip the pathinfo (if the URI is set)
            if (!empty($this->uri))
            {
                $this->log('valid URI');
                $ruri = substr($uri, strlen($this->uri));
                // remove any leading or trailing slashes
                $ruri = preg_replace('/^\/*|\/*$/', '', $ruri);
                $ruri = preg_replace('/\?.*$/', '', $ruri);
                $this->path_info = $ruri;
            }
        }
    }

    /**
     * @method void validateMethod()
     *
     * Handles the second phase of the run process. This method tests whether the 
     * requested method is allowed. If the service class does not implement
     * a method handler for the HTTP operation this method sets the status property
     * to RESTService::BAD_METHOD.
     */
    protected function validateMethod()
    {
        $meth = $_SERVER['REQUEST_METHOD'];
        $this->method = $meth;
        $cmeth = "handle_" . $meth;
    }
    
     /**
      * @method void prepareOperation()
      *
      * This method builds the method name or the service handler and test the 
      * logical presence of this handler. If the service class does not implement
      * a method handler for the requested operation this method sets the status property
      * to RESTling::BAD_OPERATION.
      */
    protected function prepareOperation() {
        $cmeth = "handle_" . $this->method;
        $this->checkMethod($cmeth);
    }
    
    /**
     * @method void verifyOperation() 
     *
     * This method provides the application level verification whether an operation should be
     * executed or not. This might be the case because a caller might not have sufficient 
     * privileges for running the operation.
     * 
     * If a service requires operation verification as part of the business logic it must
     * implement this method.
     * 
     * If the operation is forbidden in the context of the given request, then this method
     * must set the status RESTling::OPERATION_FORBIDDEN.
     */
    protected function verifyOperation() 
    {}
    
    /**
     * @method void checkMethod($methodName)
     *
     * @param String $methodName: the name of a method in this class.
     *
     * Convinience function so we can implement mode complex protocol level validation.
     *
     * The function will test for the presence of a certain function in the calling class.
     *
     * If the function is present, then it passes the name to the method handling phase,
     * so it can be called there. 
     */
    protected function checkMethod($methodName)
    {
        if (method_exists($this, $methodName))
        {
            $this->action = $methodName;
        }
        else
        {
            $this->status = RESTling::BAD_METHOD;
        }
    }

    /**
     * @method void validateHeader()
     *
     * Handles the third phase of the run process. A sub-class should use this method
     * for session management or other header related tasks.
     *
     * If the header is not correctly validated for running the requested operation,
     * this class needs to set the status property to RESTService::BAD_HEADER.
     *
     * If the header validation is incusscessful this method should also set the
     * response information that is sent to the client.
     */
    protected function validateHeader()
    {}

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
        if ( empty($this->data) &&
            ($this->response_code === 200 || empty($this->response_code)) )
        {
            // the status is OK but no data is set by the service, so we respond 204
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
            case 414: $text = 'Request-URI Too Large'; break;
            case 415: $text = 'Unsupported Media Type'; break;
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
     * @property string $origin: hostname that is confirmed for CORS requests
     * @property string $methods: the list of methods that is confirmed for CORS requests
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

        if ($this->withCORS)
        {
            $origin = '';
            $methods = '';
            if (array_key_exists('*', $this->corsHosts))
            {
                $origin = '*';
                $methods = join(', ', $this->corsHosts['*']);
            }
            elseif (array_key_exists($_SERVER['HTTP_REFERRER']))
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

    /**
     * @method void responseData()
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
     * for automatically determinating what information if sent to the user.
     */
    protected function responseData()
    {
        if (!empty($this->data))
        {
            if ( $this->status === RESTService::OK &&
                ($this->response_code === 200 || empty($this->response_code)) )
            {
                $outputfunction = 'text_message';
                switch ( $this->response_type )
                {
                    case 'JSON':
                    case 'json':
                        $outputfunction = 'json_data';
                        $this->respond_json_data();
                        break;
                    case 'FORM':
                    case 'form':
                        $outputfunction = 'form_encoded';
                        break;
                    case 'TEXT':
                    case 'text':
                        break;
                    default:
                        $outputfunction = 'respond_'.strtolower($this->response_type);
                        break;
                }
                if ( method_exists($this, $outputfunction) )
                {
                    call_user_func(array($this, $outputfunction));
                }
            }
            else
            {
                $this->respond_with_message($this->data);
            }
        }
    }

    /**
     * @method void handle_OPTIONS()
     *
     * function for client interaction. Typically used for client interaction, such
     * as CORS negotiations.
     *
     * Some clients seem to refuse 204 responses for OPTIONS requests. Therefore,
     * This function always respondes OK by default.
     */
    protected function handle_OPTIONS()
    {
        $this->data = "OK";
    }

    /**
     * @method service_uri($uri)
     *
     * @param $uri: the local portion of the Base URI of the service.
     *
     * This function is a helper function to override the base URI from an external configuration.
     */
    public function service_uri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * @method setData($data)
     *
     * @param $data: data object to be returned as a response of the request
     *
     * This function is a convenience function to setting the response data of the service.
     *
     * The normal way to do this is to set the dataobject directly.
     */
    protected function setData($data)
    {
        $this->data = $data;
    }


    /**
     * @method setDataStash($data)
     *
     * @param $data: data object to be returned as a response of the request
     *
     * This function is a convenience function to setting the response data of the service.
     *
     * The normal way to do this is to set the dataobject directly.
     */
    protected function setDataStash($data)
    {
        $this->data = $data;
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
        $this->log('respond JSON data');
        header('content-type: application/json');
        if ( !empty($this->data))
        {
            if (is_array($this->data) || is_object($this->data))
            {
                $this->log('json encode data');
                echo(json_encode($this->data));
            }
            else
            {
                $this->log('just echo data');
                echo($this->data);
            }
        }
        else
        {
            $this->log('no content');
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
        header('content-type: application/x-www-form-urlencoded');
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
     * @method void respond_text_message($message)
     *
     * @param String $message: the message to respond to the client
     *
     * A simple helper function that generates the content-type header for plain text messages.
     * Very convinient for error messages.
     */
    protected function respond_text_message($message)
    {
        header('content-type: text/plain');
        echo($message);
    }

    /**
     * @method no_content()
     *
     * sends the 204 No Content response to the client on successfull operations that do not include data.
     */
    protected function no_content()
    {
           $this->log("204 OK but No Content ");
          // newer PHP version would use
          // http_response_code(204);
          // our old server requires
          header("HTTP/1.1 204 No Content");
           $this->response_code = 204;
           $this->data = "";
    }

    /**
     * @method bad_request($message)
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * returns the 400 Bad Request error for all call errors (e.g. if a bad item id has been passed).
     */
    protected function bad_request($message="")
    {
        $this->log("bad request");
        // newer PHP version would use
        // http_response_code(400);
        $this->response_code = 400;
        $this->data = $message;
    }
    
    /**
     * @method not_implemented($message)
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * returns the 501 Not Implemented error for all call errors.
     *
     * this method is handy for prototyping a service API. 
     */
    protected function not_implemented($message="")
    {
        $this->log("not implemented");
        // newer PHP version would use
        $this->response_code = 501;
        $this->data = $message;
    }
    
    protected function missing() {
        this->not_implemented();   
    }
    
    /**
     * @method authentication_required($message)
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * responds a 401 Authentication Required to show the login screen
     */
    protected function authentication_required($message="")
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
     * returns 403 errors. This should be used if the user is not allowed to access
     * a function or resource
     */
    protected function forbidden($message="")
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
    protected function not_found($message="")
    {
           $this->log("Item not found");

           $this->response_code = 404;
           $this->data = $message;
    }

     /**
     * @method not_allowed($message)
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * This method generates the 405 Not Allowed HTTP code. This method is used
     * to indicate that the service has been called with a forbidden HTTP method by the
     * run() method.
     */
    protected function not_allowed($message="")
    {
        $this->log("not allowed in RESTling");
        // newer PHP version would use
        // http_response_code(405);
        // our old server requires
        $this->response_code = 405;
        $this->data = $message;
    }

    /**
     * @method gone($message)
     *
     * @param misc $message (optional) extra message to be send to the client
     *
     * returns the 410 Gone response. Used to indicate successfull DELETE operations.
     */
    protected function gone($message="")
    {
           $this->log("requested object is gone!");
          // newer PHP version would use
          // http_response_code(410);
          // our old server requires
          $this->response_code = 410;
          $this->data = $message;
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
                header('content-type: text/plain');
                echo($message);
            }
            elseif (is_array($message) ||
                    is_object($message))
            {
                header('content-type: application/json');
                echo(json_encode($message));
            }
        }
    }
}

?>
