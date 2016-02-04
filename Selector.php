<?php
namespace Wykleph\WebScraper;

class Selector
{
    public function __construct($selector)
    {
        $this->parentSelectors = $selector['parentSelectors'];
        $this->type = $selector['type'];
        $this->multiple = $selector['multiple'];
        $this->id = $selector['id'];
        $this->selector = $selector['selector'];
        $this->regex = $selector['regex'];
        $this->delay = $selector['delay'];
        return $this;
    }
}