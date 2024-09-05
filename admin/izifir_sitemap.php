<?php
/**
 * @global $APPLICATION CMain
 */

use Bitrix\Main\Config\Option;

include($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

\Bitrix\Main\Loader::includeModule('izifir.sitemap');

$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$siteMapData = $request->getPost('Sitemap');
$generate = $request->get('generate');

if ($siteMapData && check_bitrix_sessid()) {
    foreach ($siteMapData as $field => $value) {
        if (is_array($value)) {
            $value = array_filter($value, function ($item) {
                return !empty($item);
            });
            $value = \Bitrix\Main\Web\Json::encode($value);
        }
        Option::set('izifir.sitemap', $field, $value);
    }
    if (empty($generate))
        LocalRedirect("/bitrix/admin/izifir_sitemap.php?lang=" . LANG);
}

if ($generate) {
    @set_time_limit(0);
    $generateStatus = \Izifir\Sitemap\Sitemap\Generate::run();
    if ($generateStatus['isRun'] != 'Y')
        LocalRedirect("/bitrix/admin/izifir_sitemap.php?lang=" . LANG);
}

$tabList = [
    [
        'DIV' => 'settings',
        'TAB' => 'Настройки',
        'ICON' => 'settings',
        'TITLE' => 'Параметры генерации карты сайта'
    ]
];

$tabControl = new CAdminTabControl("form_sitemap_settings", $tabList, false, true);


$values = [
    'sitemap_goods_iblock' => Option::get('izifir.sitemap', 'sitemap_goods_iblock', ''),
    'sitemap_domain' => Option::get('izifir.sitemap', 'sitemap_domain', ''),
    'sitemap_additional_iblock' => \Bitrix\Main\Web\Json::decode(Option::get('izifir.sitemap', 'sitemap_additional_iblock', '{}')),
    'sitemap_excluded' => \Bitrix\Main\Web\Json::decode(Option::get('izifir.sitemap', 'sitemap_excluded', '{}')),
];

$APPLICATION->SetTitle('Карта сайта');
include($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>

<?php
$progressItems = \Izifir\Sitemap\Sitemap\Generate::getFileList();

foreach ($progressItems as $action => &$item) {
    $item = str_replace('/', '', $item);
    if ($action == $generateStatus['action']) {
        $item = "<span style='display flex;align-items: center;'><b>{$item}</b>&nbsp;<img src='/local/modules/izifir.sitemap/admin/img/ajax-loader.gif' alt=''></span>";
    } else {
        $item = '<a href="/' . $item . '" target="_blank">' . $item . '</a>';
    }
}
if ($generateStatus['isRun'] != 'Y') {
    $generateStatus = \Izifir\Sitemap\Sitemap\Generate::getStatus();
    $lastDateTime = \Bitrix\Main\Type\DateTime::createFromTimestamp($generateStatus['endTime']);
    $progressItems[] = '<b>Время последней генерации: ' . $lastDateTime->format('d.m.Y, H:i:s') . '</b>';
}

CAdminMessage::ShowMessage(array(
    "DETAILS" => "<p>" . implode("</p><p>", $progressItems) . "</p>",
    "HTML" => true,
    "TYPE" => "PROGRESS",
    "PROGRESS_TOTAL" => '5',
    "PROGRESS_VALUE" => '5',
));
?>

<form method="POST" action="<? echo $APPLICATION->GetCurPage() ?>?">
    <input type="hidden" name="lang" value="<? echo LANG ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab() ?>
    <tr>
        <td width="40%">Домен:</td>
        <td width="60%">
            <input type="text" name="Sitemap[sitemap_domain]" value="<?= $values['sitemap_domain'] ?>">
        </td>
    </tr>
    <tr>
        <td width="40%">ID инфоблока с товарами:</td>
        <td width="60%">
            <input type="text" name="Sitemap[sitemap_goods_iblock]" value="<?= $values['sitemap_goods_iblock'] ?>">
        </td>
    </tr>
    <tr>
        <td width="40%" style="vertical-align: top;padding-top: 12px;">Другие инфоблоки:</td>
        <td width="60%">
            <div id="iq-sitemap-addit-ib">
                <?php foreach ($values['sitemap_additional_iblock'] as $k => $iblockId) : ?>
                    <input type="number" style="margin-bottom: 5px" class="adm-input" name="Sitemap[sitemap_additional_iblock][<?= $k ?>]" value="<?= $iblockId ?>"><br>
                <?php endforeach; ?>
                <input type="number" style="margin-bottom: 5px" class="adm-input" name="Sitemap[sitemap_additional_iblock][]"><br>
            </div>
            <input type="button" onclick="iqAddAdditInput()" value="Добавить">
            <script>
                function iqAddAdditInput()
                {
                    var input = document.createElement('input');
                    input.setAttribute('type', 'number');
                    input.setAttribute('name', 'Sitemap[sitemap_additional_iblock][]');
                    input.classList.add('adm-input');
                    input.style.marginBottom = '5px';
                    BX('iq-sitemap-addit-ib').appendChild(input)
                    BX('iq-sitemap-addit-ib').appendChild(document.createElement('br'));
                }
            </script>
        </td>
    </tr>
    <tr>
        <td width="40%" style="vertical-align: top;padding-top: 12px;">Исключить адреса из карты:</td>
        <td width="60%">
            <div id="iq-sitemap-excluded">
                <?php foreach ($values['sitemap_excluded'] as $k => $iblockId) : ?>
                    <input type="text" style="margin-bottom: 5px" name="Sitemap[sitemap_excluded][<?= $k ?>]" value="<?= $iblockId ?>"><br>
                <?php endforeach; ?>
                <input type="text" style="margin-bottom: 5px" name="Sitemap[sitemap_excluded][]"><br>
            </div>
            <input type="button" onclick="iqAddExcludedInput()" value="Добавить">
            <script>
                function iqAddExcludedInput()
                {
                    var input = document.createElement('input');
                    input.setAttribute('type', 'text');
                    input.setAttribute('name', 'Sitemap[sitemap_excluded][]');
                    input.style.marginBottom = '5px';
                    BX('iq-sitemap-excluded').appendChild(input)
                    BX('iq-sitemap-excluded').appendChild(document.createElement('br'));
                }
            </script>
        </td>
    </tr>

    <?php $tabControl->EndTab(); ?>
    <?php
    $tabControl->Buttons([
        'disabled' => false,
        'back_url' => '/bitrix/admin/exchange_profile_list.php',
        'btnApply' => false,
        'btnCancel' => false,
    ]);
    ?>
    <?php if ($generateStatus['isRun'] == 'Y') : ?>
    <input type="button" value="Карта генерируется">
        <script>setTimeout(function () {
                window.location.reload()
            }, 2000);</script>
    <?php else : ?>
    <input type="submit" name="generate" value="Генерировать карту">
    <?php endif ?>
    <?php $tabControl->End(); ?>
</form>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>
