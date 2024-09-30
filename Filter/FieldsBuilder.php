<?php

namespace Project\Filter;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\ORM\Query;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionPropertyTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use Project\Helpers\Tools;

class FieldsBuilder
{
    public const DESCRIPTION_SUFFIX = '_DESCRIPTION';

    protected const EXCLUDE_USER_PROPS_TYPE = ['HTML'];

    protected const DIRECTORY_2_DIRECTORY = 'directory2directory';

    protected const DIRECTORY = 'directory';
    protected const LIST = 'L';
    protected const STRING = 'S';

    protected int $iblockId;
    protected array $excludePropCode = [];
    protected array $addDescriptionPropCode = [];

    protected Query $fieldsValuesQuery;

    private array $arProperty = [];

    /**
     * @throws LoaderException
     */
    public function __construct(int $iblockId)
    {
        $this->iblockId = $iblockId;

        Loader::includeModule('iblock');
    }

    /**
     * Установить коды свойств, что исключить
     * @param array $excludePropCode
     * @return $this
     */
    public function setExcludePropCode(array $excludePropCode): static
    {
        $this->excludePropCode = $excludePropCode;

        return $this;
    }

    /**
     * Добавить код свойства для исключения
     * @param string $code
     * @return $this
     */
    public function addExcludePropCode(string $code): static
    {
        $this->excludePropCode[] = $code;

        $this->excludePropCode = array_unique($this->excludePropCode);

        return $this;
    }

    /**
     * Добавить массив кодов свойств для исключения
     * @param array $excludePropCode
     * @return $this
     */
    public function addListExcludePropCode(array $excludePropCode): static
    {
        $this->excludePropCode = array_unique(array_merge($this->excludePropCode, $excludePropCode));

        return $this;
    }

    /**
     * Добавить код свойства для получения описания
     * @param array $addDescriptionPropCode
     * @return $this
     */
    public function setDescriptionPropCode(array $addDescriptionPropCode): static
    {
        $this->addDescriptionPropCode = $addDescriptionPropCode;

        return $this;
    }

    /**
     * @return array
     * @throws ArgumentException
     * @throws SystemException
     * @throws LoaderException
     * @throws ObjectPropertyException
     */
    public function get(): array
    {
        $return = [];

        $this->arProperty = $this->getFilteredProperties();

        if (empty($this->arProperty)) {
            return $return;
        }

        $this->getPropValues();

        $return = $this->arProperty;

        foreach ($return as $code => &$property) {
            if (empty($property['VALUE'])) {
                unset($return[$code]);
                continue;
            }

            $property['VALUE'] = array_values($property['VALUE']);
        }

        return $return;
    }

    /**
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function getFilteredProperties(): array
    {
        $return = [];

        $obQuery = SectionPropertyTable::query();

        $obQuery
            ->setOrder(['SORT' => 'ASC', 'ID' => 'DESC'])
            ->setSelect(
                [
                    'ID' => 'PROPERTY_ID',
                    'PROPERTY_TYPE' => 'PROPERTY_TABLE.PROPERTY_TYPE',
                    'CODE' => 'PROPERTY_TABLE.CODE',
                    'SORT' => 'PROPERTY_TABLE.SORT',
                    'NAME' => 'PROPERTY_TABLE.NAME',
                    'TYPE' => 'PROPERTY_TABLE.PROPERTY_TYPE',
                    'MULTIPLE' => 'PROPERTY_TABLE.MULTIPLE',
                    'USER_TYPE' => 'PROPERTY_TABLE.USER_TYPE',
                    'USER_TYPE_SETTINGS' => 'PROPERTY_TABLE.USER_TYPE_SETTINGS',
                    'DESCRIPTION' => 'PROPERTY_TABLE.WITH_DESCRIPTION',
                    'DISPLAY_TYPE',
                    'DISPLAY_EXPANDED',
                    'FILTER_HINT',

                ]
            )
            ->where('IBLOCK_ID', '=', $this->iblockId)
            ->whereColumn('CODE', '=', 'PROPERTY_TABLE.CODE')
            ->whereNotNull('CODE')
            ->where('SMART_FILTER', '=', 'Y')
            ->where(
                Query::filter()
                    ->logic(Query::filter()::LOGIC_OR)
                    ->whereNotIn('USER_TYPE', self::EXCLUDE_USER_PROPS_TYPE)
                    ->whereNull('USER_TYPE')
            )
            ->registerRuntimeField(
                new Reference(
                    'PROPERTY_TABLE',
                    PropertyTable::class,
                    Join::on('this.PROPERTY_ID', 'ref.ID')
                )
            )
            ->countTotal(true)
        ;

        if (!empty($this->excludePropCode)) {
            $obQuery->whereNotIn('CODE', $this->excludePropCode);
        }

        if ($obQuery->exec()->getCount() > 0) {
            foreach ($obQuery->fetchAll() as $arProperty) {
                $return[$arProperty['CODE']] = [
                    'ID' => $arProperty['ID'],
                    'CODE' => $arProperty['CODE'],
                    'NAME' => $arProperty['NAME'] ?? '',
                    'TYPE' => $arProperty['TYPE'] ?? '',
                    'DISPLAY_TYPE' => $arProperty['DISPLAY_TYPE'] ?? '',
                    'DISPLAY_EXPANDED' => $arProperty['DISPLAY_EXPANDED'] ?? '',
                    'FILTER_HINT' => $arProperty['FILTER_HINT'] ?? '',
                    'MULTIPLE' => $arProperty['MULTIPLE'] ?? false,
                    'USER_TYPE' => $arProperty['USER_TYPE'] ?? '',
                    'USER_TYPE_SETTINGS' => $arProperty['USER_TYPE_SETTINGS'] ?? '',
                    'DESCRIPTION' => ($arProperty['DESCRIPTION'] ?? '') === 'Y',
                    'PARENT_FIELDS' => [],
                ];
            }
        }

        return $return;
    }

    /**
     * @return void
     * @throws ArgumentException
     * @throws SystemException
     * @throws LoaderException
     * @throws ObjectPropertyException
     */
    private function getPropValues(): void
    {
        $query = $this->preparePropValuesQuery();

        $this->preparePropDescription();

        //обрабатываем полученные данные
        foreach ($query->fetchAll() as $key => $item) {
            foreach ($this->arProperty as &$property) {
                if (empty($item['PROPERTY_' . $property['CODE']])) {
                    continue;
                }
                $property['VALUE'][$item['PROPERTY_' . $property['CODE']]] = [
                    'NAME' => $item['PROPERTY_' . $property['CODE'] . '_VALUE'] ?? $item['PROPERTY_' . $property['CODE']],
                    'VALUE' => $item['PROPERTY_' . $property['CODE']],
                    'SORT' => $item['PROPERTY_' . $property['CODE'] . '_SORT'] ?? $key,
                ];
            }
        }
    }

    /**
     * Метод собирает query для получения свойств
     * @return Query
     * @throws ArgumentException
     * @throws SystemException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @noinspection PhpUndefinedMethodInspection
     */
    private function preparePropValuesQuery(): Query
    {
        $arEnum = [];
        $arHlBlock = [];

        //собираем информацию о свойствах
        foreach ($this->arProperty as $propertyData) {
            switch ($propertyData['TYPE']) {
                case self::LIST:
                    //получаем id св-в типа список
                    $arEnum[] = $propertyData['ID'];
                    break;

                case self::STRING:
                    if (
                        in_array($propertyData['USER_TYPE'], [self::DIRECTORY, self::DIRECTORY_2_DIRECTORY])
                        && !empty($propertyData['USER_TYPE_SETTINGS'])
                    ) {
                        //получаем id св-в типа справочник
                        $arHlBlock[] = $propertyData['ID'];
                    }
                    break;

                default:
                    break;
            }
        }

        //получаем Entity инфоблока
        $this->fieldsValuesQuery = (new (Iblock::wakeUp($this->iblockId)->getEntityDataClass()))::query();
        $this->fieldsValuesQuery
            ->countTotal(true)
            ->whereIn('ID', $this->getSubQuery())
        ;

        //собираем запрос
        foreach ($this->arProperty as $property) {
            if (
                !is_array($property)
                || empty($property['CODE'])
                || empty($property['ID'])
            ) {
                continue;
            }

            $this->fieldsValuesQuery->addSelect($property['CODE'] . '.VALUE', 'PROPERTY_' . $property['CODE']);

            if (!empty($property['DESCRIPTION'])) {
                $this->fieldsValuesQuery->addSelect($property['CODE'] . '.DESCRIPTION', 'PROPERTY_' . $property['CODE'] . self::DESCRIPTION_SUFFIX);
            }

            if (in_array($property['ID'], $arEnum)) {
                $this->handleEnumQuery($property);
                continue;
            }

            if (in_array($property['ID'], $arHlBlock)) {
                $this->handleHlBlockQuery($property);
                continue;
            }
            $this->fieldsValuesQuery->addOrder('PROPERTY_' . $property['CODE'], 'ASC');
        }

        return $this->fieldsValuesQuery;
    }

    /**
     * Добавление значений св-ва список в запрос
     * @param array $property
     * @return void
     * @throws ArgumentException
     * @throws SystemException
     */
    private function handleEnumQuery(array $property): void
    {
        $this->fieldsValuesQuery->registerRuntimeField(
            (new Reference(
                'ENUM_' . $property['CODE'],
                PropertyEnumerationTable::class,
                Join::on('this.PROPERTY_' . $property['CODE'], 'ref.ID')
                    ->where('ref.PROPERTY_ID', '=', $property['ID'])
            ))->configureJoinType('left')
        )
            ->addSelect('ENUM_' . $property['CODE'] . '.VALUE', 'PROPERTY_' . $property['CODE'] . '_VALUE')
            ->addOrder('ENUM_' . $property['CODE'] . '.SORT', 'ASC')
            ->addOrder('PROPERTY_' . $property['CODE'] . '_VALUE', 'ASC')
        ;
    }

    /**
     * Добавление значений св-ва Справочник в запрос
     * @param array $property
     * @return void
     * @throws ArgumentException
     * @throws SystemException
     * @throws LoaderException
     * @throws ObjectPropertyException
     */
    protected function handleHlBlockQuery(array $property): void
    {
        if (!is_string($property['USER_TYPE_SETTINGS'])) {
            return;
        }

        $settings = unserialize($property['USER_TYPE_SETTINGS']);

        if (!is_array($settings)) {
            return;
        }

        if (empty($settings['TABLE_NAME'])) {
            return;
        }

        $this->getHlBlockQuery($property['CODE'], $settings['TABLE_NAME']);
    }

    /**
     * @param string $referenceCode
     * @param string $tableName
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function getHlBlockQuery(string $referenceCode, string $tableName): void
    {
        $hlbClass = Tools::getHlBlockEntityByTableName($tableName);

        if (!class_exists($hlbClass)) {
            return;
        }

        $joinRef = 'ID';
        $hlBEntity = (new $hlbClass())->getEntity();

        if ($hlBEntity->hasField('UF_XML_ID')) {
            if ($hlBEntity->getField('UF_XML_ID')->isRequired()) {
                $joinRef = 'UF_XML_ID';
            }
        }

        $this->fieldsValuesQuery->registerRuntimeField(
            (new Reference(
                'HL_B' . $referenceCode,
                (new $hlbClass())::class,
                Join::on('this.PROPERTY_' . $referenceCode, 'ref.' . $joinRef)
            ))->configureJoinType('left')
        );

        if ($hlBEntity->hasField('UF_SORT')) {
            $this->fieldsValuesQuery->addOrder('HL_B' . $referenceCode . '.UF_SORT', 'ASC');
            $this->fieldsValuesQuery->addSelect('HL_B' . $referenceCode . '.' . 'UF_SORT', 'PROPERTY_' . $referenceCode . '_SORT');
        }

        if ($hlBEntity->hasField('UF_NAME')) {
            $this->fieldsValuesQuery->addOrder('HL_B' . $referenceCode . '.UF_NAME', 'ASC');
        }

        $hlBField = 'ID';

        if ($hlBEntity->hasField('UF_NAME')) {
            $hlBField = 'UF_NAME';
        }

        $this->fieldsValuesQuery->addSelect('HL_B' . $referenceCode . '.' . $hlBField, 'PROPERTY_' . $referenceCode . '_VALUE');
    }

    /**
     * @return void
     */
    private function preparePropDescription(): void
    {
        foreach ($this->addDescriptionPropCode as $propertyKey) {
            if (!array_key_exists($propertyKey, $this->arProperty)) {
                continue;
            }

            if (!$this->arProperty[$propertyKey]['DESCRIPTION']) {
                continue;
            }
            //убираю элемент с его текущего места, записываю во временную переменную
            $tmpProperty = $this->arProperty[$propertyKey];
            unset($this->arProperty[$propertyKey]);
            //записываю элемент на последнее место
            $this->arProperty[$propertyKey] = $tmpProperty;

            //записываю временный массив в описание поля, отдельным филдом сразу после элемента
            $this->arProperty[$propertyKey . self::DESCRIPTION_SUFFIX] = $tmpProperty;
            //изменяю символьный код филда
            $this->arProperty[$propertyKey . self::DESCRIPTION_SUFFIX]['CODE'] = $propertyKey . self::DESCRIPTION_SUFFIX;
            //записываю символьный код родительского филда
            $this->arProperty[$propertyKey . self::DESCRIPTION_SUFFIX]['PARENT_FIELDS'][] = $propertyKey;
        }
    }

    /**
     * Получение подзапроса
     * @return Query
     * @throws ArgumentException
     * @throws SystemException
     */
    protected function getSubQuery(): Query
    {
        return (new Query(ElementTable::getEntity()))
            ->addSelect('ID')
            ->where('IBLOCK_ID', $this->iblockId)
            ->where('ACTIVE', 'Y')
        ;
    }
}
