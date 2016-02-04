<?php

namespace Wykleph\Curler;

/**
 * Class AsyncCurler
 *
 * Asynchronous requests with cURL.
 */
class AsyncCurler extends Curler
{
    public $ch;
    public $cmh;

    public $urls;
    public $handles;
    public $multi_response;
    public $additionalCookies;

    public $multi_active;
    public $multi_cookie;

    public $poststring;
    public $postfields;

    public $optionsMap;

    public $responses_filepath;
    public $write_responses;

    public function __construct()
    {
        $this->setDefault();
        return $this;
    }

    public function __destruct()
    {
        $this->removeHandles();
        $this->closeHandles($this->handles);
        $this->closeMultiHandle();
    }

    public function __invoke()
    {
        $this->removeHandles();
        $this->closeHandles($this->handles);
        $this->closeMultiHandle();
        $this->setDefault();
        return $this;
    }

    /**
     * Set the class back to default settings.
     *
     * @return $this
     */
    private function setDefault()
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

        $this->ch = curl_init();
        $this->cmh = curl_multi_init();

        $this->urls = [];
        $this->handles = [];
        $this->multi_response = [];
        $this->additionalCookies = [];

        $this->multi_active = null;
        $this->multi_cookie = false;

        $this->poststring = '';
        $this->postfields = [];

        $this->responses_filepath = null;
        $this->write_responses = false;

        return $this;
    }

    /**
     * Do a dry run on the current curl handles.  This will not send anything to the server
     * if you do not call the `go()` method.
     *
     * @return mixed
     */
    public function dryRun()
    {
        $this->setHeaders()->setPostFields();
        // Take what we have in this->urls and generate handles.
        $this->generateHandlesFromUrls();
        // Take what we have in this->handles and add them to this->cmh
        $this->setupMultiHandles();
        $data['cookieJarFile'] = $this->cookieJarFile;
        $data['headers'] = $this->headers;
        $data['postfields'] = $this->postfields;
        $data['poststring'] = $this->poststring;
        $data['options'] = $this->options;
        $data['curl_getinfo'] = curl_getinfo($this->ch);

        $data['urls'] = $this->urls;
        $data['additionalCookies'] = $this->additionalCookies;
        $data['handles'] = $this->handles;

        return $data;
    }

    /**
     * Close all the handles given.
     *
     * @param $handles
     * @return $this
     */
    private function closeHandles($handles)
    {
        foreach ( $handles as $ch )
        {
            $this->closeHandle($ch);
        }
        return $this;
    }

    /**
     * Get the response from the last request
     *
     * @return bool | $this->multi_response
     */
    public function getResponse()
    {
        return ( ! $this->multi_response) ? false : $this->multi_response ;
    }

    /**
     * Remove handles from the list of handles.
     *
     * @return $this
     */
    private function removeHandles()
    {
        foreach ( $this->handles as $key=>$handle )
        {
            try {
                curl_multi_remove_handle($this->cmh, $handle);
            } catch (\Exception $e) { /* If there are errors, no worries. */ }
        }
        return $this;
    }

    /**
     * Try to close the multi-handle and silently catch any exceptions.
     *
     * @return $this
     */
    private function closeMultiHandle()
    {
        try{
            curl_multi_close($this->cmh);
        } catch ( \Exception $e ) { /* It's probably fine... */ }

        return $this;
    }

    /**
     * Add a cURL handle to the list of cURL handles to execute during a multi-request.
     *
     * @param $ch
     * @return $this
     * @throws \Exception
     */
    public function addHandle($ch)
    {
        if ( is_array($ch) ) { return $this->addHandles($ch); }

        if( ! gettype($ch) == 'resource' and ! get_resource_type($ch) == 'curl' )
        {
            throw new \Exception("`addHandle()` requires a valid curl resource to run.");
        }

        $this->handles[] = $ch;
        return $this;
    }

    /**
     * Add an array of cURL handles to the list of cURL handles to execute during a multi-request.
     *
     * @param $curlHandles
     * @return $this
     * @throws \Exception
     */
    public function addHandles($curlHandles)
    {
        if ( ! is_array($curlHandles) )
        {
            throw new \Exception("addHandles() takes an array of curl handles.");
        }

        foreach ( $curlHandles as $curlHandle ) { $this->addHandle($curlHandle); }

        return $this;
    }

    /**
     * Add a url to the list of urls to create cURL handles out of.
     *
     * These handles will inherit the same options and headers as
     * the parent cURL handle that was created and modified with
     * Curler.
     *
     * @param $url
     * @return $this
     */
    public function addUrl($url)
    {
        if ( is_array($url) ) { return $this->addUrls($url); }
        $this->urls[] = $url;
        return $this;
    }

    /**
     * Add an array of urls to the list of urls to create cURL handles out of.
     *
     * These handles will inherit the same options and headers as
     * the parent cURL handle that was created and modified with
     * Curler.
     *
     * @param $urls
     * @return $this
     * @throws \Exception
     */
    public function addUrls($urls)
    {
        if ( ! is_array($urls) )
        {
            throw new \Exception("addUrls() takes an array of URLs...");
        }

        foreach ( $urls as $url )
        {
            $this->addUrl($url);
        }
        return $this;
    }

    /**
     * Enable multi-cookies.  Each url or handle that is used in the multi-request will
     * have it's own cookie which is numbered after the index value of the corresponding
     * cURL handle in $this->handles.
     *
     * @param bool $bool
     * @return $this
     * @throws \Exception
     */
    public function multiCookie($bool=true)
    {
        if ( $this->cookieJarFile == '' )
        {
            throw new \Exception("You must set up the cookie jar before calling `multiCookie()`");
        }
        $this->multi_cookie = $bool;
        return $this;
    }

    /**
     * Start the cURL multi-request
     *
     * @return $this
     */
    public function go($timelimit=30, $memory='128M')
    {
        ini_set('memory_limit', $memory);
        set_time_limit($timelimit);

        // Instantiate cURL handle to act as template.
        curl_setopt($this->ch, CURLOPT_URL, $this->urls[0]);
        unset($this->urls[0]);

        // Set the headers and post fields like in the `go()` method.
        if ( ! $this->headers_set ) { $this->setHeaders(); }
        if ( ! $this->postfields_set ) { $this->setPostFields(); }

        // Add main cURL handle to the list of cURL handles
        $this->handles[] = $this->ch;

        // Take what we have in this->urls and generate handles.
        $this->generateHandlesFromUrls();
        // Take what we have in this->handles and add them to this->cmh
        $this->setupMultiHandles();

        $running = null;
        do {
            curl_multi_exec($this->cmh, $running);
        } while ( $running > 0 );

        // Set up this->multi-response
        $this->setupOutput();

        curl_multi_close($this->cmh);

        return $this;
    }

    /**
     * Copy the main cURL handle and change the URL.
     *
     * @param $url
     * @return $this
     */
    private function copyHandleChangeUrl($url)
    {
        $ch = $this->copy();
        curl_setopt($ch, CURLOPT_URL, $url);
        return $ch;
    }

    /**
     * Copy curl handle and replace the url option with the new url for each one in list.
     * This will retain the options that have been set.
     */
    private function generateHandlesFromUrls()
    {
        if ( ! count($this->urls) > 0) { return $this; }

        foreach ($this->urls as $urlKey => $url)
        {
            $this->handles[] = $this->copyHandleChangeUrl($url);
        }
        return $this;
    }

    /**
     * Set up the multi-handle.
     *
     * @return mixed
     */
    private function setupMultiHandles()
    {
        if ( ! count($this->handles) > 0 ) { return $this; }

        foreach ($this->handles as $handleKey => $handle)
        {
            // Should we use separate cookies for each request?
            if ($this->multi_cookie)
            {
                $this->additionalCookies[$handleKey] = "{$this->cookieJarFile}{$handleKey}";
                curl_setopt($handle, CURLOPT_COOKIEFILE, "{$this->cookieJarFile}{$handleKey}");
                curl_setopt($handle, CURLOPT_COOKIEJAR, "{$this->cookieJarFile}{$handleKey}");
            }

            // Add handle to the multi-handle
            curl_multi_add_handle($this->cmh, $handle);
        }
        return $this;
    }

    /**
     * Set up the output from all the responses on the handles.
     *
     * @return $this
     */
    private function setupOutput()
    {
        foreach ( $this->handles as $hKey => $handle )
        {
            // Should the output HTML be renderable?
            if ( $this->noRender and ! $this->write_responses )
            {
                $this->multi_response[$hKey] = htmlspecialchars(curl_multi_getcontent($handle));
            }
            // Should we write the responses to files instead of tracking them locally?
            elseif ( $this->write_responses )
            {
                $link = curl_getinfo($handle)['url'];
                $ext = $this->getFileExtension($link);
                $content = curl_multi_getcontent($handle);
                $id = md5($content);
                $filepath = "{$this->responses_filepath}{$id}.{$ext}";

                $this->writeResponse($filepath, $content);
            }
            else
            {
                $this->multi_response[$hKey] = curl_multi_getcontent($handle);
            }

            curl_multi_remove_handle($this->cmh, $handle);
        }
        return $this;
    }

    public function getFileExtension($link)
    {
        $linkList = explode('.', $link);
        $arrayLength =  count($linkList);
        return $linkList[$arrayLength - 1];
    }

    /**
     * Write the response to a file.
     *
     * @param $filepath
     * @param $response
     * @return $this
     */
    public function writeResponse($filepath, $response)
    {
        file_put_contents($filepath, $response);
        return $this;
    }

    /**
     * Tell AsyncCurler to write responses to files instead of tracking them in memory.
     *
     * @param $folderpath
     * @return $this
     */
    public function writeResponses($folderpath)
    {
        $this->responses_filepath = $folderpath;
        $this->write_responses = true;
        return $this;
    }
}
