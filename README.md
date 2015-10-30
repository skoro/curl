Curl
====
Object oriented wrapper for curl functions. Wrappers for curl and curl_multi functions are available.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist skoro/curl "*"
```

or add

```
"skoro/curl": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
// Include composer autoload script.
require 'vendor/autoload.php';

// Simple GET request.
$content = Curl::get('google.com');

// HEAD request
$curl = new Curl('google.com', 'HEAD');
$body = $curl->request(); // Returns response with headers.
         $curl->getResponse(); // Returns "raw" response.
         $curl->getResponseHeaders(); // Returns array of headers.
}
```

Curl multi usage:
```php
$m = new Multi();
// Attach curl instances and run them.
$m->add(new Curl('google.com', 'HEAD'))
  ->add(new Curl('microsoft.com', 'HEAD'))
  ->add(new Curl('amazon.com', 'GET'))
  ->run();
// Get responses.
foreach ($m->getMulti() as $curl) {
    var_dump($m->getResponse());
}
```

Exceptions
-----------
* ```HttpException``` throws by ```Curl::request()``` for requests except HEAD when returned response status not in range 200 ... 300 codes.

Links
-----
* [https://github.com/skoro/curl]()
* [http://docs.php.net/manual/en/book.curl.php]()
* [http://getcomposer.org]()
