<?php

namespace Drupal\yoti\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\yoti\YotiHelper;
use Drupal\yoti\Models\YotiUserModel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

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
    $targetUser = self::getTargetUser($current);

    if (!($targetUser instanceof UserInterface)) {
      return $this->notFoundResponse();
    }

    $dbProfile = YotiUserModel::getYotiUserById($targetUser->id());
    if (!$dbProfile) {
      return $this->notFoundResponse();
    }

    // Unserialize Yoti user data.
    $userProfileArr = unserialize($dbProfile['data']);

    $field = ($field === 'selfie') ? 'selfie_filename' : $field;
    if (!is_array($userProfileArr) || !array_key_exists($field, $userProfileArr)) {
      return $this->notFoundResponse();
    }

    // Get user selfie file path.
    $file = YotiHelper::uploadDir() . "/{$userProfileArr[$field]}";
    if (!is_file($file)) {
      return $this->notFoundResponse();
    }

    // Returning response here as required by Drupal controller action.
    return new BinaryFileResponse($file, 200);
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

  /**
   * Check access to bin files.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   If account can view target user isAllowed() will be TRUE.
   */
  public static function accessBinFile(AccountInterface $account) {
    if ($targetUser = self::getTargetUser($account)) {
      return AccessResult::allowedIf($targetUser->access('view', $account));
    }
    return AccessResult::neutral();
  }

  /**
   * Get the target user for this request defined by user_id GET parameter.
   *
   * Provided $account user will be returned by default.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Return this account when user_id is not specified as GET parameter.
   *
   * @return \Drupal\user\UserInterface|null
   *   The target user.
   */
  private static function getTargetUser(AccountInterface $account) {
    $userId = (!empty($_GET['user_id'])) ? (int) $_GET['user_id'] : $account->id();
    return User::load($userId);
  }

  /**
   * Return a 404 response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The 404 response.
   */
  private function notFoundResponse() {
    return new Response(NULL, 404, []);
  }

}
