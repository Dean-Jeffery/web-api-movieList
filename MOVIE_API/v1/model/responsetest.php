<?php

require_once('response.php');

$response = new Response();

$response->setSuccess(true);
$response->setHttpStatusCode(200);
$response->addMessage("This is a test message");
$response->addMessage("Test message two");
$response->addMessage("Third Test Message");
$response->send();

?>
