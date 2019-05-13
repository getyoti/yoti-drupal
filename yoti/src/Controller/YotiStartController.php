<?php

namespace Drupal\yoti\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\yoti\YotiHelper;
use Drupal\yoti\Models\YotiUserModel;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

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
    $helper = \Drupal::service('yoti.helper');
    $config = \Drupal::service('yoti.config');

    // If no token is given check if we are in mock request mode.
    if (!array_key_exists('token', $_GET)) {
      return new TrustedRedirectResponse($helper::getLoginUrl());
    }

    $result = $helper->link();
    if (!$result) {
      $failedURL = YotiHelper::getPathFullUrl($config->getFailUrl());
      return new TrustedRedirectResponse($failedURL);
    }
    elseif ($result instanceof RedirectResponse) {
      return $result;
    }

    $successUrl = YotiHelper::getPathFullUrl($config->getSuccessUrl());
    return new TrustedRedirectResponse($successUrl);
  }

  /**
   * Send binary file from Yoti.
   */
  public function binFile($field) {
    $current = \Drupal::currentUser();
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

  /**
   * Check that current account is not linked.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   If account is not linked isAllowed() will be TRUE, otherwise
   *   isNeutral() will be TRUE.
   */
  public static function accessLink(AccountInterface $account) {
    $db_profile = YotiUserModel::getYotiUserById($account->id());
    return AccessResult::allowedIf(empty($db_profile));
  }

}
