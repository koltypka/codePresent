<?php

namespace Project\Scripts\SiteMap\Collection;

interface CollectionInterface
{
    /**
     * @return array|false
     */
    public function getPages(): array|false; //getElemUrlInfo
    public function getPrimary(); //getIblockId
}
