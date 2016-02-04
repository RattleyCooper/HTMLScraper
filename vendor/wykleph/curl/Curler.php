<?php

namespace Wykleph\Curler;

/**
 * Class Curler
 *
 * A fluent API wrapper for libcurl in php.  Setting options and headers is done using
 * method chaining instead of setting options explicitly using the libcurl constants.
 *
 * Debugging cURL commands in php using the Curler class is insanely simple as well.
 * Just chain the `dryRun()` method onto the end of your method chain instead of
 * the `go()` method and it will dump out all of the cURL request information.
 *
 * See http://php.net/manual/en/book.curl.php for information on libcurl.
 *
 * Note: This class is in its infancy and may not be suitable for all
 * applications, however it is great for simple requests!  Multi-
 * requests will be coming soon(hopefully)!
 *
 * Todo: Clean up code / Add more usage examples to github.
 */
class Curler
{
    public $url;
    public $postfields;
    protected $postfields_set;
    public $poststring;
    public $headers;
    protected $headers_set;
    public $response;
    public $noRender;
    public $ch;
    public $options;
    public $errors;
    public $optionsMap;
    public $cookieJarFile;

    /**
     * Instantiate with a valid URL.
     *
     * @param $urlCh
     */
    public function __construct($urlCh='')
    {
        $this->optionsMap = [
            '-b'            =>  'CURLOPT_COOKIEFILE',
            '-c'            =>  'CURLOPT_COOKIEJAR',
            '-L'            =>  'CURLOPT_FOLLOWLOCATION',
            '--compressed'  =>  'CURLOPT_ENCODING',
            '-m'            =>  'CURLOPT_TIMEOUT',
            '--max-time'    =>  'CURLOPT_TIMEOUT',
            '--connect-timeout'=>'CURLOPT_CONNECTTIMEOUT'
        ];

        $this->url = $urlCh;
        $this->postfields = [];
        $this->postfields_set = false;
        $this->poststring = '';
        $this->headers = [];
        $this->headers_set = false;
        $this->response = '';
        $this->noRender = false;
        $this->cookieJarFile = '';

        if( $urlCh )
        {
            $this->ch = curl_init($urlCh);
        }else
        {
            $this->ch = false;
        }

        $this->options = [];

        $this->errors = false;
        return $this;
    }

    /**
     * Let users call the class like a function after instantiating it.  This will allow for
     * reuse of the class without having to re-instantiate it.
     *
     * @param $url
     * @return $this
     */
    public function __invoke($url)
    {
        $this->resetHandle();
        $this->resetOptions();
        $this->url = $url;
        $this->postfields = [];
        $this->postfields_set = false;
        $this->poststring = '';
        $this->headers = [];
        $this->headers_set = false;
        $this->response = '';
        $this->noRender = false;
        $this->cookieJarFile = '';
        $this->ch = curl_init($url);
        $this->options = [];

        return $this;
    }

    /**
     * Close the cURL handle if it's still open.
     */
    public function __destruct()
    {
        if( gettype($this->ch) == 'resource' and get_resource_type($this->ch) == 'curl' )
        {
            curl_close($this->ch);
        }
    }

    /**
     * Provide information for `var_dump()`.
     *
     * @return mixed
     */
    public function __debugInfo()
    {
        return $this->dryRun();
    }

    /**
     * If the Curler object is echoed, it will show the value of the response attribute.
     * This is the same as doing $this->dryRun()
     *
     * @return string
     */
    public function __toString()
    {
        return $this->dryRun();
    }

    /**
     * Change the html characters to htmlspecialchars on `go()`.  HTML will not be rendered by a browser
     *
     * @param bool $noRender
     * @return $this
     */
    public function suppressRender($noRender = true)
    {
        $this->noRender = $noRender;
        return $this;
    }
    
    /**
     * Close the current curl handle.
     *
     * @return $this
     */
    protected function closeHandle($ch)
    {
        if( gettype($ch) == 'resource' and get_resource_type($ch) == 'curl' )
        {
            curl_close($ch);
        }
        return $this;
    }

    /**
     * Tell Curler that you expect a compressed response.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_ENCODING
     * @param string $compressionType
     * @return $this
     */
    public function compressedResponse($compressionType = '')
    {
        $this->setOption('--compressed');
        return $this;
    }

    /**
     * Return a copy of the current curl handle.
     *
     * @return resource
     */
    public function copy()
    {
        return curl_copy_handle($this->ch);
    }

    /**
     * Set the username and password that will be used in the request option.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_USERPWD
     * @param $username
     * @param $password
     * @return $this
     */
    public function credentials($username, $password)
    {
        $this->setOption('CURLOPT_USERPWD', $username . ':' . $password);
        return $this;
    }

    /**
     * Decode a url-encoded string like you would get from http_build_query()
     *
     * @param $string
     * @return bool|string
     */
    public function decode($string)
    {
        return curl_unescape($this->ch, $string);
    }

    /**
     * Do a dry run on the current curl handle.  This will not send anything to the server
     * if you do not call the `go()` method.
     *
     * @return mixed
     */
    public function dryRun()
    {
        $this->setHeaders()->setPostFields();
        $data['url'] = $this->url;
        $data['cookieJarFile'] = $this->cookieJarFile;
        $data['headers'] = $this->headers;
        $data['postfields'] = $this->postfields;
        $data['poststring'] = $this->poststring;
        $data['options'] = $this->options;
        $data['curl_getinfo'] = curl_getinfo($this->ch);

        //$data['urls'] = $this->urls;
        //$data['additionalCookies'] = $this->additionalCookies;
        //$data['handles'] = $this->handles;

        return $data;
    }

    /**
     * Url-encode a string
     *
     * @param $string
     * @return bool|string
     */
    public function encode($string)
    {
        return curl_escape($this->ch, $string);
    }

    /**
     * Returns `true` if Curler encountered errors with the request and `false` if it
     * did not encounter errors.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return $this->errors;
    }

    /**
     * Tell Curler that it should follow redirects.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_FOLLOWLOCATION
     * @param bool $bool
     * @return $this
     */
    public function followRedirects($bool = true)
    {
        $this->setOption('-L', $bool);
        return $this;
    }

    /**
     * Tell Curler it should preform the request using the GET method.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_HTTPGET
     * @param bool $bool
     * @return $this
     */
    public function get($bool = true)
    {
        $this->setOption('CURLOPT_HTTPGET', $bool);
        return $this;
    }

    /**
     * Return curl handler.
     *
     * @return resource
     */
    public function getHandle()
    {
        return $this->ch;
    }

    /**
     * Return a list of headers that Curler is tracking.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Return the response of the last curl request.
     *
     * @return null|string
     */
    public function getResponse()
    {
        if( ! $this->response ) { return curl_errno($this->ch) . ': ' . curl_error($this->ch); }
        return $this->response;
    }

    /**
     * Start the curl request and automatically close the cURL handle if the `autoClose` option is
     * set to true.
     *
     * @param bool $autoClose
     * @return $this
     * @throws \Exception
     */
    public function go($autoClose = true)
    {
        // If no url is given we cannot run `curl_init` if the URL was not provided
        // on class instantiation.
        if( ! $this->url ) { throw new \Exception('Curler requires a valid URL to run.  See documentation for details.'); }
        // Since we have our URL set and we don't have a valid cURL handle, we need to make one.
        if( ! $this->ch ) { $this->ch = curl_init($this->url); }

        // Business as usual.
        $this->setHeaders()->setPostFields();
        $this->response = curl_exec($this->ch);

        // Check to see if the request was successful or not.  Set the errors attribute accordingly
        if ( $this->response === false )
        {
            $this->errors = true;
        }

        // Should the output HTML be renderable?
        if ( $this->noRender ) { $this->response = htmlspecialchars($this->response); }

        // Should the cURL handle be closed automatically?
        if( $autoClose ) { $this->closeHandle($this->ch); }

        return $this;
    }

    /**
     * Add a header to the array of headers that will be set when the request is executed.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_HTTPHEADER
     * @param $headerName
     * @param $headerValue
     * @return $this
     */
    public function header($headerName, $headerValue)
    {
        $this->headers[$headerName] = $headerValue;
        return $this;
    }

    /**
     * Add headers to the request using an associative array of key/value pairs.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_HTTPHEADER
     * @param $associativeArray
     * @return $this
     */
    public function headerArray($associativeArray)
    {
        foreach($associativeArray as $name=>$value)
        {
            $this->header($name, $value);
        }
        return $this;
    }
    
    /**
     * Set Curler to include the request headers in the output.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_HEADER
     * @param bool $bool
     * @return $this
     */
    public function outputHeaders($bool = true)
    {
        $this->setOption('CURLOPT_HEADER', $bool);
        return $this;
    }

    /**
     * Set a custom request method.
     *
     * Some supported request methods include:
     *
     *      CONNECT
     *      DELETE
     *      HEAD
     *      GET
     *      POST
     *      PUT
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_CUSTOMREQUEST
     * @param $method
     * @return $this
     * @throws \Exception
     */
    public function method($method)
    {
        $validMethods = [
            'CONNECT',
            'DELETE',
            'HEAD',
            'GET',
            'POST',
            'PUT'
        ];

        // Check for valid HTTP methods.
        if ( ! in_array($method, $validMethods) )
        {
            throw new \Exception("{$method} is not a valid method type.");
        }

        $this->setOption('CURLOPT_CUSTOMREQUEST', strtoupper($method));
        return $this;
    }

    /**
     * Set a file to write the output to.  You must give this method an open file stream.
     *
     * Note: AsyncCurler support coming soon.
     * todo: add AsyncCurler support.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_STDERR
     * @param $fileHandle
     * @return $this
     * @throws \Exception
     */
    public function outputTo($fileHandle)
    {
        if( get_resource_type($fileHandle) !== 'file' )
        {
            throw new \Exception('$fileHandle must be an open file handle.  No file handle was provided.');
        }

        $this->setOption('CURLOPT_STDERR', $fileHandle);
        return $this;
    }

    /**
     * Add post parameters to the current curl request.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_POSTFIELDS
     * @param $key
     * @param $value
     * @return $this
     */
    public function post($key, $value)
    {
        $this->postfields[$key] = $value;
        return $this;
    }

    /**
     * Set the post parameters based on an associative array of name/value pairs.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_POSTFIELDS
     * @param $associativeArray
     * @return $this
     */
    public function postArray($associativeArray)
    {
        foreach($associativeArray as $name=>$value)
        {
            $this->post($name, $value);
        }
        return $this;
    }

    /**
     * Set the referer.  No need to type out 'Referer:', just give a URL
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_REFERER
     * @param $url
     * @return $this
     */
    public function referer($url)
    {
        $this->setOption('CURLOPT_REFERER', $url);
        return $this;
    }

    /**
     * Reset the current curl handle.
     *
     * @return $this
     */
    public function resetHandle()
    {
        if( gettype($this->ch) == 'resource' and get_resource_type($this->ch) == 'curl' )
        {
            curl_reset($this->ch);
        }

        $this->postfields = [];
        $this->poststring = '';
        $this->headers = [];
        $this->response = null;
        $this->url = null;
        return $this;
    }

    /**
     * Reset the options array for new requests.
     *
     * @return $this
     */
    public function resetOptions()
    {
        $this->options = [];
        return $this;
    }

    /**
     * Set the filepath that will be used to read/save cookies.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_COOKIEFILE, CURLOPT_COOKIEJAR
     * @param $filepath
     * @return $this
     */
    public function cookieJar($filepath)
    {
        $this->cookieJarFile = $filepath;
        $this->cookieJarRead($filepath);
        $this->cookieJarWrite($filepath);
        return $this;
    }

    /**
     * Set the filepath that will be used to read cookies.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_COOKIEFILE
     * @param $filepath
     * @return $this
     */
    public function cookieJarRead($filepath)
    {
        $this->setOption('CURLOPT_COOKIEFILE', $filepath);
        return $this;
    }

    /**
     * Set the filepath that will be used to save cookies.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_COOKIEJAR
     * @param $filepath
     * @return $this
     */
    public function cookieJarWrite($filepath)
    {
        $this->setOption('CURLOPT_COOKIEJAR', $filepath);
        return $this;
    }

    /**
     * Set the endpoint that Curler will use in the request.
     *
     * @param $url
     * @return $this
     */
    public function setEndpoint($url)
    {
        $this->url = $url;
        $this->ch = curl_init($url);
        return $this;
    }

    /**
     * Set the headers that will be used in the curl request.  An associative array can be passed to
     * the method, but it will overwrite anything set using the `header()` method.  Call the
     * `header()` method multiple times to add headers dynamically, or assemble the array
     * of headers and pass that array to this method.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_HTTPHEADER
     * @param bool $headers
     * @return $this|\Exception
     */
    public function setHeaders($headers = false)
    {
        if( is_array($headers) )
        {
            $this->headers = $headers;
        }elseif($headers !== false)
        {
            return new \Exception('$headers must be an associative array of header keys and header values.');
        }

        if( count($this->headers) === 0 )
        {
            return $this;
        }

        $headerStrings = [];
        foreach($this->headers as $key=>$value)
        {
            $key = trim($key);
            $value = trim($value);
            $headerStrings[] = "{$key}: {$value}";
        }

        $this->setOption('CURLOPT_HTTPHEADER', $headerStrings);
        $this->headers_set = true;
        return $this;
    }

    /**
     * Set an option using libcurl constants, or command line options(very few available).
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @param $option
     * @param $value
     * @return $this
     */
    public function setOption($option, $value = '')
    {
        if( strpos($option, 'CURL') === false )
        {
            try{
                $option = $this->optionsMap[$option];
                if ( ! $option ) { return $this; }
                $this->setOption($option, $value);
            } catch ( Exception $e )
            {
                echo $e;
            }

        }else
        {
            curl_setopt($this->ch, constant($option), $value);
            $this->options[$option] = $value;
        }

        return $this;
    }

    /**
     * Set the post string that should be used in the POST request.
     *
     * @param $postString
     * @return $this
     */
    public function setPostString($postString)
    {
        $this->poststring = $postString;
        return $this;
    }

    /**
     * Similar to `setHeaders()`.  Pass an associative array of ALL the post fields you would like to use
     * `setPostFields` should only be called once before `curl_exec` is called
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_POSTFIELDS
     * @param bool $postfields
     * @return $this
     */
    public function setPostFields($postfields = false)
    {
        if( is_array($postfields) )
        {
            $this->postfields = $postfields;
        }

        // If poststring is set to a string, assume the work is done and set option.
        if ( is_string($this->poststring) and $this->poststring )
        {
            $this->setOption('CURLOPT_POSTFIELDS', $this->poststring);
            return $this;
        }

        if( count($this->postfields) === 0 )
        {
            return $this;
        }

        $httpBuilt = http_build_query($this->postfields);
        $this->poststring = $this->decode($httpBuilt);

        $this->setOption('CURLOPT_POSTFIELDS', $this->poststring);
        $this->postfields_set = true;
        return $this;
    }

    /**
     * Tell Curler that you would like to fetch the output of the curl request as a returned
     * value and to NOT automatically display it.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_RETURNTRANSFER
     * @param bool $bool
     * @return $this
     */
    public function returnText($bool = true)
    {
        $this->setOption('CURLOPT_RETURNTRANSFER', $bool);
        return $this;
    }

    /**
     * Set the SSL version for the curl request.  This is not the same as the --ssl option
     * in cURL.  Version numbers range from 0-6.
     *
     * CURL_SSLVERSION_DEFAULT      (0)
     * CURL_SSLVERSION_TLSv1        (1)
     * CURL_SSLVERSION_SSLv2        (2)
     * CURL_SSLVERSION_SSLv3        (3)
     * CURL_SSLVERSION_TLSv1_0      (4)
     * CURL_SSLVERSION_TLSv1_1      (5)
     * CURL_SSLVERSION_TLSv1_2      (6)
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_SSLVERSION
     * @param $versionNumber
     * @return $this
     */
    public function ssl($versionNumber)
    {
        $this->setOption('CURLOPT_SSLVERSION', $versionNumber);
        return $this;
    }

    /**
     * Set the connection timeout parameter in seconds.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_CONNECTTIMEOUT
     * @param $seconds
     * @return $this
     */
    public function timeout($seconds)
    {
        $this->setOption('CURLOPT_CONNECTTIMEOUT', $seconds);
        return $this;
    }

    /**
     * Require a safe upload on files?
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_SAFE_UPLOAD
     * @param bool $bool
     * @return $this
     */
    public function safeUpload($bool = true)
    {
        $this->setOption('CURLOPT_SAFE_UPLOAD', $bool);
        return $this;
    }

    /**
     * Upload a file using a filepath and the `name` of the form element.
     *
     * @param string $postName
     * @param $filepath
     * @return $this
     */
    public function upload($postName, $filepath)
    {
        $filepath = '@' . $filepath;

        $this->post($postName, $filepath);
        return $this;
    }

    /**
     * Set the User-Agent header.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_USERAGENT
     * @param $value
     * @return $this
     */
    public function userAgent($value)
    {
        $this->setOption('CURLOPT_USERAGENT', $value);
        return $this;
    }

    /**
     * Verbose output.
     *
     * See http://php.net/manual/en/function.curl-setopt.php for more information.
     *
     * @curl_option CURLOPT_FAILONERROR, CURLOPT_HEADER_OUT, CURLOPT_VERBOSE
     * @return $this
     */
    public function verbose()
    {
        $this->setOption('CURLOPT_FAILONERROR', true);
        $this->setOption('CURLINFO_HEADER_OUT', true);
        $this->setOption('CURLOPT_VERBOSE', true);
        return $this;
    }

    /**
     * Write the response to a file.
     *
     * @param $filename
     * @return $this
     */
    public function writeResponse($filename)
    {
        file_put_contents($filename, $this->response);
        return $this;
    }
}
