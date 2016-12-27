<?php
namespace RESTling\Security;

// The Security Model needs to implement a validateUserPassword($username, $password) method
// the validateUserPassword() method is expected to throw an error if the
// user credentials are rejected.
//
// For this purpose the "\RESTling\Exceptions\UserCredentialsRejected" - Exception
// can be used.

class Basic extends \RESTling\Security {
    public function validate($model, $input) {
        parent::validate($model, $input);

        if ($input->hasParameter("Authorization", "header")) {
            $auth = $input->getParameter("Authorization", "header");
            $aAuth = explode(" ", $auth, 2);
            if (count($aAuth) == 2 && $aAuth[0] === "Basic") {
                if (!$model || !method_exists($model, "validateUserPassword")) {
                    throw new \RESTling\Exception\Security\BasicValidationUnsupported();
                }

                $userInfo = explode(":", base64_decode($aAuth[1]));

                $model->validateUserPassword($userInfo[0], $userInfo[1]);
                $this->success();
            }
        }
    }
}
?>
