<?php

namespace Project\Scripts\SiteMap\Collection;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CIBlock;
use Project\Entity\NoIndex;
use Project\SiteMap\Collection\Element\Element;
use Bitrix\Iblock\SectionTable;
use Project\SiteMap\UrlHelper;

class BitrixSectionCollection implements CollectionInterface
{
    use UrlHelper;

    protected int $ibId = 0;

    public function __construct(string $id, string $urlField = '')
    {
        $this->ibId = $id;
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    public function getPages(): array|false
    {
        return $this->prepareData($this->getData());
    }

    public function getPrimary(): string
    {
        return $this->ibId . '_section';
    }

    /**
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getData(): array
    {
        $return = [];
        $result = SectionTable::query();
        $result
            ->addSelect('*')
            ->where('IBLOCK_ID', '=', $this->ibId)
            ->whereIn('ID', (new NoIndex($this->ibId))->getSections())
        ;


        $iblock = IblockTable::getList([
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
            'select' => ['*'],
            'filter' => ['ID' => $this->ibId],
            'limit' => 1,
            'cache' => [
                'ttl' => 360000,
                'cache_joins' => true,
            ]
        ]);

        $iblock = $iblock->fetch();

        $result = $result->fetchAll();

        foreach ($result as &$item) {
            $params = [
                'ID' => $item['ID'],
                'CODE' => $item['CODE'],
                'SECTION_ID' => $item['ID'],
                'SECTION_CODE' => $item['CODE'],
                'IBLOCK_SECTION_ID' => $item['ID'],
                'IBLOCK_TYPE_ID' => $iblock['IBLOCK_TYPE_ID'],
                'IBLOCK_ID' => $iblock['ID'],
                'IBLOCK_CODE' => $iblock['CODE'],
                'IBLOCK_EXTERNAL_ID' => $iblock['XML_ID'],
                'PAGE_URL' => $iblock['SECTION_PAGE_URL'],
            ];

            $item['LOC'] = CIBlock::ReplaceDetailUrl($iblock['SECTION_PAGE_URL'], $params, true, 'S');
        }

        if (!empty($result)) {
            $return = $result;
        }

        return $return;
    }

    /**
     * @param array $result
     * @return array|false
     */
    private function prepareData(array $result): array|false
    {
        $return = false;
        $result = array_diff($result, [null, false, '']);

        foreach ($result as $item) {
            if (empty($item['LOC']) || empty($item['TIMESTAMP_X'])) {
                continue;
            }

            $url = self::prepareUrl($item['LOC']);

            if (self::getUrlStatus($url) !== 200) {
                continue;
            }

            $tmpItem = new Element($url, $item['TIMESTAMP_X']->format('c'));

            $return[] = $tmpItem->getElement();
        }

        return $return;
    }
}
