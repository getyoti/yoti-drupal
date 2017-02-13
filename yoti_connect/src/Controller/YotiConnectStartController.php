<?php
namespace Drupal\yoti_connect\Controller;

use Drupal;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\user\UserInterface;
use Drupal\yoti_connect\YotiConnectHelper;

require_once __DIR__ . '/../YotiConnectHelper.php';
require_once __DIR__ . '/../../sdk/boot.php';

/**
 * Class YotiConnectStartController
 *
 * @package Drupal\yoti_connect\Controller
 * @author Simon Tong <simon.tong@yoti.com>
 */
class YotiConnectStartController extends ControllerBase
{
    /**
     * Link account
     */
    public function link()
    {
        /** @var YotiConnectHelper $helper */
        $helper = \Drupal::service('yoti_connect.helper');

        // todo: remove on live
        if (!array_key_exists('token', $_GET))
        {
            if (YotiConnectHelper::mockRequests())
            {
                $token = file_get_contents(__DIR__ . '/../../sdk/sample-data/connect-token.txt');
                return $this->redirect('yoti_connect.link', ['token' => $token]);
            }
            return new TrustedRedirectResponse($helper::getLoginUrl());
        }

        $this->cache('dynamic_page_cache')->deleteAll();
        $this->cache('render')->deleteAll();

        $helper->link();
        return $this->redirect('user.login');
    }

    /**
     * Unlink account
     */
    public function unlink()
    {
        /** @var YotiConnectHelper $helper */
        $helper = \Drupal::getContainer()->get('yoti_connect.helper');

        $this->cache('dynamic_page_cache')->deleteAll();
        $this->cache('render')->deleteAll();

        $helper->unlink();
        return $this->redirect('user.login');
    }

    /**
     * Send binary file from yoti
     */
    public function binFile($field)
    {
        /** @var YotiConnectHelper $helper */
        $helper = \Drupal::getContainer()->get('yoti_connect.helper');
        $user = Drupal::currentUser();
        if (!$user)
        {
            return;
        }

        $field = ($field == 'selfie') ? 'selfie_filename' : $field;
        $dbProfile = Drupal::service('user.data')->get('yoti_connect', $user->id());
        if (!$dbProfile || !array_key_exists($field, $dbProfile))
        {
            return;
        }

        $file = $helper::uploadDir() . "/{$dbProfile[$field]}";
        if (!file_exists($file))
        {
            return;
        }

        $type = 'image/png';
        header('Content-Type:'.$type);
        header('Content-Length: ' . filesize($file));
        readfile($file);
    }
}
