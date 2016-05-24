<?php
namespace RESTling;

/**
 * OAuthSession is responsible for all OAuth management and verification tasks.
 *
 * all access token management happens here!
 *
 * This class has three main functions
 * 1. validateConsumerToken()
 * 2. validateRequestToken()
 * 3. validateAccessToken()
 * 4. verifyRequestValidation()
 *
 * if also contains a small function of invalidating access keys
 */
class OAuthSession extends Validator
{
    const TIMEOUT_DELTA = 86400;

    protected $dbh; // data based handler
    protected $oauth;

    protected $userID;

    protected $consumerKey;
    protected $consumerSecret;
    protected $consumerID;
    protected $consumerVerificationMode = 'auto'; // auto, user-authorized

    protected $requestToken;
    protected $requestTokenSecret;
    protected $requestTokenID;

    protected $accessToken;
    protected $accessTokenSecret;
    protected $accessTokenID;

    protected $verificationCode;
    protected $oauthState;

    protected $validationMode = 'use';

    public function __construct($dbh)
    {
        $this->setDebugMode(true);
        $this->mark();
        $this->dbh = $dbh;

        $this->oauth = new OAuthProvider();
        $this->oauth->setParam('_', NULL);
        $this->oauthState = OAUTH_OK;
    }

    /**
     * pre service invocation
     *
     * RESTling calls the validate function for all
     * header validators;
     */
    public function validate()
    {
        $this->mark();
        switch ($this->validationMode) {
            case 'invalidate':
                // active tokens will be invalidated (deleted)
                break;
            case 'register':
                // request a new consumer token
                break;
            case 'request':
                // get a new request token requires a consumer token
                $this->validateConsumerToken();
                break;
            case 'authorize':
                // everything before access can be verified
                $this->validateRequestToken();
                break;
            case 'access':
                // send verification code to obtain an access token
                $this->session->verifyRequestToken();
                break;
            case 'use':
            default:
                //  normal access
                $this->validateAccessToken();
                break;
        }
        return ($this->getOAuthState() === OAUTH_OK);
    }

    /**
     * ACCESSORS
     */

    public function getOAuthState()
    {
        return $this->oauthState;
    }

    public function getRequestToken()
    {
        if (!empty($this->requestToken) &&
            !empty($this->requestTokenSecret))
        {
            return array('oauth_token'        => $this->requestToken,
                         'oauth_token_secret' => $this->requestTokenSecret);
        }
        return array();
    }

    public function getAccessToken()
    {
        if (!empty($this->accessToken) &&
            !empty($this->accessTokenSecret))
        {
            return array('oauth_token'        => $this->accessToken,
                         'oauth_token_secret' => $this->accessTokenSecret);
        }
        return array();
    }

    public function getVerificationCode()
    {
        if (!empty($this->requestToken) &&
            !empty($this->verificationCode))
        {
            return array('oauth_verifier' => $this->verificationCode);
        }
        return array();
    }

    public function getUserID()
    {
        return $this->userID;
    }

    public function setUserID($userid)
    {
        $this->userID = $userid;
    }

    public function getConsumerVerificationMode()
    {
        return $this->consumerVerificationMode;
    }

    /**
     * @method bool requestVerified()
     *
     * This function checks if the OAuth data from the client has been verified to be a valid requestToken
     * for a user.
     */
    public function requestVerified()
    {
        $this->log("user id is " . $this->userID);

        // debugging code
        $this->logtest(($this->oauthState == OAUTH_OK),   "oauthstate is OK");
        $this->logtest(!empty($this->requestToken),       "request token ok");
        $this->logtest(!empty($this->requestTokenSecret), "request token secret ok");
        $this->logtest($this->userID,                     "user id is OK");

        // the actual logic
        if ( $this->oauthState == OAUTH_OK &&
            !empty($this->requestToken) &&
            !empty($this->requestTokenSecret) &&
            $this->userID)
        {
            $this->log("request verified");
            return true;
        }

        return false;
    }

    /**
     * @method void accessVerified()
     *
     * This function checks if the OAuth data from the client has been verified to be a valid accessToken.
     */
    public function accessVerified()
    {
        $this->log("user id is " . $this->userID);

        if ($this->oauthState === OAUTH_OK &&
            !empty($this->accessToken) &&
            !empty($this->accessTokenSecret) &&
            isset($this->userID) &&
            $this->userID > 0)
        {
            $this->log("access verified");
            return true;
        }
        return false;
    }

    /**
     * @method void verifyUser($email, $credentialhash)
     *
     * @param string $email the user's email
     * @param string $credentials the users secured password.
     *
     * This method expects the users email and an encrypted password.
     *
     * The credentialhash is a double encrypted password. The first encryption is used for matching the
     * storage format of the password. Each password is stored in a hash based on the actual password and
     * the user's email address in SHA1 format
     *
     * The hash is generated by the following line of code:
     *
     *     $pwHash = sha1($email . $clearPassword);
     *
     * The credentialhash uses the $pwHash and encrypts it with session specific parameters by using the
     * session secrets using the following code:
     *
     *     $credentialhash = sha1($requestTokenSecret . $consumerSecret . $pwHash);
     *
     * Please note, that both email and password MUST NOT contain any leading or trailing whitespace
     * characters when they are passed into the hashing function!
     */
    public function verifyUser($usermail, $credentialhash)
    {
        $this->mark('>>>>>>> LOGIN');

        // we should use a user class for this handling
        // $this->user->load($usermail);
        // $userid = $this->user->id();
        // $pwHash = $this->user->pwhash();

        $sth= $this->dbh->prepare("select id, password from users where email = ?");
        $res = $sth->execute($usermail);
        if (!PEAR::isError($res))
        {
            if ($res->numRows())
            {
                $row = $res->fetchRow();
                $userid = $row[0];
                $pwHash = $row[1];

                $verificationhash = sha1($this->requestTokenSecret . $this->consumerSecret . $pwHash);
                $this->log('local hash '. $verificationhash);
                $this->log('remote hash' . $credentialhash);

                if ($verificationhash == $credentialhash)
                {
                    $this->log('YEY The user is authenticated!!');
                    $this->userID = $userid;
                    $this->log("user id is " . $this->userID);
                }
                else
                {
                    $this->log('strings do not match');
                }
            }
        }
        $sth->free();
    }


    /**
     * SERVICE FUNCTIONS
     */

    public function validateConsumerToken()
    {
        $oauth = $this->oauth;
        try
        {
            // the following line informs $oauth whether or not a token is part of the signature
            // true informs $oauth not to run the token validation
            $oauth->isRequestTokenEndpoint(true);

            // initialize the verification handler
            $oauth->consumerHandler(array($this,'lookupConsumer'));
            $oauth->timestampNonceHandler(array($this,'timestampNonceChecker'));

            $oauth->checkOAuthRequest();
        }
        catch(OAuthException $E)
        {
            $this->log(OAuthProvider::reportProblem($E));
            $this->oauthState = OAUTH_INVALID_SIGNATURE;
        }
    }

    public function validateRequestToken($params = array())
    {
        $oauth = $this->oauth;
        try
        {

            // you should add the parameters, but somehow this creates segfaults
            //if (!empty($params)) {
            //   $this->log("got parameters");
            //   foreach($params as $k => $v) {
            //        $oauth->addRequiredParameter($k);
            //        $oauth->setParam($k, $v);
            //   }
            //}
            // with a request token we can run the token validation
            $oauth->isRequestTokenEndpoint(false);

            // initialize the verification handler
            $oauth->consumerHandler(array($this,'lookupConsumer'));
            $oauth->timestampNonceHandler(array($this,'timestampNonceChecker'));
            $oauth->tokenHandler(array($this, 'lookupRequestToken'));

            $oauth->checkOAuthRequest();
        }
        catch(OAuthException $E)
        {
            $this->log(OAuthProvider::reportProblem($E));
            $this->oauthState = OAUTH_INVALID_SIGNATURE;
        }
    }

    public function validateAccessToken()
    {
        $oauth = $this->oauth;
        try
        {
            // with a request token we can run the token validation
            $oauth->isRequestTokenEndpoint(false);

            // initialize the verification handler
            $oauth->consumerHandler(array($this,'lookupConsumer'));
            $oauth->timestampNonceHandler(array($this,'timestampNonceChecker'));
            $oauth->tokenHandler(array($this, 'lookupAccessToken'));

            $oauth->checkOAuthRequest();
        }
        catch(OAuthException $E)
        {
            $this->log('cannot run the token validation without a request token');
            $this->log(OAuthProvider::reportProblem($E));
            $this->oauthState = OAUTH_INVALID_SIGNATURE;
        }
    }

    /**
     * verifyRequestToken();
     */
    public function verifyRequestToken()
    {
        $oauth = $this->oauth;
        try
        {
            // with a request token we can run the token validation
            $oauth->isRequestTokenEndpoint(false);

            // initialize the verification handler
            $oauth->consumerHandler(array($this,'lookupConsumer'));
            $oauth->timestampNonceHandler(array($this,'timestampNonceChecker'));
            $oauth->tokenHandler(array($this, 'lookupRequestTokenVerification'));

            $oauth->checkOAuthRequest();
        }
        catch(OAuthException $E)
        {
            $this->log(OAuthProvider::reportProblem($E));
            $this->oauthState = OAUTH_INVALID_SIGNATURE;
        }
    }

    /**
     * invalidateAccessToken()
     *
     * Clears the access token from the backend. This function is called during logout.
     */
    public function invalidateAccessToken()
    {
        if (!empty($this->accessTokenID))
        {
            $this->clearAccessTokenAndNonces($this->accessTokenID);
        }
    }

    /**
     * invalidateRequestToken()
     *
     * This method is called to remove the request token after the consumer loaded the access token
     */
    public function invalidateRequestToken()
    {
        if ($this->oauthState == OAUTH_OK)
        {
            $this->clearRequestTokenAndNonces($this->requestTokenID);
        }
    }

    /**
     * TOKEN GENERATORS
     */

    public function generateRequestToken()
    {
        // this returns the requestToken and its secret
        $this->requestToken       = bin2hex($this->oauth->generateToken(4, true));
        $this->requestTokenSecret = bin2hex($this->oauth->generateToken(12, true));

        // we want to store the two items into the data base.
        $sth = $this->dbh->prepare("INSERT INTO requesttokens (consumer_key, request_token, request_token_secret) " .
                                   "VALUES (?,?,?)");
        $res = $sth->execute(array($this->consumerKey,
                                   $this->requestToken,
                                   $this->requestTokenSecret));
        if( PEAR::isError($res) )
        {
            $this->log("database error " . $res->getMessage());
        }
        else
        {
            $this->log("successfully inserted the requesttoken");
            $this->requestTokenID = $this->dbh->lastInsertID('requesttokens','id');
        }

        $sth->free();
    }

    public function generateAccessToken()
    {
        $this->accessToken       = bin2hex($this->oauth->generateToken(4, true));
        $this->accessTokenSecret = bin2hex($this->oauth->generateToken(12, true));

        // we want to store the two items into the data base.
        $sth = $this->dbh->prepare("INSERT INTO accesstokens (consumer_key, access_token, access_token_secret, user_id) " .
                                   "VALUES (?,?,?,?)");
        $res = $sth->execute(array($this->consumerKey,
                                   $this->accessToken,
                                   $this->accessTokenSecret,
                                   $this->userID));
        if( PEAR::isError($res) )
        {
            $this->log("database error " . $res->getMessage());
        }
        else
        {
            $this->log("successfully inserted the requesttoken");
            $this->accessTokenID = $this->dbh->lastInsertID('accesstokens','id');
        }

        $sth->free();
    }

    public function generateVerificationCode()
    {
        if ($this->requestTokenID > 0 && $this->userID > 0)
        {
            $this->verificationCode = bin2hex($this->oauth->generateToken(8, true));
            $sth = $this->dbh->prepare('UPDATE requesttokens SET authorised_user_id = ?, verification =? WHERE id = ?');
            $res = $sth->execute(array($this->userID,
                                       $this->verificationCode,
                                       $this->requestTokenID));
            if (PEAR::isError($res))
            {
                $this->log("DB ERROR: " .  $res->getMessage());
            }
            $sth->free();
        }
    }

    /**
     * CALLBACKS
     */

    public function timestampNonceChecker($provider)
    {
        $this->mark();

        $nonce = $provider->nonce;
        $timestamp = $provider->timestamp;

        // nonce verification
        // during request token requests we ignore the nonce
        if ( !(empty($this->accessToken) &&
               empty($this->requestToken)))
        {

            $this->log("NONCE: " . $nonce);

            // we check if the nonce has been used already in the same session
            if ($this->accessTokenID > 0)
            {
                $sth = $this->dbh->prepare("SELECT nonce FROM nonce_list WHERE consumer_id =? AND access_id = ? AND nonce = ?");
                $res = $sth->execute(array($this->consumerID,
                                           $this->accessTokenID,
                                           $nonce));
            }
            else if ($this->requestTokenID)
            {
                $sth = $this->dbh->prepare("SELECT nonce FROM nonce_list WHERE consumer_id =? AND request_id = ? AND nonce =?");
                $res = $sth->execute(array($this->consumerID,
                                           $this->requestTokenID,
                                           $nonce));
            }

            if ($res && !PEAR::isError($res))
            {
                if ($res->numRows() > 0)
                {
                    $this->log("Client tries to reuse the NONCE! ");
                    $sth->free();
                    $this->oauthState = OAUTH_BAD_NONCE;
                    return $this->oauthState;
                }
            }
            else
            {
                $this->log('DB error '. $res->getMessage());
            }

            if ($sth)
            {
                $sth->free();
            }
        }

        // check the timestam
        $delta0 = time() - $timestamp;
        $delta = abs($delta0);
        if ($delta > self::TIMEOUT_DELTA)
        {
            if ($delta0 <= 0)
            {
                $this->log("user comes from the future?");
                // this could be due to timezone differences.
            }
            $this->log("user is outside the dateline");
            $this->oauthState= OAUTH_BAD_TIMESTAMP;
            return $this->oauthState;
        }

        // no errors so we store the nonce into the nonce_list
        if ( !(empty($this->accessToken) &&
               empty($this->requestToken)))
        {
            $sth = $this->dbh->prepare("INSERT INTO nonce_list (nonce, consumer_id, access_id, request_id) VALUES (?,?,?,?)");
            $res = $sth->execute(array($nonce,
                                       $this->consumerID,
                                       $this->accessTokenID,
                                       $this->requestTokenID));
            if ( PEAR::isError($res))
            {
                $this->log('DB ERROR: '. $res->getMessage());
            }
            $sth->free();
        }

        $this->log('NONCE and Timestamp are OK');
        $this->oauthState = OAUTH_OK;
        return $this->oauthState;
    }

    public function lookupConsumer($provider)
    {
        $this->mark();
        $this->oauthState = OAUTH_OK;

        $this->log("look up consumer in our database");

        if ($this->consumerKey != $provider->consumer_key ||
            empty($this->consumerSecret) )
        {
            $this->log('missing secret try to load it from the DB');
            $this->oauthState = $this->initConsumerKey($provider->consumer_key);
        }

        if ( $this->oauthState == OAUTH_OK )
        {
            $this->log('Consumer Key and Secret found');
            $this->log("Consumer Secret: " . $this->consumerSecret);
            $provider->consumer_secret = $this->consumerSecret;
        }

        return $this->oauthState;
    }

    public function lookupRequestToken($provider)
    {
        $this->mark();
        $this->oauthState = OAUTH_OK;

        $this->log("look up the request in our database");

        if ($this->requestToken != $provider->token ||
            empty($this->requestTokenSecret))
        {
            $this->oauthState = $this->initRequestToken($provider->token);
        }

        if ( $this->oauthState === OAUTH_OK )
        {
            $this->log('request token is verified; store the secret!');
            $this->log("Token Secret: " . $this->requestTokenSecret);
            $provider->token_secret = $this->requestTokenSecret;
        }
        else
        {
            $this->log('request token has been rejected!');
        }

        return $this->oauthState;
    }

    public function lookupRequestTokenVerification($provider)
    {
        $this->mark();

        $this->oauthState = OAUTH_OK;

        $this->log("look up the request in our database");

        if ($this->requestToken != $provider->token ||
            empty($this->requestTokenSecret))
        {
            $this->oauthState = $this->initRequestTokenVerification($provider->token,
                                                                    $provider->verifier);
        }

        if ( $this->oauthState === OAUTH_OK )
        {
            $provider->token_secret = $this->requestTokenSecret;
        }

        return $this->oauthState;
    }

    public function lookupAccessToken($provider)
    {
        $this->mark();

        $this->oauthState = OAUTH_OK;

        if ($this->accessToken != $provider->token ||
            empty($this->accessTokenSecret))
        {
            $this->oauthState = $this->initAccessToken($provider->token);
        }

        if ( $this->oauthState === OAUTH_OK )
        {
            $this->log("Token Secret: " . $this->accessTokenSecret);
            $provider->token_secret = $this->accessTokenSecret;
        }

        return $this->oauthState;
    }

    /**
     * DATA MANAGEMENT FUNCTIONS
     **/

    protected function initConsumerKey($token)
    {
        // loads the secret from the database
        $this->mark();

        $boolValidToken = OAUTH_CONSUMER_KEY_UNKNOWN;

        $sth = $this->dbh->prepare("SELECT id, consumerSecret, verificationMode FROM consumers WHERE consumerKey = ?");
        $res = $sth->execute($token);

        if( PEAR::isError($res))
        {
            $boolValidToken = OAUTH_CONSUMER_KEY_REFUSED;
        }
        else if ($res->numRows() != 1)
        {
            $boolValidToken = OAUTH_CONSUMER_KEY_UNKNOWN;
        }
        else
        {
            $row = $res->fetchRow();
            $this->consumerKey = $token;
            $this->consumerID = $row[0]; // we need this for the nonce list
            $this->consumerSecret = $row[1];

            // this helper variable informs the calling service if a service requires explicit
            // verification
            $this->consumerVerificationMode = $row[2];

            $boolValidToken = OAUTH_OK;
        }

        $sth->free();

        // this function only returns true if there is a secret for the token

        return $boolValidToken;
    }

    protected function initRequestToken($token)
    {
        // loads the secret from the database
        $this->mark();
        $boolValidToken = OAUTH_TOKEN_REJECTED;

        if (!empty($token))
        {
            // this function only returns true if there is a secret for the token
            $sth = $this->dbh->prepare("SELECT id, request_token_secret, verification, " .
                                       "UNIX_TIMESTAMP(created_date), authorised_user_id  " .
                                       "FROM requesttokens WHERE consumer_key = ? AND request_token = ?");
            $res = $sth->execute(array($this->consumerKey,
                                       $token));
            if (!PEAR::isError($res))
            {
                if ($res->numRows() > 0)
                {
                    $row = $res->fetchRow();

                    if ( $row[3] > 0 && (time() - $row[3]) > self::TIMEOUT_DELTA )
                    {
                        // in this case we delete the token and all the nonces
                        $this->clearRequestTokenAndNonces($row[0]);
                        $this->log('token expired');
                        $boolValidToken = OAUTH_TOKEN_EXPIRED;
                    }
                    else if ($row[0] > 0)
                    {
                        $this->requestTokenID = $row[0];
                        $this->requestToken = $token;
                        $this->requestTokenSecret = $row[1];

                        // only used if we need to verify the request token when we want to get an access token
                        $this->verificationCode = $row[2];
                        $this->userID = $row[4];
                        $this->log('token is OK');
                        $boolValidToken = OAUTH_OK;
                    }
                }
                else
                {
                    $this->log('token not found!');
                }
            }
            else
            {
                $this->log('database error ' . $res->getMessage());
            }

            $sth->free();
        }
        else
        {
            $this->log('consumer forgot to provide a token!');
        }
        return $boolValidToken;
    }

    protected function initRequestTokenVerification($token, $verifier)
    {
        // loads the secret from the database
        $this->mark();
        $boolValidToken = OAUTH_TOKEN_REJECTED;

        $this->log("token ".$token. " verifier ".$verifier);

        // this function only returns true if there is a secret for the token
        $sth = $this->dbh->prepare("SELECT id, request_token_secret, verification, " .
                                   "UNIX_TIMESTAMP(created_date), authorised_user_id " .
                                   "FROM requesttokens WHERE consumer_key = ? AND request_token = ? AND verification = ?");
        $res = $sth->execute(array($this->consumerKey,
                                   $token,
                                   $verifier));
        if (!PEAR::isError($res))
        {
            if ($res->numRows() > 0)
            {
                $row = $res->fetchRow();

                if ( $row[3] > 0 &&
                    (time() - $row[3]) > self::TIMEOUT_DELTA )
                {
                    // in this case we delete the token and all the nonces
                    $this->clearRequestTokenAndNonces($row[0]);
                    $this->log('token expired');
                    $boolValidToken = OAUTH_TOKEN_EXPIRED;
                }
                else if ($row[0] > 0)
                {
                    $this->requestTokenID = $row[0];
                    $this->requestToken = $token;
                    $this->requestTokenSecret = $row[1];

                    // only used if we need to verify the request token when we want to get an access token
                    $this->verificationCode = $row[2];
                    $this->userID = $row[4];
                    $this->log("REQUEST TOKEN OK");
                    $boolValidToken = OAUTH_OK;
                }
            }
            else
            {
                $this->log("VERIFIER INVALID");
                $boolValidToken = OAUTH_VERIFIER_INVALID;
                // we should invalidate the token, tool
            }
        }
        $sth->free();

        return $boolValidToken;
    }

    protected function initAccessToken($token)
    {
        // loads the secret from the database
        $this->mark();
        $boolValidToken = OAUTH_TOKEN_REJECTED;

        $sth = $this->dbh->prepare("SELECT id, access_token_secret, user_id, " .
                                   "UNIX_TIMESTAMP(create_time) FROM accesstokens WHERE consumer_key = ? AND access_token = ?");
        $res = $sth->execute(array($this->consumerKey, $token));
        if (!PEAR::isError($res))
        {
            if ($res->numRows() > 0)
            {
                $row = $res->fetchRow();
                if ( $row[3] > 0 && (time() - $row[3]) > self::TIMEOUT_DELTA )
                {
                    // in this case we delete the token and all the nonces
                    $this->clearAccessTokenAndNonces($row[0]);
                    $boolValidToken = OAUTH_TOKEN_EXPIRED;
                }
                else if ($row[0] > 0)
                {
                    $this->accessTokenID = $row[0];
                    $this->accessToken = $token;
                    $this->accessTokenSecret = $row[1];
                    $this->userID = $row[2];

                    // update the timeout data
                    $sth->free();
                    $sth = $this->dbh->prepare("UPDATE accesstokens SET create_time = NOW() WHERE id = ? AND consumer_key = ?");
                    $res = $sth->execute(array($this->accessTokenID, $this->consumerKey));
                    $sth->free();

                    $boolValidToken = OAUTH_OK;
                }
            }
        }
        $sth->free();

        return $boolValidToken;
    }

    protected function clearRequestTokenAndNonces($id)
    {
        $sth = $this->dbh->prepare("DELETE FROM requesttokens WHERE id = ?");
        $res = $sth->execute($id);

        if (!PEAR::isError($res))
        {
            // remove the nonces
            $sth->free();
            $sth = $this->dbh->prepare("DELETE FROM nonce_list WHERE consumer_id = ? AND request_id = ?");
            $res = $sth->execute(array($this->consumerID,
                                       $id));
        }
        $sth->free();
    }

    protected function clearAccessTokenAndNonces($id)
    {
        // invalidate the access token to end the session for the consumer
        $sth = $this->dbh->prepare("DELETE FROM accesstokens WHERE consumer_key = ? and access_token = ?");
        $res = $sth->execute(array($this->consumerKey,
                                   $this->accessToken));
        if (!PEAR::isError($res))
        {
            $sth->free();
            // now remove all nonces for the access token
            $sth = $this->dbh->prepare("DELETE FROM nonce_list WHERE consumer_id = ? AND access_id = ?");
            $res = $sth->execute(array($this->consumerID,
                                       $id));
        }
        else
        {
            $this->log('DB Error: ' . $res->getMessage());
        }
        $sth->free();
    }
}

?>
