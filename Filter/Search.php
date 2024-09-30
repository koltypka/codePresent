<?php

namespace Project\Filter;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use CSearch;

class Search
{
    protected int $iblockId;
    protected string $siteId;
    protected string $moduleId = 'iblock';

    protected CSearch $obSearch;

    /**
     * @throws LoaderException
     */
    public function __construct(int $iblockId)
    {
        $this->iblockId = $iblockId;
        $this->siteId = SITE_ID;

        Loader::includeModule('search');

        $this->obSearch = new CSearch();
        $this->obSearch->SetOptions(['ERROR_ON_EMPTY_STEM' => false]);
    }

    /**
     * @param string $query
     * @return array
     */
    public function get(string $query): array
    {
        return $this->execSearch($query);
    }

    /**
     * @param string $query
     * @return array
     */
    protected function execSearch(string $query): array
    {
        //Если поиск произошёл, но ничего не нашли ставим -1, чтобы результат был пустым
        $return = [-1];

        $this->obSearch->Search(
            [
                "QUERY" => $query,
                "SITE_ID" => $this->siteId,
                "MODULE_ID" => $this->moduleId,
                'PARAM2' => $this->iblockId,
            ]
        );

        //делаем запрос, если нет результатов с включённой морфологией
        if (!$this->obSearch->selectedRowsCount()) {
            $this->obSearch->Search(
                [
                    'QUERY' => $query,
                    'SITE_ID' => $this->siteId,
                    'MODULE_ID' => $this->moduleId,
                    'PARAM2' => $this->iblockId
                ],
                [],
                ['STEMMING' => false]
            );
        }

        if ($this->obSearch->selectedRowsCount()) {
            while ($row = $this->obSearch->fetch()) {
                $return[] = $row;
            }

            $return = array_column($return, 'ITEM_ID');
        }

        return $return;
    }
}
