<?php

require_once __DIR__."/../vendor/autoload.php";
use League\JsonGuard\Validator as JSONValidator;

$data = "foobarbaz";
$schema = json_decode('{"type": "string","minLength":5,"maxLength":8}');

$validator = new JSONValidator($data, $schema);

if ($validator->fails()) {
    echo(json_encode($validator->errors()));
}
else {
    echo("ok");
}
echo("\n");
?>
