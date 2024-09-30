<?php

namespace Project\Scripts\SiteMap\Collection;

use Bitrix\Iblock\Iblock;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Project\Entity\NoIndex;
use Project\SiteMap\Collection\Element\Element;
use Project\SiteMap\Collection\Repository\BitrixRepository;
use Project\SiteMap\Collection\Repository\RepositoryIntarface;
use Project\SiteMap\UrlHelper;

class BitrixElementCollection implements CollectionInterface
{
    use UrlHelper;

    protected int $ibId = 0;
    protected string $className = '';

    private RepositoryIntarface $repository;

    /**
     * @throws LoaderException
     * @throws SystemException
     * @throws ArgumentException
     */
    public function __construct(string $id, string $urlField = '')
    {
        Loader::includeModule('iblock');

        $this->ibId = $id;
        $this->className = Iblock::wakeUp($this->ibId)->getEntityDataClass();
        $this->repository = $this->getRepository(
            $this->className,
            (new \Krit\Tools\TableQuery\Iblock\Iblock())->getOne(['ID' => $id])['CODE'],
            $urlField
        );
    }

    /**
     * @return array|false
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getPages(): array|false
    {
        $result = $this->getElemUrlInfo();

        return $this->prepareElemUrlInfo($result);
    }

    /**
     * @return int
     */
    public function getPrimary(): int
    {
        return $this->ibId;
    }

    /**
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getElemUrlInfo(): array
    {
        $filter = ['ACTIVE' => 'Y',];
        $arIndex = (new NoIndex($this->ibId))->getElements()->fetchAll();

        if (!empty($arIndex)) {
            $filter['@ID'] = array_column($arIndex, 'ID');
        }

        return $this->repository->getList($filter);
    }

    /**
     * @param array $result
     * @return array|false
     */
    private function prepareElemUrlInfo(array $result): array|false
    {
        $return = false;
        $result = array_diff($result, [null, false, '']);

        foreach ($result as $item) {
            if (empty($item['LOC']) || empty($item['TIMESTAMP_X'])) {
                continue;
            }

            $tmpItem = new Element(self::prepareUrl($item['LOC']), $item['TIMESTAMP_X']->format('c'));

            $return[] = $tmpItem->getElement();
        }

        return $return;
    }

    /**
     * Класс нужен, для получения sitemap даных из репозитория.
     * Может повторять уже существующие реализации этого репозитория.
     * @param mixed $table
     * @param string $code
     * @param string $urlField
     * @return RepositoryIntarface
     * @throws ArgumentException
     * @throws SystemException
     * @throws LoaderException
     */
    private function getRepository(mixed $table, string $code, string $urlField): RepositoryIntarface
    {
        return  new BitrixRepository($table, $code, $urlField);
    }
}
