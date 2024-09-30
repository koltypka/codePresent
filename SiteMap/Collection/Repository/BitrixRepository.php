<?php

namespace Project\SiteMap\Collection\Repository;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Iblock\Section;
use Project\Repository\Abstract\AbstractRepository;

//этот класс использует абстрактный репозиторий некоторого проекта,
//подразумевается возможность различной реалзиации этого класса
//TODO вовзращать массив объектов строго задного класса
class BitrixRepository extends AbstractRepository implements RepositoryIntarface
{
    //стандартные коды ИБ для генерации sitemap
    private const DEFAULT_CODES = [
        'LIST_PAGE_URL',
        'DETAIL_PAGE_URL',
        'SECTION_PAGE_URL',
        'CANONICAL_PAGE_URL'
    ];

    private const DEFAULT = 'DEFAULT';
    private const PROPERTY = 'PROPERTY';
    private const CUSTOM = 'CUSTOM';

    private array $selectFields = ['ID', 'TIMESTAMP_X'];
    private array $fillFields = [];
    private array $sections = [];

    private string $type = self::DEFAULT;
    private string $urlField = '';

    public function __construct(mixed $table, string $code, ?string $urlField = '')
    {
        parent::__construct($table, $code);
        $this->urlField = $urlField;

        $this->prepareFields();
    }

    /**
     * @param array|null $filter
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getList(?array $filter = []): array
    {
        $return = [];
        $collection = $this->get(['filter' => $filter, 'to_array' => true]);

        foreach ($collection->getElements() as $item) {
            $return[] = $this->formatter($item);
        }

        return $return;
    }

    /**
     * @param $item
     * @return array
     */
    public function formatter($item): array
    {
        switch ($this->type) {
            case self::DEFAULT:
                $item['LOC'] = $this->getUrl(
                    $item['ID'],
                    $item['CODE'],
                    !empty($item['IBLOCK_SECTION_ID']) ? $item['IBLOCK_SECTION_ID'] : 0,
                    !empty($this->sections[$item['IBLOCK_SECTION_ID']]['CODE']) ? $this->sections[$item['IBLOCK_SECTION_ID']]['CODE'] : '',
                );

                $item['LOC'] = urldecode($item['LOC']);

                break;
            case self::CUSTOM:
                if (empty($item[$this->urlField])) {
                    $item = [];
                    break;
                }

                $item['LOC'] = $item[$this->urlField];
                break;
            case self::PROPERTY:
                if (empty($item[$this->urlField . '_VALUE'])) {
                    $item = [];
                    break;
                }

                $item['LOC'] = $item[$this->urlField . '_VALUE'];
                break;
        }

        return $item;
    }

    /**
     * @return void
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function prepareFields(): void
    {
        if (empty($this->urlField) || in_array($this->urlField, self::DEFAULT_CODES)) {
            $this->selectFields = ['*'];
            $this->initUrlTemplate();

            return;
        }

        if (in_array($this->urlField, array_column($this->getProps(), 'CODE'))) {
            $this->selectFields = array_merge($this->selectFields, [$this->urlField . '_VALUE' => $this->urlField . '.VALUE']);
            $this->type = self::PROPERTY;

            return;
        }

        $this->selectFields = array_merge($this->selectFields, [$this->urlField]);
        $this->type = self::CUSTOM;

    }

    /**
     * @return array
     */
    protected function getSelectFields(): array
    {
        return $this->selectFields;
    }

    /**
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function initUrlTemplate(): void
    {
        $result = Section::getList(['select' => ['*'], 'filter' => ['IBLOCK_ID' => $this->getIblockId()]])->fetchAll();

        if (!empty($result)) {
            foreach ($result as $item) {
                $this->sections[$item['ID']] = $item;
            }
        }
    }

}
