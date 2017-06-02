<?php

namespace Drupal\yoti_connect\Controller;

use Drupal;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\user\UserInterface;
use Drupal\yoti_connect\YotiConnectHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
        $config = YotiConnectHelper::getConfig();

        // todo: remove on live
        if (!array_key_exists('token', $_GET)) {
            if (YotiConnectHelper::mockRequests()) {
                $token = file_get_contents(__DIR__ . '/../../sdk/sample-data/connect-token.txt');
                return $this->redirect('yoti_connect.link', ['token' => $token]);
            }
            return new TrustedRedirectResponse($helper::getLoginUrl());
        }

        $this->cache('dynamic_page_cache')->deleteAll();
        $this->cache('render')->deleteAll();

//        return $this->redirect('user.login');

        $result = $helper->link();
        if (!$result) {
            return new TrustedRedirectResponse($config['yoti_fail_url']);
        }
        elseif ($result instanceof RedirectResponse) {
            return $result;
        }
        return new TrustedRedirectResponse($config['yoti_success_url']);
    }

    public function register()
    {
        // don't allow unless session
        if (!YotiConnectHelper::getYotiUserFromStore())
        {
            // todo: enable
//            drupal_goto();
        }

        $form['yoti_nolink'] = array(
            '#weight' => -1000,
            //        '#type' => 'checkbox',
            //        '#title' => t('Check this box to skip linking Yoti account to Drupal and simply login'),
            '#default_value' => variable_get('yoti_nolink'),
            '#markup' => '<div class="form-item form-type-checkbox form-item-yoti-link messages warning" style="margin: 0 0 15px 0">
                <div><b>Warning: You are about to link your Drupal account to your Yoti account</b></div>
                <input type="checkbox" id="edit-yoti-link" name="yoti_nolink" value="1" class="form-checkbox"'.(!empty($form_state['input']['yoti_nolink']) ? ' checked="checked"' : '').'>
                <label class="option" for="edit-yoti-link">Check this box to stop this from happening and instead login regularly.</label>
            </div>'
            //    '#description' => t('Copy the SDK ID of your Yoti App here'),
        );

        $form =  user_login($form, $form_state);

        $form['name']['#title'] = t('Your Drupal Username');
        $form['pass']['#title'] = t('Your Drupal Password');

        return $form;
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
        $current = Drupal::currentUser();
        $isAdmin = in_array('administrator', $current->getRoles());
        $userId = (!empty($_GET['user_id']) && $isAdmin) ? (int) $_GET['user_id'] : $current->id();
        $tableName = YotiConnectHelper::tableName();
        $dbProfile = \Drupal::database()->query("SELECT * from `{$tableName}` WHERE uid=$userId")->fetchAssoc();
        if (!$dbProfile)
        {
            return;
        }

        $dbProfile = unserialize($dbProfile['data']);

//        $field = null;
//        if (!empty($_GET['field']))
//        {
//            $field = $_GET['field'];
//        }
//
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
}
