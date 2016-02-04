# Curler

A fluent API wrapper for libcurl in php.  Setting options and headers is done using
method chaining instead of setting options explicitly using the libcurl constants.

Debugging cURL commands in php using the Curler class is insanely simple as well.
Just chain the `dryRun()` method onto the end of your method chain instead of
the `go()` method and it will dump out all of the cURL request information
without making the request.
 
See http://php.net/manual/en/book.curl.php for information on libcurl.

Note: This library is in its infancy.

Full documentation coming soon.

## Getting Started

##### Install using Composer

Add `wykleph/curl` to your composer.json file or:

`composer require "wykleph/curl"

You could also just copy the files in the `src` directory into your project if you aren't using composer, however I would recommend it.

##### Creat the Curler instance.

```php
$curler = new Curler('https://github.com/');
```

_Note that all of the following methods can be chained together unless noted otherwise_

##### Post information

```php
$curler->post('fname', 'John')
    ->post('lname', 'Doe')
;
$curler->postArray(['fname'=>'John', 'lname'=>'Doe']);
```

##### Switch From POST to a GET request

```php
$curler->get();
```

##### Set Headers

```php
$curler->header('Connection', 'keep-alive')
    ->header('Host', 'github.com')
;
$curler->headerArray(['Connection'=>'keep-alive', 'Host'=>'github.com']);
```

##### Set User Agent or Referer

```php
$ua = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:40.0) Gecko/20100101 Firefox/40.0';

$curler->userAgent($ua)
    ->referer('http://github.com');
```

##### Save Cookies

```php
$curler->cookieJar('SomeDirectory/testCookie');
```

##### Follow Browser Redirects

```php
$curler->followRedirects();
```

##### Compressed Responses

You can tell `Curler` you expect a compressed response from the request with `compressedResponse()`.

```php
$curler->compressedResponse()
```

##### Upload a File

Just give a form input name and a filepath.

```php
$curler->upload('file', 'filepath');
```

##### Verbose Output

```php
$curler->verbose();
```

##### Write Response to File

_multi-request support coming soon._

```php
$curler->writeResponse('someDirectory/Filename');
```

#### Preforming an Asynchronous Request, or Multi-Request

You can send asynchronous requests as well!  This can be accomplished through the use of AsyncCurler by
adding URLs to the request(this retains any options that have been set in AsyncCurler up to this point),
or by adding cURL handles to the request(adding handles as opposed to urls is somewhat untested.  
It's in the works though).

```php
$curler = new AsyncCurler();

$urls = [
    'https://github.com/',
    'http://pastebin.com/',
    'https://google.com/',
    'http://yahoo.com/'
];

$headers = [
    'Connection'        =>      'keep-alive',
    'Accept'            =>      'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language'   =>      'en-US,en;q=0.5',
    'Content-Type'      =>      'application/x-www-form-urlencoded'
];

$cookieJar = '/home/user/testCookie';

$curler->followRedirects()          // Exactly how it sounds.
    ->headerArray($headers)         // Add an array of headers.
    ->cookieJar($cookieJar)         // Set a location for cookies.
    ->returnText()                  // Don't display response.  Get a text string.
    ->suppressRender()              // This will suppress the html from rendering if it is echoed.
    ->addUrl($urls)                 // Add urls to the multi-request..
    ->addUrl('http://php.net/');    // or add them individually.


$html = $curler->go()->getResponse();

var_dump($html);
 ```

#### Debugging a request
Debug requests with ease by chaining the `dryRun()` method to the end of your method chain and dumping the result.

This will output something similar to this(consider using something like Symfony's `VarDumper` or Laravels dump and die - `dd()`):

```php
 [
   "url" => "https://github.com/"
   "cookieJarFile" => "/home/parker/gitCookie"
   "headers" => [
     "Connection" => "keep-alive"
     "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
     "Accept-Language" => "en-US,en;q=0.5"
     "Host" => "github.com"
     "Content-Type" => "application/x-www-form-urlencoded"
   ]
   "postfields" => []
   "poststring" => ""
   "handles" => []
   "options" => [
     "CURLOPT_FOLLOWLOCATION" => true
     "CURLOPT_COOKIEFILE" => "/home/parker/gitCookie"
     "CURLOPT_COOKIEJAR" => "/home/parker/gitCookie"
     "CURLOPT_RETURNTRANSFER" => true
     "CURLOPT_HTTPHEADER" => [
       0 => "Connection: keep-alive"
       1 => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
       2 => "Accept-Language: en-US,en;q=0.5"
       3 => "Host: github.com"
       4 => "Content-Type: application/x-www-form-urlencoded"
     ]
   ]
   "curl_getinfo" => [
     "url" => "https://github.com/"
     "content_type" => null
     "http_code" => 0
     "header_size" => 0
     "request_size" => 0
     "filetime" => 0
     "ssl_verify_result" => 0
     "redirect_count" => 0
     "total_time" => 0.0
     "namelookup_time" => 0.0
     "connect_time" => 0.0
     "pretransfer_time" => 0.0
     "size_upload" => 0.0
     "size_download" => 0.0
     "speed_download" => 0.0
     "speed_upload" => 0.0
     "download_content_length" => -1.0
     "upload_content_length" => -1.0
     "starttransfer_time" => 0.0
     "redirect_time" => 0.0
     "redirect_url" => ""
     "primary_ip" => ""
     "certinfo" => []
     "primary_port" => 0
     "local_ip" => ""
     "local_port" => 0
   ]
 ]
```
