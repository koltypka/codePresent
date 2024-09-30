<?php

namespace Project\AbstractRepositoryExample;

use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\Model\Section as ModelSection;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Expression;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Iblock\IblockTable;

abstract class AbstractRepository implements RepositoryInterface
{
    protected const CACHE_TIME = 3600;
    protected string $referenceClass = Reference::class;
    protected string $queryClass = Query::class;
    protected string $expressionClass = Expression::class;
    protected string $expressionFieldClass = ExpressionField::class;
    protected string $joinClass = Join::class;
    protected string $entityClass = Entity::class;
    protected string $propertyEnumerationTableClass = PropertyEnumerationTable::class;
    protected string $propertyTableClass = PropertyEnumerationTable::class;
    protected string $sectionTableClass = SectionTable::class;
    protected string $modelSectionClass = ModelSection::class;

    protected string $className = '';
    protected int $ibId = 0;

    protected Query $query;

    protected function __construct()
    {
        Loader::includeModule('iblock');
    }

    /**
     * @param string $iblockCode
     * @return void
     */
    protected function initQueryParams(string $iblockCode)
    {
        $this->ibId = IblockTable::getList(['select' => '*', 'filter' => ['CODE' => $iblockCode]]);
        $this->className = Iblock::wakeUp($this->ibId)->getEntityDataClass();
        $this->setQuery();
    }

    /** Конструктор запроса текущей сущности
     * @return Query
     */
    protected function getCurEntityQuery(): Query
    {
        return new $this->queryClass($this->className::getEntity());
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return int
     */
    public function getIblockId(): int
    {
        return $this->ibId;
    }

    /**
     * @param string $elementCode
     * @return int
     */
    public function getElementIdByCode(string $elementCode): int
    {
        $return = 0;

        $subQuery = $this->getCurEntityQuery();
        $subQuery
            ->addSelect('ID')
            ->where('CODE', '=', $elementCode)
        ;

        $queryResult = $subQuery->exec()->fetch();

        if (!empty($queryResult['ID'])) {
            $return = intval($queryResult['ID']);
        }

        return $return;
    }

    /**
     * @param array|null $arSelect
     * @return array|false
     */
    public function getIblock(?array $arSelect = [])
    {
        $subQuery = $this->getCurEntityQuery();

        if (!empty($arSelect)) {
            foreach ($arSelect as $selectField) {
                $subQuery->addSelect('TMP_IBLOCK_TABLE_ALIAS.' . $selectField, $selectField);
            }
        } else {
            $subQuery->addSelect('TMP_IBLOCK_TABLE_ALIAS.*', 'IBLOCK_TABLE_');
        }

        $subQuery
            ->where('TMP_IBLOCK_TABLE_ALIAS.ID', $this->ibId)
            ->registerRuntimeField(
                new $this->referenceClass(
                    'TMP_IBLOCK_TABLE_ALIAS',
                    IblockTable::class,
                    $this->joinClass::on('this.IBLOCK_ID', 'ref.ID')
                )
            )
            ->setCacheTtl(self::CACHE_TIME)
            ->setLimit(1)
        ;

        return $subQuery->exec()->fetch();
    }

    protected function getSelect(): array
    {
        return ['*'];
    }

    protected function getOrder(): array
    {
        return ['SORT' => 'ASC'];
    }

    abstract protected function setQuery(): void;

    abstract protected function add();
    abstract protected function update();
    abstract protected function delete();
}
