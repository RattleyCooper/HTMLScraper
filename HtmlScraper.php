<?php

namespace Wykleph\WebScraper;

use Symfony\Component\DomCrawler\Crawler;

/**
 * A class that consumed a SiteMap object and crawls html
 * based on said SiteMap object.
 *
 * Class HtmlScraper
 * @package Wykleph\WebScraper
 */
class HtmlScraper
{
    private $siteMap;
    private $html;
    public function __construct(ConsumesSiteMap $siteMap, $html)
    {
        $this->siteMap = $siteMap;
        $this->html = $html;
        $this->selections = $this->traverseSiteMap();
    }

    /**
     * Grab the selections that got scraped from the HTML.
     *
     * @return array
     */
    public function getSelections()
    {
        return $this->selections;
    }

    /**
     * Traverse the site map and return the selections.
     *
     * @return array
     */
    private function traverseSiteMap()
    {
        $sm = $this->siteMap;
        $selectors = $sm->selectors;

        $selections = [];
        foreach ( $selectors as $key=>$selector )
        {
            $selections[$key] = $this->cssSelect($selector);
        }
        return $selections;
    }

    /**
     * Return matching selections based on the CSS selector.
     *
     * @param $selector
     * @return array
     */
    private function cssSelect($selector)
    {
        $cssSelector = $selector->selector;

        $crawler = new Crawler($this->html);

        return $crawler->filter($cssSelector)
            ->each(function ( Crawler $node, $i ){
                return $node->text();
            });
    }
}