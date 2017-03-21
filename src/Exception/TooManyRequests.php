<?php
namespace RESTling\Exception;

class TooManyRequests extends \RESTling\Exception {
    const responseCode = 429;
}
?>
