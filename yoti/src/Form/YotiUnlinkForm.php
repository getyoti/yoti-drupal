<?php

namespace Drupal\yoti\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\yoti\Models\YotiUserModel;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Class YotiUnlinkForm.
 *
 * @package Drupal\yoti\Form
 */
class YotiUnlinkForm extends ConfirmFormBase {

  /**
   * Check that the current account is linked.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   If account is linked isAllowed() will be TRUE, otherwise
   *   isNeutral() will be TRUE.
   */
  public static function access(AccountInterface $account) {
    $db_profile = YotiUserModel::getYotiUserById($account->id());
    return AccessResult::allowedIf(!empty($db_profile));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::service('yoti.helper')->unlink();
    $form_state->setRedirect('user.page');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "yoti_unlink_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('user.page');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Unlink Yoti Account');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Are you sure you want to unlink your account from Yoti?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this
      ->t('Yes');
  }

}
