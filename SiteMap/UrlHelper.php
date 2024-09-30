<?php

namespace Project\SiteMap;

use Project\Helpers\Tools;

trait UrlHelper
{
    /**
     * @param string $url
     * @return string
     */
    public static function prepareUrl(string $url): string
    {
        return Tools::getBaseUrl() . $url;
    }

    public static function getUrlStatus(string $url): int
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return intval($status);
    }
}
