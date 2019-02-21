<?php

define('APP_PATH', dirname(__FILE__).'/../');

$app = new Yaf\Application( APP_PATH . "/conf/app.ini");
define("BASE_URL", 'http://local.identifypro.com/');
$app->bootstrap()->run();
?>
