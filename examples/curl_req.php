<?php

require __DIR__ . '/../Curl.php';
require __DIR__ . '/../HttpException.php';

use skoro\curl\Curl;
use skoro\curl\HttpException;

try {
    $req = Curl::get('ru.wikipedia.org');
} catch (HttpException $e) {
    var_dump($e->statusCode);
}
