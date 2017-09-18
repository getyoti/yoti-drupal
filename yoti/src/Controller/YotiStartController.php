<?php

namespace Drupal\yoti\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\yoti\YotiHelper;
use Drupal\yoti\Models\YotiUserModel;
use Symfony\Component\HttpFoundation\RedirectResponse;

require_once __DIR__ . '/../../sdk/boot.php';

/**
 * Class YotiStartController.
 *
 * @package Drupal\yoti\Controller
 * @author Moussa Sidibe <moussa.sidibe@yoti.com>
 */
class YotiStartController extends ControllerBase {

  /**
   * Link user account to Yoti.
   */
  public function link() {
    /** @var \Drupal\yoti\YotiHelper $helper */
    $helper = Drupal::service('yoti.helper');
    $config = YotiHelper::getConfig();

    // If no token is given check if we are in mock request mode.
    if (!array_key_exists('token', $_GET)) {
      if (YotiHelper::mockRequests()) {
        $token = file_get_contents(__DIR__ . '/../../sdk/sample-data/connect-token.txt');
        return $this->redirect('yoti.link', ['token' => $token]);
      }
      return new TrustedRedirectResponse($helper::getLoginUrl());
    }

    $this->cache('dynamic_page_cache')->deleteAll();
    $this->cache('render')->deleteAll();

    $result = $helper->link();
    if (!$result) {
      $failedURL = YotiHelper::getPathFullUrl($config['yoti_fail_url']);
      return new TrustedRedirectResponse($failedURL);
    }
    elseif ($result instanceof RedirectResponse) {
      return $result;
    }

    $successUrl = YotiHelper::getPathFullUrl($config['yoti_success_url']);
    return new TrustedRedirectResponse($successUrl);
  }

  /**
   * Create Yoti user.
   */
  public function register() {
    // Don't allow unless session.
    if (!YotiHelper::getYotiUserFromStore()) {
      drupal_goto();
    }

    $config = YotiHelper::getConfig();

    $companyName = (!empty($config['yoti_company_name'])) ? $config['yoti_company_name'] : 'Drupal';

    $form['yoti_nolink'] = [
      '#weight' => -1000,
      '#markup' => '<div class="form-item form-type-checkbox form-item-yoti-link messages warning" style="margin: 0 0 15px 0">
                    <div><b>Warning: You are about to link your ' . $companyName . ' account to your Yoti account</b></div>
                    <input type="checkbox" id="edit-yoti-link" name="yoti_nolink" value="1" class="form-checkbox"' . (!empty($form_state['input']['yoti_nolink']) ? ' checked="checked"' : '') . '>
                    <label class="option" for="edit-yoti-link">Check this box to stop this from happening and instead login regularly.</label>
                </div>',
    ];

    $form['name']['#title'] = "Your {$companyName} Username";
    $form['pass']['#title'] = "Your {$companyName} Password";

    return $form;
  }

  /**
   * Unlink user account from Yoti.
   */
  public function unlink() {
    /** @var \Drupal\yoti\YotiHelper $helper */
    $helper = Drupal::service('yoti.helper');

    $this->cache('dynamic_page_cache')->deleteAll();
    $this->cache('render')->deleteAll();

    $helper->unlink();
    return $this->redirect('user.login');
  }

  /**
   * Send binary file from Yoti.
   */
  public function binFile($field) {
    $current = Drupal::currentUser();
    $isAdmin = in_array('administrator', $current->getRoles());
    $userId = (!empty($_GET['user_id']) && $isAdmin) ? (int) $_GET['user_id'] : $current->id();
    $dbProfile = YotiUserModel::getYotiUserById($userId);
    if (!$dbProfile) {
      return;
    }

    $dbProfile = unserialize($dbProfile['data']);

    $field = ($field == 'selfie') ? 'selfie_filename' : $field;
    if (!$dbProfile || !array_key_exists($field, $dbProfile)) {
      return;
    }

    $file = YotiHelper::uploadDir() . "/{$dbProfile[$field]}";
    if (!file_exists($file)) {
      return;
    }

    $type = 'image/png';
    header('Content-Type:' . $type);
    header('Content-Length: ' . filesize($file));
    readfile($file);
  }

}
