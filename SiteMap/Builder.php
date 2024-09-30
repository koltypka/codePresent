<?php

namespace Project\SiteMap;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use DateTime;
use Project\Helpers\Tools;
use Project\Entity\Settings;
use Project\SiteMap\Collection\BitrixElementCollection;
use Project\SiteMap\Collection\BitrixSectionCollection;
use Project\SiteMap\Collection\CollectionInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

use const LIBXML_HTML_NOIMPLIED;
use const LIBXML_NOBLANKS;
use const LIBXML_NONET;
use const LIBXML_NOXMLDECL;
use const LIBXML_NSCLEAN;

class Builder
{
    private const ENCODING = 'UTF-8';
    private const XMLNS_URL = ['@xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9', 'replace' => ''];

    private const   SITE_MAP_FILE_PREFIX = 'sitemap-my-iblock-';
    private const   SITE_MAP_MAIN_FILE_NAME = 'sitemap.xml';

    private Settings $settings;

    private string $xmlHead = '';
    private string $xml = '';
    private string $urlTemplate = '';
    private string $rootPath = '';
    private array $arXmlSitemapNames = [];

    public function __construct(string $rootPath)
    {
        $this->settings = new Settings();

        $this->setRootPath($rootPath);
    }

    /**
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function run(): void
    {
        if (!$this->needBuilding()) {
            return;
        }

        $this->handleBitrix();

        if ($this->isSuccess()) {
            $this->createSiteMapIndexFile();
        }

        $this->settings->update(['IS_CHANGED' => false]);
    }

    private function setRootPath(string $rootPath): void
    {
        $this->rootPath = $rootPath;
    }

    /**
     * @param $defaultContext
     * @return XmlEncoder
     */
    private function initXmlEncoder($defaultContext): XmlEncoder
    {
        return new XmlEncoder($defaultContext);
    }

    /**
     * @return void
     */
    private function unsetXml(): void
    {
        $this->xml = '';
        $this->xmlHead = '';
    }

    /**
     * @param string $rootNodeName
     * @return $this
     */
    private function createHead(string $rootNodeName): static
    {
        $this->xmlHead = $this->initXmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => $rootNodeName,
            XmlEncoder::ENCODING => self::ENCODING,
            XmlEncoder::LOAD_OPTIONS =>  LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NSCLEAN | LIBXML_NOXMLDECL | LIBXML_HTML_NOIMPLIED ,

        ])->encode(self::XMLNS_URL, 'xml');

        return $this;
    }

    /**
     * @param array $responseResult
     * @param string $rootNodeName
     * @return $this
     */
    private function createBody(array $responseResult, string $rootNodeName): static
    {
        $xmlUrlEncoder = $this->initXmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => $rootNodeName,
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [
                XML_PI_NODE, //этот флаг убирает xml header
            ],
        ]);

        foreach ($responseResult as $item) {
            $this->xml .= $xmlUrlEncoder->encode($item, 'xml');
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function putBodyToHead(): static
    {
        $replace = $this->initXmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'replace',
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [
                XML_PI_NODE,
            ],
        ])->encode('', 'xml');

        $this->xml = mb_eregi_replace($replace, $this->xml, $this->xmlHead);

        return $this;
    }

    private function createXml(array $data, string $bodyRoot, string $headRoot): void
    {
        $this->unsetXml();

        $this
            ->createBody($data, $bodyRoot)
            ->createHead($headRoot)
            ->putBodyToHead()
        ;
    }

    /**
     * @param string $fileName
     * @return string|false
     */
    private function writeXmlToFile(string $fileName): string|false
    {
        $result = false;

        if (file_put_contents($this->rootPath . '/' . $fileName, $this->xml, LOCK_EX)) {
            $result = $fileName;
        } elseif (file_exists($this->rootPath . '/' . $fileName)) {
            unlink($this->rootPath. '/' . $fileName);
        }

        return $result;
    }

    /**
     * @return bool
     */
    private function createSiteMapIndexFile(): bool
    {
        $this->createXml($this->arXmlSitemapNames, 'sitemap', 'sitemapindex');

        return !empty($this->writeXmlToFile(self::SITE_MAP_MAIN_FILE_NAME));
    }

    /**
     * @param $result
     * @return array|false
     */
    private function prepareData($result): array|false
    {
        $return = false;

        if (!empty($result)) {
            $return = $result;
        }

        return $return;
    }

    /**
     * @param CollectionInterface $oBcollection
     * @return bool
     */
    private function createSiteMapFile(CollectionInterface $oBcollection): bool
    {
        $responseResult = $oBcollection->getPages();

        if (empty($responseResult)) {
            return false;
        }

        $this->createXml($responseResult, 'url', 'urlset');

        $fileName = $this->writeXmlToFile(self::SITE_MAP_FILE_PREFIX . $oBcollection->getPrimary() . '.xml');

        if ($fileName) {
            $this->arXmlSitemapNames[] = [
                'loc' => Tools::getBaseUrl() . '/' . $fileName,
                'lastmod' => (new DateTime('now'))->format('c'),
            ];
        }

        return !empty($fileName);
    }

    /**
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getData()
    {
        $return = $this->prepareData($this->settings->getElementValueByCode('IBLOCK_SITEMAP'));

        // Если не заданы инфоблоки для генерации
        if (!$return) {
            $this->settings->update(['IS_CHANGED' => false]);
            return false;
        }

        return $return;
    }

    private function handle(array $arIblocks): void
    {
        foreach ($arIblocks as $iblock) {
            $this->createSiteMapFile(new BitrixElementCollection($iblock['VALUE'], $iblock['DESCRIPTION']));
            $this->createSiteMapFile(new BitrixSectionCollection($iblock['VALUE'], $iblock['DESCRIPTION']));
        }
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    private function handleBitrix(): void
    {
        $arIblocks = $this->getData();
        $this->handle($arIblocks);
    }

    /**
     * Проверка, нужно ли генерировать sitemap
     * (Если были изменения, или файл не существует)
     * @return bool
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function needBuilding(): bool
    {
        return (!empty($this->settings->getElementValueByCode('IS_CHANGED'))
            || !file_exists($this->rootPath . '/' . self::SITE_MAP_MAIN_FILE_NAME));
    }

    /**
     * @return bool
     */
    private function isSuccess(): bool
    {
        return !empty($this->arXmlSitemapNames);
    }
}
