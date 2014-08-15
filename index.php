<?php
require('./vendor/autoload.php');
list(, $url, $savePath, $mode) = $argv;
$crawler = new \trntv\crawler\Worker($url, $savePath, $mode);
$crawler->crawl();

