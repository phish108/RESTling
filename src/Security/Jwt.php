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
                    throw new \RESTling\Exception\Security\InvalidJwt();
                }

                $keyString = '';

                // 2 check if the JWT is encrypted
                if ($jwt instanceof \Jose\Object\JWE) {
                    $kid = $jwt->getSharedProtectedHeader('kid');
                    $jku = $jwt->getSharedProtectedHeader('jku');
                    $alg = $jwt->getSharedProtectedHeader('alg');
                    $enc = $jwt->getSharedProtectedHeader('enc');

                    if (empty($alg) && empty($enc)) {
                        throw new \RESTling\Exception\Security\JweHeadersMissing();
                    }

                    // 3 get encryption keys
                    $keyAttr = [
                        'use' => 'enc',
                        'alg' => $alg,
                    ];

                    if (empty($kid) && empty($jku)) {
                        // 3a check if we need to load a private key
                        if (!preg_match('/^RSA/', $alg)) {
                            throw new \RESTling\Exception\Security\JwaUnsupported();
                        }

                        if (!method_exists($model, 'getPrivateKey')) {
                            throw new \RESTling\Exception\Security\PrivateKeyDecryptionUnsupported();
                        }

                        // 3b  if JWE find service privateKey($alg) and decrypt payload
                        if (!($keyString = $model->getPrivateKey())) {
                            throw new \RESTling\Exception\Security\PrivateKeyMissing();
                        }
                    }
                    else {
                        // 3c  load kid or jku from JOSE header if present
                        if (!method_exists($model, 'getSharedKey')) {
                            throw new \RESTling\Exception\Security\SharedKeyDecryptionUnsupported();
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
                            throw new \RESTling\Exception\Security\SharedKeyMissing();
                        }
                    }

                    $key = \Jose\Factory\JWKFactory::createFromKey($keyString,
                                                                   null,
                                                                   $keyAttr);
                    if (!$key) {
                        throw new \RESTling\Exception\Security\KeyBroken();
                    }

                    $jwk_set = new \Jose\Object\JWKSet();
                    $jwk_set->addKey($key);
                    $decrypter = \Jose\Decrypter::createDecrypter([$alg], [$enc],['DEF', 'ZLIB', 'GZ']);

                    try {
                        $decrypter->decryptUsingKeySet($jwt, $jwk_set, null);
                    }
                    catch (Exception $err) {
                        throw new \RESTling\Exception\Security\DecryptionFailed();
                    }

                    $payload = $jwt->getPayload();
                    if (!$payload) {
                        throw new \RESTling\Exception\Security\MissingPayload();
                    }

                    // 5  if JWE check if payload contains a JWS
                    if (is_array($payload) && array_key_exists('signatures', $payload)) {
                        // 5a update $jwt hold the embedded JWS
                        $jwt = \Jose\Util\JWSLoader::loadSerializedJsonJWS($payload);
                        if (!$jwt || !($jwt instanceof \Jose\Object\JWS)) {
                            throw new \RESTling\Exception\Security\MissingJwt();
                        }
                    }
                    elseif (empty($kid) && empty($jku)) {
                        throw new \RESTling\Exception\Security\InvalidJwt();;
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
                        throw new \RESTling\Exception\Security\InvalidJwt();
                    }

                    if (empty($kid) && empty($jku)) {
                        throw new \RESTling\Exception\Security\KeyIdMissing();;
                    }

                    if (!method_exists($model, 'getSharedKey')) {
                        throw new \RESTling\Exception\Security\SharedKeyValidationUnsupported();
                    }

                    if (!($keyString = $model->getSharedKey($keyId))) {
                        throw new \RESTling\Exception\Security\SharedKeyMissing();
                    }

                    $key = \Jose\Factory\JWKFactory::createFromKey($keyString,
                                                                   null,
                                                                   $keyAttr);
                    if (!$key) {
                        throw new \RESTling\Exception\Security\KeyBroken();;
                    }

                    $jwk_set = new \Jose\Object\JWKSet();
                    $jwk_set->addKey($key);

                    // 8 verify JWS signature
                    $verifier = \Jose\Verifier::createVerifier([$alg]);
                    try {
                        $verifier->verifyWithKeySet($jwt, $jwk_set, null, null);
                    }
                    catch (Exception $err) {
                        throw new \RESTling\Exception\Security\TokenRejected();
                    }
                }

                // 9  verify iss claim with key context
                $iss = $jwt->getClaim('iss');

                if (empty($iss)) {
                    throw new \RESTling\Exception\Security\MissingIssuer();
                }

                if (method_exists($model, "verifyIssuer")) {
                    try {
                        $model->verifyIssuer($iss, $keyId);
                    }
                    catch (Exception $err){
                        throw new \RESTling\Exception\Security\IssuerRejected();
                    }
                }

                // 10 verify that aud points to service URL
                $aud = $jwt->getClaim('aud');

                if (empty($aud)) {
                    throw new \RESTling\Exception\Security\MissingAudience();
                }

                $myUrl = 'http' . ($_SERVER['HTTPS'] ? "s" : "") . "://" . (empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST']) . $_SERVER['REQUEST_URI'];

                if ($aud !== $myUrl) {
                    throw new \RESTling\Exception\Security\AudienceRejected();
                }

                // 11 verify extra claims based on RFC
                // 12 verify extra claims based on key context
                $self->success();
            }
        }
    }
}
?>
