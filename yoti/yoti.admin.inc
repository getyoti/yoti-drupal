<?php

/**
 * @file
 * Admin page callback file for the yoti module.
 */

/**
 * Page callback: Generates the appropriate yoti administration form.
 *
 * This function generates yoti settings form.
 *
 * @return mixed
 *   A renderable form array for yoti settings.
 */
function yoti_api_keys_settings($form, &$form_state) {
  // Make sure private path exists, if not, create it.
  $uploadDir = YotiHelper::secureUploadDir(TRUE);
  if ($uploadDir && !is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, TRUE);
  }

  $form['some_text'] = [
    '#markup' => '<div>' .
    t('You need to first create a Yoti App at <a href="@yoti-dev" target="_blank">@yoti-dev</a>.', ['@yoti-dev' => YotiHelper::YOTI_HUB_URL]) . '<br>' .
    t('Note: On the Yoti Hub the callback URL should be set to:') . ' <strong>' . url('/yoti/link', ['absolute' => TRUE]) . '</strong><br>' .
    t('Warning: User IDs provided by Yoti are unique to each Yoti Application. Using a different Yoti Application means you will receive a different Yoti User ID for all of your users.') .
    '</div>',
  ];

  $form['#attributes'] = [
    'enctype' => 'multipart/form-data',
  ];

  $form['yoti_settings']['yoti_app_id'] = [
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('App ID'),
    '#default_value' => variable_get('yoti_app_id'),
    '#description' => t('App ID is a unique identifier for your specific application.'),
  ];

  $form['yoti_settings']['yoti_scenario_id'] = [
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('Scenario ID'),
    '#default_value' => variable_get('yoti_scenario_id'),
    '#description' => t('Scenario ID identifies the attributes associated with your Yoti application. This value can be found on your application page in Yoti Hub.'),
  ];

  $form['yoti_settings']['yoti_sdk_id'] = [
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('Client SDK ID'),
    '#default_value' => variable_get('yoti_sdk_id'),
    '#description' => t('Client SDK ID identifies your Yoti Hub application. This value can be found in the Hub, within your application section, in the keys tab.'),
  ];

  $form['yoti_settings']['yoti_company_name'] = [
    '#type' => 'textfield',
    '#title' => t('Your Company Name'),
    '#default_value' => variable_get('yoti_company_name'),
    '#description' => t('To tailor our Yoti plugin please add your company name.'),
  ];

  $form['yoti_settings']['yoti_success_url'] = [
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('Success URL'),
    '#default_value' => variable_get('yoti_success_url', '/user'),
    '#description' => t('Redirect users here if they successfully log in with Yoti.'),
  ];

  $form['yoti_settings']['yoti_fail_url'] = [
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('Fail URL'),
    '#default_value' => variable_get('yoti_fail_url', '/'),
    '#description' => t('Redirect users here if they were unable to log in with Yoti.'),
  ];

  $form['yoti_settings']['yoti_pem'] = [
    '#type' => 'managed_file',
    '#field_name' => 'yoti_pem',
    '#required' => TRUE,
    '#title' => t('PEM File'),
    '#default_value' => variable_get('yoti_pem'),
    '#upload_location' => YotiHelper::secureUploadDir(FALSE),
    '#description' => t('PEM File is the application pem file. It can be downloaded only once from the Keys tab in your Yoti Hub.'),
    '#upload_validators' => [
      'file_validate_extensions' => ['pem'],
    ],
  ];

  $form['yoti_settings']['yoti_age_verification'] = [
    '#type' => 'checkbox',
    '#title' => t('Prevent users who have not passed age verification to access your site'),
    '#description' => t('Requires Age verify condition to be set in the <a href="@yoti-dev" target="_blank">Yoti Hub</a>.', ['@yoti-dev' => YotiHelper::YOTI_HUB_URL]),
    '#default_value' => variable_get('yoti_age_verification'),
  ];

  $form['yoti_settings']['yoti_only_existing'] = [
    '#type' => 'checkbox',
    '#title' => t('Only allow existing Drupal users to link their Yoti account'),
    '#default_value' => variable_get('yoti_only_existing'),
  ];

  $form['yoti_settings']['yoti_user_email'] = [
    '#type' => 'checkbox',
    '#title' => t('Attempt to link Yoti email address with Drupal account for first time users'),
    '#default_value' => variable_get('yoti_user_email'),
  ];

  // Load the file.
  $pemFile = variable_get('yoti_pem');
  if ($pemFile) {
    $file = file_load($pemFile);
    // Change status to permanent.
    if (is_object(gettype($file))) {
      $file->status = FILE_STATUS_PERMANENT;
      // Save.
      file_save($file);
      // Record the module (in this example, user module) is using the file.
      file_usage_add($file, 'yoti', 'yoti_pem', $file->fid);
    }
  }

  return system_settings_form($form);
}
