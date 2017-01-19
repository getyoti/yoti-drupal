<?php
require_once __DIR__ . '/YotiConnectHelper.php';

function yoti_connect_link()
{
    $helper = new YotiConnectHelper();
    // todo: remove on live
    if (!array_key_exists('token', $_GET))
    {
        if (YotiConnectHelper::mockRequests())
        {
            $token = file_get_contents(__DIR__ . '/sdk/sample-data/connect-token.txt');
            return drupal_goto("/yoti-connect/link", array('query' => ["token" => $token]));
        }
        return drupal_goto(YotiConnectHelper::getLoginUrl());
    }

//    cache_clear_all('dynamic_page_cache');
//    cache_clear_all('render');

    $helper->link();
    return drupal_goto('/user');
}

function yoti_connect_unlink()
{
//    cache_clear_all('dynamic_page_cache');
//    cache_clear_all('render');

    $helper = new YotiConnectHelper();
    $helper->unlink();
    return drupal_goto('/');
}

function yoti_connect_binFile()
{
    global $user;

    $current = $user;
    $isAdmin = in_array('administrator', $current->roles);
    $userId = (!empty($_GET['user_id']) && $isAdmin) ? (int)$_GET['user_id'] : $current->uid;
    $tableName = YotiConnectHelper::tableName();
    $dbProfile = db_query("SELECT * from `{$tableName}` WHERE uid=$userId")->fetchAssoc();
    if (!$dbProfile)
    {
        return;
    }

    $dbProfile = unserialize($dbProfile['data']);

    $field = null;
    if (!empty($_GET['field']))
    {
        $field = $_GET['field'];
    }

    $field = ($field == 'selfie') ? 'selfie_filename' : $field;
    if (!$dbProfile || !array_key_exists($field, $dbProfile))
    {
        return;
    }

    $file = YotiConnectHelper::uploadDir() . "/{$dbProfile[$field]}";
    if (!file_exists($file))
    {
        return;
    }

    $type = 'image/png';
    header('Content-Type:' . $type);
    header('Content-Length: ' . filesize($file));
    readfile($file);
}