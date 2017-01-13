<?php

// Very Basic RESTling Service responding to GET, PUT, POST and DELETE
// requests.

require_once __DIR__."/../vendor/autoload.php";

// load our service class.
require_once __DIR__ ."/restlingtest.php";

$service = new RESTling\Service();
$service->run(new RestlingTest());
?>
