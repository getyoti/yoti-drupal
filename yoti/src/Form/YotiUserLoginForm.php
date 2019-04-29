<?php

namespace Drupal\yoti\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Form\UserLoginForm;

/**
 * Class YotiUserLoginForm.
 *
 * @package Drupal\yoti\Form
 * @author Moussa Sidibe <websdk@yoti.com>
 */
class YotiUserLoginForm extends UserLoginForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::service('yoti.config');

    $company_name = (!empty($config->getCompanyName())) ? $config->getCompanyName() : 'Drupal';

    $form['yoti_login_message'] = [
      '#type' => 'fieldset',
      '#weight' => -1000,
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#attributes' => [
        'class' => ['messages', 'warning'],
      ],
    ];

    $form['yoti_login_message']['text'] = [
      '#type' => 'inline_template',
      '#template' => "
        <div>
          <b>Warning: You are about to link your {{ company_name }} account to your Yoti account.
          If you don't want this to happen, tick the checkbox below.</b>
        </div>",
      '#context' => [
        'company_name' => $company_name,
      ],
    ];

    $form['yoti_login_message']['yoti_nolink'] = [
      '#type' => 'checkbox',
      '#title' => t("Don't link my Yoti account"),
      '#default_value' => $form_state->get('yoti_nolink'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit user login form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['yoti_nolink'] = !empty($form_state->get('yoti_nolink'));
    parent::submitForm($form, $form_state);
  }

}
