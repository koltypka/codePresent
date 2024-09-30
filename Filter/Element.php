<?php

namespace Project\Filter;

use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\ORM\Query;
use Bitrix\Main\ArgumentException;

class Element
{
    protected int $iblockId;
    protected Query $query;

    private string $prefix = '';
    private $entity;

    /**
     * @param int $iblockId
     * @noinspection PhpUndefinedMethodInspection
     */
    public function __construct(int $iblockId)
    {
        $this->iblockId = $iblockId;
        $dataClass = (new (Iblock::wakeUp($this->iblockId)->getEntityDataClass()));

        $this->entity = $dataClass->getEntity();
        $this->query = $this->entity->getDataClass()::query();
    }

    /**
     * @param array $filter
     * @return Query
     * @throws ArgumentException
     */
    public function get(array $filter)
    {
        $this->handleRequest($filter);

        return $this->buildQuery($filter);
    }

    /**
     * @param array $filter
     */
    private function handleRequest(array &$filter)
    {
        foreach ($filter as &$item) {
            if (is_string($item)) {
                $item = urldecode($item);
                $item = explode(',', $item);
            }
        }
    }

    /**
     * @param array $filter
     * @return Query
     * @throws ArgumentException
     */
    private function buildQuery(array &$filter)
    {
        $this->query->addSelect('ID');

        if (array_key_exists('ID', $filter)) {
            if (is_array($filter['ID'])) {
                $this->query->whereIn('ID', $filter['ID']);
            }

            unset($filter['ID']);
        }

        if (array_key_exists('CODE', $filter)) {
            if (is_array($filter['CODE'])) {
                $this->query->whereIn('CODE', $filter['CODE']);
            }

            unset($filter['CODE']);
        }

        //TODO если будет фильтр по штатным параметрам (name, description)
        foreach ($filter as $field => $arValues) {
            $this->prefix = '.VALUE';

            if (!$this->entity->hasField($field)) {
                $skipElement = $this->handlePrefix($field);

                if ($skipElement) {
                    continue;
                }
            }

            $queryFilter = Query::filter()->logic(Query::filter()::LOGIC_OR);

            foreach ($arValues as $value) {
                $queryFilter->where($field . $this->prefix, '=', $value);
            }

            $this->query->where($queryFilter);
        }

        return $this->query;
    }

    /**
     * @param string $field
     * @return bool
     */
    private function handlePrefix(string $field): bool
    {
        $skipElement = true;
        if (mb_ereg_match('(.)*.DESCRIPTION', $field)) {
            $tmpField = mb_ereg_replace('.DESCRIPTION', '', $field);
            if ($this->entity->hasField($tmpField)) {
                $currentFieldOb = $this->entity->getField($tmpField);
                if (method_exists($currentFieldOb, 'getIblockElementProperty')) {
                    if ($currentFieldOb->getIblockElementProperty()->get('WITH_DESCRIPTION')) {
                        $this->prefix = '';
                        $skipElement = false;
                    }
                }
            }
        }

        return $skipElement;
    }
}
