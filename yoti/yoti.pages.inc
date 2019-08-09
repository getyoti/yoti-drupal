<?php

/**
 * @file
 * User page callback file for the Yoti module.
 */

require_once __DIR__ . '/YotiHelper.php';

/**
 * Page callback to handle linking of Yoti user to current user.
 */
function yoti_link() {
  // Resume as normal.
  $helper = new YotiHelper();
  if (!array_key_exists('token', $_GET)) {
    return drupal_goto(YotiHelper::getLoginUrl());
  }

  if (!$helper->link()) {
    return drupal_goto(variable_get('yoti_fail_url'));
  }
  return drupal_goto(variable_get('yoti_success_url'));
}

/**
 * Form to confirm unlinking of Drupal user from Yoti user.
 *
 * @ingroup forms
 * @see yoti_unlink_submit()
 */
function yoti_unlink() {
  return confirm_form(
    array(),
    'Unlink Yoti Account',
    '<front>',
    'Are you sure you want to unlink your account from Yoti?',
    t('Yes'),
    t('Cancel')
  );
}

/**
 * Form submission handler for yoti_unlink().
 *
 * @see yoti_unlink()
 */
function yoti_unlink_submit() {
  $helper = new YotiHelper();
  $helper->unlink();
  return drupal_goto('/');
}

/**
 * Display file as an image.
 */
function yoti_bin_file() {
  global $user;
  $current = $user;

  // Check that request token is valid before serving the bin file.
  if (!isset($_GET['token']) || !drupal_valid_token($_GET['token'], 'yoti_selfie')) {
    drupal_not_found();
    drupal_exit();
  }

  // Field must be provided to fetch a bin file.
  if (empty($_GET['field'])) {
    drupal_not_found();
    drupal_exit();
  }
  $field = $_GET['field'];

  // Check access and set field based on requested field name.
  switch ($field) {
    case YotiHelper::YOTI_BIN_FIELD_SELFIE:
      $canAccess = user_access(YotiHelper::YOTI_PERMISSION_VIEW_SELFIE);
      $field = 'selfie_filename';
      break;

    default:
      $canAccess = user_access('administer users');
  }

  // Use requested user ID if provided and default to current user.
  $userId = !empty($_GET['user_id']) ? (int) $_GET['user_id'] : (int) $current->uid;

  // Prevent access to other user files for users without permission.
  if (($userId != $current->uid) && !$canAccess) {
    drupal_access_denied();
    drupal_exit();
  }

  $dbProfile = YotiHelper::getYotiUserProfile($userId);
  if (!$dbProfile) {
    drupal_not_found();
    drupal_exit();
  }

  $dbProfile = unserialize($dbProfile['data']);

  if (!$dbProfile || !array_key_exists($field, $dbProfile)) {
    drupal_not_found();
    drupal_exit();
  }

  $file = YotiHelper::selfieFilePath($dbProfile[$field]);
  if (!is_file($file)) {
    drupal_not_found();
    drupal_exit();
  }

  $type = 'image/png';
  header('Content-Type:' . $type);
  header('Content-Length: ' . filesize($file));
  readfile($file);
}

/**
 * Implements hook_user_insert().
 */
function yoti_user_login(&$edit, $account) {
  $activityDetails = YotiHelper::getYotiUserFromStore();
  if ($activityDetails && empty($_SESSION['yoti_nolink'])) {
    // Link account.
    $helper = new YotiHelper();
    $helper->createYotiUser($account->uid, $activityDetails);
  }

  // Remove session.
  unset($_SESSION['yoti_nolink']);
  YotiHelper::clearYotiUserStore();
}

/**
 * Display the option to Yoti users not to link their account on the login page.
 */
function yoti_register($form, &$form_state) {
  // Don't allow unless session.
  if (!YotiHelper::getYotiUserFromStore()) {
    drupal_goto();
  }

  // Associative array of text replacements.
  $text_replacements = array(
    '@company_name' => (!empty(variable_get('yoti_company_name'))) ? variable_get('yoti_company_name') : 'Drupal',
  );

  $form['yoti_login_message'] = array(
    '#weight' => -1000,
    '#prefix' => '<div class="messages warning yoti-login-message">',
    '#suffix' => '</div>',
  );

  $form['yoti_login_message']['text'] = array(
    '#prefix' => '<div><b>',
    '#suffix' => '</b></div>',
    '#markup' => implode(' ', array(
      t('Warning: You are about to link your @company_name account to your Yoti account.', $text_replacements),
      t("If you don't want this to happen, tick the checkbox below."),
    )),
  );

  $form['yoti_login_message']['yoti_nolink'] = array(
    '#type' => 'checkbox',
    '#title' => t("Don't link my Yoti account"),
    '#default_value' => (int) !empty($form_state['input']['yoti_nolink']),
  );

  $form = user_login($form, $form_state);

  $form['name']['#title'] = t('Your @company_name Username', $text_replacements);
  $form['pass']['#title'] = t('Your @company_name Password', $text_replacements);

  $form['#attached']['css'][] = array(
    'data' => drupal_get_path('module', 'yoti') . '/css/yoti.css',
    'group' => CSS_DEFAULT,
    'every_page' => TRUE,
  );

  return $form;
}

/**
 * Process user registration form.
 */
function yoti_register_submit($form, &$form_state) {
  $_SESSION['yoti_nolink'] = !empty($form_state['input']['yoti_nolink']);
  user_login_submit($form, $form_state);
}
