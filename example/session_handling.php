<?php
    // this example illustrates how to work with multiple
    // security Schemes
require_once __DIR__."/../vendor/autoload.php";

require_once __DIR__ ."/restlingtest.php";

// The preferred method is to implement a separate security model.

// For very simple services and security requirements  the security accessors
// could be directly implemented into the service class. This makes sense, for
// example, if only API keys in Bearer Tokens are validated.
class RestlingTestSecurity
{
    // ***********************************************
    // Security accessors
    // ***********************************************

    public function getCookieName() {
        return "mySession"; // optional, default == "session"
    }

    // for Cookie authorization
    public function validateCookie($sessionid) {
        // we need to check if we find the cookie
        if ($sessionid != "helloCookie") {
            throw new \RESTling\Exception\Forbidden();
        }
    }

    // for Bearer Authorization
    public function validateToken($sessiontoken) {
        if ($sessionid != "helloToken") {
            throw new \RESTling\Exception\Forbidden();
        }
    }

    // for basic authorization
    public function validateUserPassword($user, $passwd) {
        if ($user != "foo") {
            throw new \RESTling\Exception\Forbidden();
        }
        if ($passw != "bar") {
            throw new \RESTling\Exception\Forbidden();
        }
    }
}

$service = new RESTling\Service();

$service->setSecurityModel(new RestlingTestSecurity());

$service->addSecurityHandler(new \RESTling\Security\Cookie());
$service->addSecurityHandler(new \RESTling\Security\Bearer());
$service->addSecurityHandler(new \RESTling\Security\Basic());

$service->run(new RestlingTest());
?>
