<?php
namespace RESTling\Security;

/**
 * For full compliance security models MUST support the following functions:
 * - getPrivateKey() - load the servers private key
 * - getSharedKey($keyId) - load a shared key for a given key id (kid or jku values)
 * - verifyIssuer($iss, $keyId) - throws an error if the issuer does not match the target for the keyId
 */
class Jwt extends \RESTling\Security {
    public function validate($model, $input) {
        parent::validate($model, $input);

        // 1 check if the authorization header is set
        if ($input->hasParameter("Authorization", "header")) {
            $auth = $input->getParameter("Authorization", "header");
            $aAuth = explode(" ", $auth, 2);

            // 1a verify that the authorization header requests the Bearer Scheme
            if (count($aAuth) == 2 && $aAuth[0] === "Bearer") {
                // 1b verify that the token is a JWT
                $loader = new \Jose\Loader();
                try {
                    $jwt = $loader->load($aAuth[1]);
                }
                catch (Exception $err) {
                    throw new Exception("Invalid JWT");
                }

                $keyString = '';

                // 2 check if the JWT is encrypted
                if ($jwt instanceof \Jose\Object\JWE) {
                    $kid = $jwt->getSharedProtectedHeader('kid');
                    $jku = $jwt->getSharedProtectedHeader('jku');
                    $alg = $jwt->getSharedProtectedHeader('alg');
                    $enc = $jwt->getSharedProtectedHeader('enc');

                    if (empty($alg) && empty($enc)) {
                        throw new Exception("Cannot Validate JWE");
                    }

                    // 3 get encryption keys
                    $keyAttr = [
                        'use' => 'enc',
                        'alg' => $alg,
                    ];

                    if (empty($kid) && empty($jku)) {
                        // 3a check if we need to load a private key
                        if (!preg_match('/^RSA/', $alg)) {
                            throw new Exception("JWA Algorithm $alg Unsupported");
                        }

                        if (!method_exists($model, 'getPrivateKey')) {
                            throw new Exception("JWE Private Key Decryption Not Supported");
                        }

                        // 3b  if JWE find service privateKey($alg) and decrypt payload
                        if (!($keyString = $model->getPrivateKey())) {
                            throw new Exception("JWE Private Key Missing");
                        }
                    }
                    else {
                        // 3c  load kid or jku from JOSE header if present
                        if (!method_exists($model, 'getSharedKey')) {
                            throw new Exception("JWE Shared Key Decryption Not Supported");
                        }

                        $keyId = $kid;
                        if (empty($kid)) {
                            $keyId = $jku;
                        }
                        else {
                            $keyAttr['kid'] = $kid;
                        }

                        // 3d ask JOSE Key Context (for $kid or $jku) from model
                        if (!($keyString = $model->getSharedKey($keyId))) {
                            throw new Exception("JWE Private Key Missing");
                        }
                    }

                    $key = \Jose\Factory\JWKFactory::createFromKey($keyString,
                                                                   null,
                                                                   $keyAttr);
                    if (!$key) {
                        throw new Exception("JWE ALG-Key Broken");
                    }

                    $jwk_set = new \Jose\Object\JWKSet();
                    $jwk_set->addKey($key);
                    $decrypter = \Jose\Decrypter::createDecrypter([$alg], [$enc],['DEF', 'ZLIB', 'GZ']);

                    try {
                        $decrypter->decryptUsingKeySet($jwt, $jwk_set, null);
                    }
                    catch (Exception $err) {
                        throw new Exception("JWE Not Decrypted");
                    }

                    $payload = $jwt->getPayload();
                    if (!$payload) {
                        throw new Exception("Missing Payload");
                    }

                    // 5  if JWE check if payload contains a JWS
                    if (is_array($payload) && array_key_exists('signatures', $payload)) {
                        // 5a update $jwt hold the embedded JWS
                        $jwt = \Jose\Util\JWSLoader::loadSerializedJsonJWS($payload);
                        if (!$jwt || !($jwt instanceof \Jose\Object\JWS)) {
                            throw new Exception("Invalid Embedded JWT");
                        }
                    }
                    elseif (empty($kid) && empty($jku)) {
                        throw new Exception("Embedded JWS Missing");
                    }
                }

                // 6 check if the JWT is a JWS

                $keyString = '';
                if ($jwt instanceof \Jose\Object\JWS) {
                    // 7 load kid or jku from JOSE header
                    $kid = $jwt->getSignature(0)->getProtectedHeader('kid');
                    $jku = $jwt->getSignature(0)->getProtectedHeader('jku');
                    $alg = $jwt->getSignature(0)->getProtectedHeader('alg');

                    $keyAttr = [
                        'use' => 'sig',
                    ];

                    // 7a ask JOSE Key Context (for $kid or $jku) from model
                    $keyId = $kid;
                    if (empty($kid)) {
                        $keyId = $jku;
                    }
                    else {
                        $keyAttr['kid'] = $kid;
                    }

                    if (empty($alg)) {
                        throw new Exception("Cannot Validate JWS");
                    }

                    if (empty($kid) && empty($jku)) {
                        throw new Exception("Cannot Identify JWS Signature Key");
                    }

                    if (!method_exists($model, 'getSharedKey')) {
                        throw new Exception("JWE Shared Key Validation Not Supported");
                    }

                    if (!($keyString = $model->getSharedKey($keyId))) {
                        throw new Exception("JWE Private Key Missing");
                    }

                    $key = \Jose\Factory\JWKFactory::createFromKey($keyString,
                                                                   null,
                                                                   $keyAttr);
                    if (!$key) {
                        throw new Exception("JWE ALG-Key Broken");
                    }

                    $jwk_set = new \Jose\Object\JWKSet();
                    $jwk_set->addKey($key);

                    // 8 verify JWS signature
                    $verifier = \Jose\Verifier::createVerifier([$alg]);
                    try {
                        $verifier->verifyWithKeySet($jwt, $jwk_set, null, null);
                    }
                    catch (Exception $err) {
                        throw new Exception("JWS Not Verified");
                    }
                }

                // 9  verify iss claim with key context
                $iss = $jwt->getClaim('iss');

                if (empty($iss)) {
                    throw new Exception("Missing Issuer Claim");
                }

                if (method_exists($model, "verifyIssuer")) {
                    try {
                        $model->verifyIssuer($iss, $keyId);
                    }
                    catch (Exception $err){
                        throw new Exception("Issuer Validation Failed");
                    }
                }

                // 10 verify that aud points to service URL
                $aud = $jwt->getClaim('aud');

                if (empty($aud)) {
                    throw new Exception("Missing Audience Claim");
                }

                $myUrl = 'http' . ($_SERVER['HTTPS'] ? "s" : "") . "://" . (empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST']) . $_SERVER['REQUEST_URI'];

                if ($aud !== $myUrl) {
                    throw new Exception("Audience Validation Failed");
                }

                // 11 verify extra claims based on RFC
                // 12 verify extra claims based on key context
                $self->success();
            }
        }
    }
}
?>
