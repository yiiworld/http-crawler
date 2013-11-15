<?php
require_once('vendor/autoload.php');
require_once('lib/Crawler.php');


if(isset($argv[1]) && isset($argv[2])){
    // @todo: правильное разруливание аргументов + included, excluded
    // @todo: аргументы через --arg=value
    $crawler = new Crawler($argv[1], $argv[2], $argv[3]);
    $crawler->crawl();
}
