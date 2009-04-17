<?php
require_once 'Diggin/Scraper.php';


function docomo_device($value)
{
    $value = mb_ereg_replace('\n', '', (string)$value);
    $value = str_replace('&nbsp;', '', $value);

    preg_match('/([^（]+)(（(.*)）)?/iu', $value, $match);
    $device = str_replace('&mu;', 'μ', isset($match[2]) ? $match[3] : $match[1]);
    $device = str_replace('II S', 'IIS', $device);

    return $device;
}

function flash_version_docomo()
{
    try {
        $url = 'http://www.nttdocomo.co.jp/service/imode/make/content/spec/useragent/index.html';

        $profile = new Diggin_Scraper();
        $profile->process('/td[not(@scope) and not(@class="acenter middle")][1]/span', "device => RAW, docomo_device");
        $section = new Diggin_Scraper();
        $section->process('//table/tr', array('profile[]' => $profile));
        $scraper = new Diggin_Scraper();
        $scraper->process('div.boxArea > div.wrap > div.section', array('section[]' => $section))
                ->scrape($url);
    } catch (Exception $e) {
        throw $e;
    }

    $result = array();
    foreach ($scraper->section as $section) {
        foreach ($section['profile'] as $profile) {
            $result[$profile['device']] = array(
                'carrier' => 'docomo',
                'device'  => $profile['device'],
                'flash'   => '',
            );
        }
    }


    try {
        $url = 'http://www.nttdocomo.co.jp/service/imode/make/content/spec/useragent/index.html';

        $profile = new Diggin_Scraper();
        $profile->process('/td[last()-6]/span', "device => RAW, docomo_device");
        $section = new Diggin_Scraper();
        $section->process('div.titlept01 a', "version => TEXT")
                ->process('//table/tr[not(@class="brownLight acenter middle")]', array('profile[]' => $profile));
        $scraper = new Diggin_Scraper();
        $scraper->process('div.boxArea > div.wrap > div.section', array('section[]' => $section))
                ->scrape($url);
    } catch (Exception $e) {
        throw $e;
    }


    try {
        $url = 'http://www.nttdocomo.co.jp/service/imode/make/content/spec/flash/index.html';

        $profile = new Diggin_Scraper();
        $profile->process('/td[last()-6]/span', "device => RAW, docomo_device");
        $section = new Diggin_Scraper();
        $section->process('div.titlept01 a', "version => TEXT")
                ->process('//table/tr[not(@class="brownLight acenter middle")]', array('profile[]' => $profile));
        $scraper = new Diggin_Scraper();
        $scraper->process('div.boxArea > div.wrap > div.section', array('section[]' => $section))
                ->scrape($url);
    } catch (Exception $e) {
        throw $e;
    }

    $flash_version = null;
    foreach ($scraper->section as $section) {
        $flash_version = preg_replace('/(Flash Lite) ?([\d\.]+)/', '\1 \2', $section['version']);

        foreach ($section['profile'] as $profile) {
            $result[$profile['device']]['flash'] = $flash_version;
        }
    }

    return $result;
}


function softbank_device($value)
{
    return mb_ereg_replace('\?', 'II', $value);
}

function softbank_flash($value)
{
    return preg_replace(array('/×/u', '/&trade;/'), array('', ' '), $value);
}

function flash_version_softbank()
{
    try {
        $url = 'http://creation.mb.softbank.jp/terminal/?lup=y&cat=service';

        $profile = new Diggin_Scraper();
        $profile->process('td[1]', "device => TEXT, softbank_device")
                ->process('td[4]', "flash => TEXT, softbank_flash");
        $scraper = new Diggin_Scraper();
        $scraper->process('//tr[@bgcolor="#FFFFFF"]', array('profile[]' => $profile))
                ->scrape($url);
    } catch (Exception $e) {
        throw $e;
    }

    $result = array();
    foreach ($scraper->profile as $profile) {
        $result[] = array(
            'carrier' => 'softbank',
            'device'  => $profile['device'],
            'flash'   => $profile['flash'],
        );
    }

    return $result;
}


function au_flash($value)
{
    $flash_version = array(
        "●" => 'Flash Lite 2.0',
        "◎" => 'Flash Lite 1.1',
        "○" => 'Flash Lite 1.1',
        "−" => '',
    );

    return $flash_version[$value];
}

function au_device($value)
{
    return preg_replace('/^.*ケータイ\s*/', '', $value);
}

function flash_version_au()
{
    try {
        $url = 'http://www.au.kddi.com/ezfactory/tec/spec/new_win/ezkishu.html';

        $profile = new Diggin_Scraper();
        $profile->process('/td[1]', "device => TEXT")
                ->process('/td[12]', "flash => TEXT, au_flash");
        $scraper = new Diggin_Scraper();
        $scraper->process('//table[@width="892"]//tr[@bgcolor="#FFFFFF"]', array('profile[]' => $profile))
                ->scrape($url);
    } catch (Exception $e) {
        throw $e;
    }

    $result = array();
    foreach ($scraper->profile as $profile) {
        $result[] = array('carrier' => 'au') + $profile;
    }

    try {
        $url = 'http://www.au.kddi.com/decorations_anime/taiou.html';

        $scraper = new Diggin_Scraper();
        $scraper->process('.linkListHorizontal02 a', "device[] => TEXT, au_device")
                ->scrape($url);
    } catch (Exception $e) {
        throw $e;
    }

    foreach ($scraper->device as $device_) {
        foreach ($result as &$device) {
            if ($device['device'] === $device_) {
                $device['flash'] = 'Flash Lite 3.0';
                continue 2;
            }
        }

        $result[] = array(
            'carrier' => 'au',
            'device'  => $device_,
            'flash'   => 'Flash Lite 3.0',
        );
    }

    return $result;
}


try {
    $result = array_merge(
        flash_version_docomo(),
        flash_version_softbank(),
        flash_version_au()
    );

    echo '"'.implode('","', array_keys(reset($result))).'"'.PHP_EOL;
    foreach ($result as $row) {
        echo mb_convert_encoding('"'.implode('","', $row).'"', 'sjis-win', 'utf8').PHP_EOL;
    }

} catch (Exception $e) {
    print $e.PHP_EOL;
}
