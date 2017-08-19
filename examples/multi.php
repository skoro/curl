<?php

require __DIR__ . '/../Curl.php';
require __DIR__ . '/../Multi.php';

use skoro\curl\Multi;
use skoro\curl\Curl;

$multi = new Multi();
$multi->add(new Curl('http://ru.wikipedia.org'))
      ->add(new Curl('http://uk.wikipedia.org'))
      ->add(new Curl('http://google.com'))
      ->addUrl('https://ru.wikipedia.org')
      ->run();
      
foreach ($multi as $curl) {
    print $curl->getUrl() . ' --- ' . $curl->getStatusCode() . PHP_EOL;
}

