<?php

namespace Project\Helpers;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use CFile;
use Cutil;

class Tools
{
        /**
     * @return string
     */
    public static function getBaseUrl(): string
    {
        if (defined('BASE_URL')) {
            $return = BASE_URL;
        } else {
            $return = $_SERVER['HTTP_ORIGIN'];

            if (empty($return) && !empty($_SERVER['SERVER_NAME'])) {
                $return = 'https://' . $_SERVER['SERVER_NAME'];
            }

            if (empty($return)) {
                $return = 'https://' . SITE_SERVER_NAME;
            }
        }

        return $return;
    }

    /**
     * Получить ORM класс higload блока по имени таблицы
     * @param string $tableName
     * @return \Bitrix\Main\ORM\Data\DataManager|string
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getHlBlockEntityByTableName(string $tableName)
    {
        Loader::includeModule('highloadblock');

        $return = '';

        $res = HighloadBlockTable::getList(
            [
                'select' => ['ID'],
                'filter' => ['TABLE_NAME' => $tableName]
            ]
        );

        if ($res->getSelectedRowsCount() <= 0) {
            return $return;
        }

        $id = $res->fetch()['ID'];

        if ($id > 0) {
            $return = (new HighloadBlockTable())::compileEntity($id)->getDataClass();
        }

        return $return;
    }
}
