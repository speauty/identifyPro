<?php

define('APP_PATH', dirname(__FILE__).'/../');

$app = new Yaf\Application( APP_PATH . "/conf/app.ini");

$app->bootstrap()->run();
?>
