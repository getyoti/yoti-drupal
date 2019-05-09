<?php

/**
 * @file
 * User page callback file for the Yoti module.
 */

require_once __DIR__ . '/YotiHelper.php';

/**
 * Authenticate Yoti user or unlink if user is already linked to Yoti user.
 */
function yoti_link() {
  global $user;

  // Check if user already has an account.
  if ($user) {
    $dbProfile = YotiHelper::getYotiUserProfile($user->uid);
    if ($dbProfile) {
      return yoti_unlink();
    }
  }

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
 * Unlink drupal user from Yoti user.
 */
function yoti_unlink() {
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
  $isAdmin = in_array('administrator', $current->roles, TRUE);
  $userId = (!empty($_GET['user_id']) && $isAdmin) ? (int) $_GET['user_id'] : $current->uid;
  $dbProfile = YotiHelper::getYotiUserProfile($userId);
  if (!$dbProfile) {
    return;
  }

  $dbProfile = unserialize($dbProfile['data']);

  $field = NULL;
  if (!empty($_GET['field'])) {
    $field = $_GET['field'];
  }

  $field = ($field === 'selfie') ? 'selfie_filename' : $field;
  if (!$dbProfile || !array_key_exists($field, $dbProfile)) {
    return;
  }

  $file = YotiHelper::secureUploadDir() . "/{$dbProfile[$field]}";
  if (!file_exists($file)) {
    $oldFile = YotiHelper::uploadDir() . "/{$dbProfile[$field]}";
    // Check if the path exists in the old upload dir.
    if (!file_exists($oldFile)) {
      return;
    }
    // Use the old file path to display user profile image.
    $file = $oldFile;
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
