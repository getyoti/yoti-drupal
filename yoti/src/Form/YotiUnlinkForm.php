<?php

namespace Drupal\yoti\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;

/**
 * Class YotiUnlinkForm.
 *
 * @package Drupal\yoti\Form
 */
class YotiUnlinkForm extends ConfirmFormBase {

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
