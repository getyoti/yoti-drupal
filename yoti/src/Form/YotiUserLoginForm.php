<?php

namespace Drupal\yoti\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Form\UserLoginForm;
use Drupal\yoti\YotiHelper;

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
    $config = YotiHelper::getConfig();

    $companyName = (!empty($config['yoti_company_name'])) ? $config['yoti_company_name'] : 'Drupal';

    $form['yoti_nolink'] = [
      '#weight' => -1000,
      '#type' => 'inline_template',
      '#template' => '{{ somecontent | raw }}',
      '#default_value' => \Drupal::config('yoti_nolink')->get(),
      '#context' => [
        'somecontent' => '<div class="form-item form-type-checkbox form-item-yoti-link messages warning" style="margin: 0 0 15px 0">
                    <div><b>Warning: You are about to link your ' . $companyName . ' account to your Yoti account. If you don\'t want this to happen, tick the checkbox below.</b></div>
                    <input type="checkbox" id="edit-yoti-link" name="yoti_nolink" value="1" class="form-checkbox"' . (!empty($form_state->get('yoti_nolink')) ? ' checked="checked"' : '') . '>
                    <label class="option" for="edit-yoti-link">Don\'t link my Yoti account</label>
                </div>',
      ],
    ];

    $form['name']['#title'] = "Your {$companyName} Username";
    $form['pass']['#title'] = "Your {$companyName} Password";

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
