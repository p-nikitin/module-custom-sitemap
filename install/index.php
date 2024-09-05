<?php

use Bitrix\Main\ModuleManager;

class izifir_sitemap extends CModule
{
    public $MODULE_ID = 'izifir.sitemap';

    public function __construct()
    {
        $arVersion = [];
        include (dirname(__FILE__) . '/version.php');

        $this->MODULE_VERSION = $arVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arVersion['VERSION_DATE'];

        $this->MODULE_NAME = 'IZIFIR карта сайта';
        $this->MODULE_DESCRIPTION = 'IZIFIR карта сайта';

        $this->PARTNER_NAME = 'IZIFIR';
        $this->PARTNER_URI = 'https://izifir.ru';
    }

    public function InstallFiles()
    {
        CopyDirFiles($_SERVER['DOCUMENT_ROOT'] . '/local/modules/izifir.sitemap/install/admin/', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/');
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles($_SERVER['DOCUMENT_ROOT'] . '/local/modules/izifir.sitemap/install/admin/', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');
    }

    public function DoInstall()
    {
        $this->InstallFiles();
        ModuleManager::registerModule($this->MODULE_ID);
    }

    public function DoUnInstall()
    {
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
