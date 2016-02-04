<?php
/**
 * Created by PhpStorm.
 * User: parkeryoung
 * Date: 2/3/16
 * Time: 9:54 PM
 */

namespace Wykleph\WebScraper;

interface ConsumesSiteMap
{
    public function spawnSelectors($selectors);
    public function getSelectors();
    public function getId();
    public function getStartUrl();
}