<?php

namespace Izifir\Sitemap\Sitemap;

use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;

class Generate
{
    public static function run()
    {
        $generateStatus = self::getStatus();

        if ($generateStatus['isRun'] != 'Y') {
            $generateStatus['isRun'] = 'Y';
            $generateStatus['action'] = 'categories';
            $generateStatus['page'] = 1;
        }
        switch ($generateStatus['action']) {
            case 'categories': // xml с товарными категориями
                self::catalogCategories();
                $generateStatus['action'] = 'activeElements';
                break;
            case 'activeElements': // xml с доступными товары
                self::catalogElements();
                $generateStatus['action'] = 'inActiveElements';
                break;
            case 'inActiveElements': // xml с недоступными товарами
                self::catalogElements('N');
                $generateStatus['action'] = 'other';
                break;
            case 'other': // xml с остальными ссылками
                self::otherMap();
                $generateStatus['action'] = 'index';
                break;
            case 'index':
                self::indexMap();
                $generateStatus['isRun'] = 'N';
                $generateStatus['endTime'] = time();
                break;
        }
        Option::set('izifir.sitemap', 'sitemap_generate_status', Json::encode($generateStatus));

        return $generateStatus;
    }

    /**
     * Формирует файл sitemap-category.xml
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\LoaderException
     */
    public static function catalogCategories()
    {
        Loader::includeModule('iblock');
        $iblockId = Option::get('izifir.sitemap', 'sitemap_goods_iblock', 0);
        if ($iblockId) {
            $serverName = Option::get('izifir.sitemap', 'sitemap_domain', Option::get('main', 'server_name', ''));
            $xml = new \DOMDocument('1.0', 'utf-8');
            $urlSet = $xml->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
            $xml->appendChild($urlSet);
            // Получим основные категории
            $sectionsIterator = \CIBlockSection::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
                false,
                ['ID', 'IBLOCK_ID', 'NAME', 'SECTION_PAGE_URL', 'TIMESTAMP_X']
            );
            while ($section = $sectionsIterator->GetNext(false, false)) {
                $sectionUrl = $serverName . $section['SECTION_PAGE_URL'];
                if (self::checkUrl($sectionUrl)) {
                    $lastModDate = DateTime::createFromPhp(new \DateTime($section['TIMESTAMP_X']));
                    $url = self::getUrlElement(
                        $xml,
                        $sectionUrl,
                        $lastModDate->format('c'),
                        '0.8'
                    );
                    $urlSet->appendChild($url);

                    $seoArticlesIterator = \CIBlockElement::GetList(
                        ['SORT' => 'AASC'],
                        [
                            'IBLOCK_ID' => '26',
                            'ACTIVE' => 'Y',
                            'PROPERTY_section' => $section['ID']
                        ],
                        false,
                        false,
                        ['ID', 'IBLOCK_ID', 'CODE', 'TIMESTAMP_X']
                    );
                    $seoArticlesIterator->SetUrlTemplates('/catalog/#ELEMENT_CODE#/');
                    while ($seoArticle = $seoArticlesIterator->GetNext()) {
                        $seoArticleLink = $serverName . $seoArticle['DETAIL_PAGE_URL'];
                        if (self::checkUrl($seoArticleLink)) {
                            $lastModDateArticle = DateTime::createFromPhp(new \DateTime($seoArticle['TIMESTAMP_X']));
                            $url = self::getUrlElement(
                                $xml,
                                $seoArticleLink,
                                $lastModDateArticle->format('c'),
                                '0.8'
                            );
                            $urlSet->appendChild($url);
                        }
                    }
                }
            }

            $xml->save($_SERVER['DOCUMENT_ROOT'] . '/sitemap-category.xml');
        }
    }

    /**
     * Формирует файлы sitemap-product-active.xml и sitemap-product-archive.xml
     * Файл определяется в зависимости от переданого параметра доступности товара
     * @param string $available Признак доступности товаров, может принимать значения Y (доступные товары) и N (не доступные)
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\LoaderException
     */
    public static function catalogElements($available = 'Y')
    {
        Loader::includeModule('iblock');
        $iblockId = Option::get('izifir.sitemap', 'sitemap_goods_iblock', 0);
        if ($iblockId) {
            $filter = [
                'iblockId' => $iblockId,
                'ACTIVE' => 'Y'
            ];
            if ($available) {
                if ($available !== 'Y')
                    $available = 'N';

                $filter['CATALOG_AVAILABLE'] = $available;
            }

            $serverName = Option::get('izifir.sitemap', 'sitemap_domain', Option::get('main', 'server_name', ''));
            $xml = new \DOMDocument('1.0', 'utf-8');
            $urlSet = $xml->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
            $xml->appendChild($urlSet);

            $elementsIterator = \CIBlockElement::GetList(
                ['SORT' => 'ASC'],
                $filter,
                false,
                false,
                ['ID', 'IBLOCK_ID', 'DETAIL_PAGE_URL', 'TIMESTAMP_X']
            );

            while ($element = $elementsIterator->GetNext()) {
                $elementUrl = $serverName . $element['DETAIL_PAGE_URL'];
                if (self::checkUrl($elementUrl)) {
                    $lastModDate = DateTime::createFromPhp(new \DateTime($element['TIMESTAMP_X']));
                    $priority = $available === 'Y' ? '0.7' : '0.5';
                    $url = self::getUrlElement(
                        $xml,
                        $elementUrl,
                        $lastModDate->format('c'),
                        $priority
                    );
                    $urlSet->appendChild($url);
                }
            }
            $fileName = '/sitemap-product-active.xml';
            if ($available != 'Y')
                $fileName = '/sitemap-product-archive.xml';

            $xml->save($_SERVER['DOCUMENT_ROOT'] . $fileName);
        }
    }

    public static function otherMap()
    {
        Loader::includeModule('iblock');
        $serverName = Option::get('izifir.sitemap', 'sitemap_domain', Option::get('main', 'server_name', ''));
        $xml = new \DOMDocument('1.0', 'utf-8');
        $urlSet = $xml->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $xml->appendChild($urlSet);

        // Добавим главную страницу
        $mainFile = new File($_SERVER['DOCUMENT_ROOT'] . '/index.php');
        $mainLastmod = DateTime::createFromTimestamp($mainFile->getModificationTime());
        $mainUrl = self::getUrlElement(
            $xml,
            $serverName,
            $mainLastmod->format('c'),
            '0.9'
        );
        $urlSet->appendChild($mainUrl);

        // Переберем все директории
        $rootDirectory = new Directory($_SERVER['DOCUMENT_ROOT']);
        $rootChildrenList = $rootDirectory->getChildren();
        foreach ($rootChildrenList as $rootChild) {
            if ($rootChild instanceof \Bitrix\Main\IO\Directory) {
                $rootChildUrl = self::processDirectory($rootChild, $xml);
                if ($rootChildUrl)
                    $urlSet->appendChild($rootChildUrl);
            }
        }

        $additionalIblocks = Json::decode(Option::get('izifir.sitemap', 'sitemap_additional_iblock', '{}'));
        $elementsIterator = \CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => $additionalIblocks, 'ACTIVE' => 'Y'],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'DETAIL_PAGE_URL', 'TIMESTAMP_X']
        );
        while ($element = $elementsIterator->GetNext()) {
            $elementLastModDate = DateTime::createFromPhp(new \DateTime($element['TIMESTAMP_X']));
            $elementLink = $serverName . $element['DETAIL_PAGE_URL'];
            if (self::checkUrl($elementLink)) {
                $urlSet->appendChild(self::getUrlElement(
                    $xml,
                    $elementLink,
                    $elementLastModDate->format('c'),
                    '0.5'
                ));
            }
        }

        $xml->save($_SERVER['DOCUMENT_ROOT'] . '/sitemap-other.xml');
    }

    /**
     * Формирует файл sitemap.xml
     *
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\IO\FileNotFoundException
     */
    public static function indexMap()
    {
        $fileList = self::getFileList();
        $serverName = Option::get('izifir.sitemap', 'sitemap_domain', Option::get('main', 'server_name', ''));
        $xml = new \DOMDocument('1.0', 'utf-8');
        $sitemapIndex = $xml->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'sitemapindex');
        $xml->appendChild($sitemapIndex);
        foreach ($fileList as $action => $fileName) {
            if ($action === 'index') continue;
            $ioFile = new File($_SERVER['DOCUMENT_ROOT'] . $fileName);
            if ($ioFile->isExists()) {
                $lastmodDate = DateTime::createFromTimestamp($ioFile->getModificationTime());
                $sitemap = $xml->createElement('sitemap');
                $loc = $xml->createElement('loc', $serverName . $fileName);
                $lastmod = $xml->createElement('lastmod', $lastmodDate->format('c'));
                $sitemap->appendChild($loc);
                $sitemap->appendChild($lastmod);
                $sitemapIndex->appendChild($sitemap);
            }
        }
        $xml->save($_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml');
    }

    /**
     * Возвращает общий список действий и соответсвующих файлов
     * @return string[]
     */
    public static function getFileList()
    {
        return [
            'categories' => '/sitemap-category.xml',
            'activeElements' => '/sitemap-product-active.xml',
            'inActiveElements' => '/sitemap-product-archive.xml',
            'other' => '/sitemap-other.xml',
            'index' => '/sitemap.xml',
        ];
    }

    /**
     * Возвращает текущий статус генерации карты
     * @return mixed
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    public static function getStatus()
    {
        $generateStatus = Option::get('izifir.sitemap', 'sitemap_generate_status', '{}');
        return Json::decode($generateStatus);
    }

    /**
     * Проверяет http код ответа, наличие мета-тега robots noindex
     * и нахождение в robots.txt
     * @param $url
     * @return bool
     */
    public static function checkUrl($url)
    {
        $error = false;
        // Проверим код ответа
        // TODO: что-то сделать с частыми запросами, чтобы сервак не выкидывал 503
        /*$httpClient = new HttpClient();
        $content = $httpClient->get($url);
        $error = $httpClient->getStatus() != 200;*/

        // Если с кодом все в порядке, проверим наличие мета-тега
        if (!$error) { // TODO: проверить алгоритм, сначала разобравшись с запросами
            /*preg_match_all('/\<meta.*?\>/mis',$content,$m);
            if ($m) {
                $error = strstr(join(',', $m[0]), 'noindex');
            }*/
        }

        // Если мета-тега так же нет, проверим закрытие в robots.txt
        if (!$error) {
            $serverName = Option::get('izifir.sitemap', 'sitemap_domain', Option::get('main', 'server_name', ''));
            $robotsContent = file_get_contents($serverName . '/robots.txt');
            preg_match_all("/Disallow: (.*?)\s\n/imU", $robotsContent, $matches);
            $robots = $matches[1];

            $f = substr($url, strlen($serverName)); // эта строка удалает домен из переданного URL

            foreach ($robots as $item) {
                if ($item == '/') continue;
                $item = str_replace("/", "\/", $item);
                $item = str_replace(
                    ['*',  '+',  '.',  '?'],
                    ['.*', '\+', '\.', '\?'],
                    $item
                );

                $error = preg_match('/^' . $item . '/', $f); // $-привязка к концу сработает автоматически

                if ($error) // Если нашли исключение, то дальше не продолжаем
                    break;
            }
        }

        if (!$error) {
            $excludedPaths = Json::decode(Option::get('izifir.sitemap', 'sitemap_excluded', '{}'));
            $f = substr($url, strlen($serverName)); // эта строка удалает домен из переданного URL
            foreach ($excludedPaths as $excludedPath) {
                $excludedPath = str_replace("/", "\/", $excludedPath);
                $error = preg_match('/^' . $excludedPath . '/', $f);
            }
        }

        return !$error;
    }

    protected static function processDirectory(Directory $directory, \DOMDocument $xml)
    {
        $indexFile = new File($directory->getPath() . '/index.php');
        if ($indexFile->isExists()) {
            $serverName = Option::get('izifir.sitemap', 'sitemap_domain', Option::get('main', 'server_name', ''));
            $path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $directory->getPath());
            $directoryUrl = $serverName . $path . '/';
            $lastModDate = DateTime::createFromTimestamp($indexFile->getModificationTime());
            if (self::checkUrl($directoryUrl)) {
                $url = self::getUrlElement(
                    $xml,
                    $directoryUrl,
                    $lastModDate->format('c'),
                    '0.5'
                );
                return $url;
            }
        }
        return false;
    }

    /**
     * @param \DOMDocument $xml
     * @param $loc
     * @param $lastmod
     * @param $priority
     * @param string $changefreq
     * @return \DOMElement|false
     */
    protected static function getUrlElement(\DOMDocument $xml, $loc, $lastmod, $priority, $changefreq = 'daily')
    {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc', $loc);
        $lastmod = $xml->createElement('lastmod', $lastmod);
        $changefreq = $xml->createElement('changefreq', $changefreq);
        $priority = $xml->createElement('priority', $priority);
        $url->appendChild($loc);
        $url->appendChild($lastmod);
        $url->appendChild($changefreq);
        $url->appendChild($priority);

        return $url;
    }
}
