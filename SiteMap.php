<?php

namespace Wykleph\WebScraper;

/**
 * A class for consuming webscraper.io output json and
 * converting it to an object.
 *
 * Class SiteMap
 * @package Wykleph\WebScraper
 */
class SiteMap implements ConsumesSiteMap
{
    private $siteMap;
    public $id;
    public $startUrl;
    public $selectors;

    public function __construct($jsonData)
    {
        $this->siteMap = json_decode($jsonData, true);
        if ( ! is_array($this->siteMap) )
        {
            $err = "Valid json data must be passed ";
            throw new \InvalidArgumentException();
        }
        $this->id = $this->siteMap['_id'];
        $this->startUrl = $this->siteMap['startUrl'];
        $this->selectors = $this->spawnSelectors($this->siteMap['selectors']);

        return $this;
    }

    /**
     * Create selector objects out of the initial siteMap array.
     *
     * @param $selectors
     * @return array
     */
    public function spawnSelectors($selectors)
    {
        $selectorObjects = [];
        foreach ( $selectors as $key=>$selector )
        {
            $selectorObjects[$selector['id']] = new Selector($selector);
        }
        return $selectorObjects;
    }

    /**
     * Return an array of selector objects found in this SiteMap.
     *
     * @return array
     */
    public function getSelectors()
    {
        return $this->selectors;
    }

    /**
     * Return the StartUrl for this SiteMap
     *
     * @return mixed
     */
    public function getStartUrl()
    {
        return $this->startUrl;
    }

    /**
     * Return the named Id of this SiteMap
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}