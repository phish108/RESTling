<?php

/**
 * @class OAUTHRESTService
 *
 * This class clusters some generic functions that are common for all services that manage
 * access only information.
 */
class OAuthService extends RESTling
{
    /**
     * @property $dbh: The global database handler (protected)
     */
    protected $dbh;

    /**
     * @property $session: The OAuth session management class.
     */
    protected $session;

    /**
     * @property $OAuthOmitCheck: boolean flag wheter or not the OAuth Access verification should
     * be performed.
     */

    /**
     * @method __construct($dbh)
     *
     * @param $dbh: An active database handler
     *
     * Initializes the OAuth session management of the service.
     */
    public function __construct($dbh)
    {
        parent::__construct();
        $this->dbh = $dbh;
    }

    /**
     * initializeRun()
     *
     * set up the session management
     */
    protected function initializeRun()
    {
        parent::initializeRun();
        if ($this->status === RESTling::OK &&
            $this->dbh)
        {
            $this->session = new SessionManagement($this->dbh);
        }
        else
        {
            $this->status = RESTling::UNINITIALIZED;
        }
    }

    /**
     * @method prepareOperation($meth)
     *
     * This runs the OAuth Access Token verification before the Service method is called.
     * If the OAuthOmitCheck is TRUE, then NO check is performed and the service proceeds with
     * calling the method handler.
     *
     * If the OAuth Token cannot get verified the service should not call the method handler.
     * In this case this function returns FALSE, which means that the user is not authenticated.
     */
    protected function validateHeader()
    {
        $this->mark();

        $this->session->validateAccessToken();
        if (!$this->session->accessVerified())
        {
            $this->status = RESTling::BAD_HEADER;
            $this->authentication_required();
        }
    }

    /**
     * @method void CORSHeader($origin, $method)
     *
     * @param $origin: the origin that is currently handled ('*' or the current hostname)
     * @param $method: the string of accepted methods
     *
     * This function adds the authorization header to the CORS OPTIONS response, so OAuth works
     * across different domains.
     */
    protected function CORSHeader($o,$m)
    {
        parent::CORSHeader($o,$m);
        header('Access-Control-Allow-Headers: Authorization');
    }
}

?>