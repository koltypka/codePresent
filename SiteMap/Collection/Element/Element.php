<?php

namespace Project\SiteMap\Collection\Element;

class Element
{
    private string $loc;
    private string $lastmod;

    public function __construct(string $loc, string $lastmod)
    {
        $this->loc = $loc;
        $this->lastmod = $lastmod;
    }

    public function setLoc(): string
    {
        return $this->loc;
    }

    public function setLastmod(): string
    {
        return $this->lastmod;
    }

    public function getElement(): array
    {
        return get_object_vars($this);
    }
}
