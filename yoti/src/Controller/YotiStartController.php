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
 * @author Moussa Sidibe <websdk@yoti.com>
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
    $isAdmin = in_array('administrator', $current->getRoles(), TRUE);
    $userId = (!empty($_GET['user_id']) && $isAdmin) ? (int) $_GET['user_id'] : $current->id();
    $dbProfile = YotiUserModel::getYotiUserById($userId);
    if (!$dbProfile) {
      return;
    }

    // Unserialize Yoti user data.
    $userProfileArr = unserialize($dbProfile['data']);

    $field = ($field === 'selfie') ? 'selfie_filename' : $field;
    if (!is_array($userProfileArr) || !array_key_exists($field, $userProfileArr)) {
      return;
    }

    // Get user selfie file path.
    $file = YotiHelper::uploadDir() . "/{$userProfileArr[$field]}";
    if (!file_exists($file)) {
      return;
    }

    $type = 'image/png';
    header('Content-Type:' . $type);
    header('Content-Length: ' . filesize($file));
    readfile($file);
    // Returning response here as required by Drupal controller action.
    return new TrustedRedirectResponse('yoti.bin-file');
  }

}
