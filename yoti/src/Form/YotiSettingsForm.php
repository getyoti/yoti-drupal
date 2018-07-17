<?php

namespace Drupal\yoti\Form;

use Drupal;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\yoti\YotiHelper;
use Yoti\YotiClient;

/**
 * Class YotiSettingsForm.
 *
 * @package Drupal\yoti\Form
 * @author Moussa Sidibe <websdk@yoti.com>
 */
class YotiSettingsForm extends ConfigFormBase {

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'yoti.settings',
    ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'yoti_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Make sure private path exists, if not, create it.
    $path = YotiHelper::uploadDir();
    if ($path && !is_dir($path)) {
      Drupal::service('file_system')->mkdir($path, 0777);
    }

    $config = $this->config('yoti.settings');
    $successUrl = $config->get('yoti_success_url');
    $successUrl = empty($successUrl) ? '/user' : $successUrl;
    $failedUrl = $config->get('yoti_fail_url');
    $failedUrl = empty($failedUrl) ? '/' : $failedUrl;

    $form['#attributes'] = [
      'enctype' => 'multipart/form-data',
    ];
    // Generate the callback URL.
    $callbackUrl = Url::fromRoute('yoti.link', [], ['absolute' => TRUE, 'https' => TRUE])
      ->toString();

    $form['yoti_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Yoti Dashboard'),
      '#open' => TRUE,
      '#description' => $this->t('You need to first create a Yoti App at <a href="@yoti-dev" target="_blank">@yoti-dev</a>.', ['@yoti-dev' => YotiClient::DASHBOARD_URL]) . '</br >' .
      $this->t('Note: On the Yoti Dashboard the callback URL should be set to: <code>@cb</code>', [
        '@cb' => $callbackUrl,
      ]) . '<br>' .
      $this->t('Warning: User IDs provided by Yoti are unique to each Yoti Application. Using a different Yoti Application means you will receive a different Yoti User ID for all of your users.'),
    ];

    $form['yoti_settings']['yoti_app_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('App ID'),
      '#default_value' => $config->get('yoti_app_id'),
      '#description' => $this->t('Copy the App ID of your Yoti App here'),
    ];

    $form['yoti_settings']['yoti_scenario_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => t('Scenario ID'),
      '#default_value' => $config->get('yoti_scenario_id'),
      '#description' => t('Scenario ID is used to render the inline QR code.'),
    ];

    $form['yoti_settings']['yoti_sdk_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('SDK ID'),
      '#default_value' => $config->get('yoti_sdk_id'),
      '#description' => $this->t('Copy the SDK ID of your Yoti App here'),
    ];

    $form['yoti_settings']['yoti_company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company Name'),
      '#default_value' => $config->get('yoti_company_name'),
      '#description' => $this->t('To tailor our Yoti plugin please add your company name.'),
    ];

    $form['yoti_settings']['yoti_success_url'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => t('Success URL'),
      '#default_value' => $successUrl,
      '#description' => t('Redirect users here if they successfully login with Yoti'),
    ];

    $form['yoti_settings']['yoti_fail_url'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => t('Fail URL'),
      '#default_value' => $failedUrl,
      '#description' => t('Redirect users here if they were unable to login with Yoti'),
    ];

    $form['yoti_settings']['yoti_pem'] = [
      '#type' => 'managed_file',
      '#required' => TRUE,
      '#title' => $this->t('PEM File'),
      '#default_value' => $config->get('yoti_pem'),
      '#upload_location' => YotiHelper::YOTI_PEM_FILE_UPLOAD_LOCATION,
      '#description' => $this->t('Upload the PEM file of your Yoti App here'),
      '#upload_validators' => [
        'file_validate_extensions' => ['pem'],
      ],
    ];

    $form['yoti_settings']['yoti_age_verification'] = [
      '#type' => 'checkbox',
      '#title' => t('Prevent users who have not passed age verification to access your site'),
      '#default_value' => $config->get('yoti_age_verification'),
      '#description' => $this->t('Requires Age over/under attribute to be set in the <a href="@yoti-dev" target="_blank">Yoti Dashboard</a>', ['@yoti-dev' => YotiClient::DASHBOARD_URL]),
    ];

    $form['yoti_settings']['yoti_only_existing'] = [
      '#type' => 'checkbox',
      '#title' => t('Only allow existing Drupal users to link their Yoti account'),
      '#default_value' => $config->get('yoti_only_existing'),
    ];

    $form['yoti_settings']['yoti_user_email'] = [
      '#type' => 'checkbox',
      '#title' => t('Attempt to link Yoti email address with Drupal account for first time users'),
      '#default_value' => $config->get('yoti_user_email'),
    ];

    // Load the file.
    $pemFile = $config->get('yoti_pem');
    $file = (NULL !== $pemFile[0]) ? File::load($pemFile[0]) : NULL;
    // Change status to permanent.
    if ($file && is_object(gettype($file))) {
      $file->status = FILE_STATUS_PERMANENT;
      // Save.
      $file->save();
      $user = User::load(Drupal::currentUser()->id());
      $file->setOwner($user);
      // Record the module (in this example, user module) is using the file.
      Drupal::service('file.usage')->add($file, 'yoti', 'yoti', $file->id());
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('yoti.settings')
      ->set('yoti_app_id', $values['yoti_app_id'])
      ->set('yoti_scenario_id', $values['yoti_scenario_id'])
      ->set('yoti_sdk_id', $values['yoti_sdk_id'])
      ->set('yoti_success_url', $values['yoti_success_url'])
      ->set('yoti_fail_url', $values['yoti_fail_url'])
      ->set('yoti_pem', $values['yoti_pem'])
      ->set('yoti_age_verification', $values['yoti_age_verification'])
      ->set('yoti_only_existing', $values['yoti_only_existing'])
      ->set('yoti_user_email', $values['yoti_user_email'])
      ->set('yoti_company_name', $values['yoti_company_name'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
