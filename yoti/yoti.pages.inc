<?php

/**
 * @file
 * User page callback file for the yoti module.
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
  // If there is no token, check if we are mocking the request.
  if (!array_key_exists('token', $_GET)) {
    if (YotiHelper::mockRequests()) {
      $token = file_get_contents(__DIR__ . '/sdk/sample-data/connect-token.txt');
      return drupal_goto('/yoti/link', ['query' => ['token' => $token]]);
    }
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

  $companyName = (!empty(variable_get('yoti_company_name'))) ? variable_get('yoti_company_name') : 'Drupal';

  $form['yoti_nolink'] = [
    '#weight' => -1000,
    '#default_value' => variable_get('yoti_nolink'),
    '#markup' => '<div class="form-item form-type-checkbox form-item-yoti-link messages warning" style="margin: 0 0 15px 0">
                <div><b>Warning: You are about to link your ' . $companyName . ' account to your Yoti account. If you don\'t want this to happen, tick the checkbox below.</b></div>
                <input type="checkbox" id="edit-yoti-link" name="yoti_nolink" value="1" class="form-checkbox"' . (!empty($form_state['input']['yoti_nolink']) ? ' checked="checked"' : '') . '>
                <label class="option" for="edit-yoti-link">Don\'t link my Yoti account</label>
            </div>',
  ];

  $form = user_login($form, $form_state);
  $loginFieldLabel = 'Your ' . $companyName . ' Username';
  $passwordFieldLabel = 'Your ' . $companyName . ' Password';
  $form['name']['#title'] = $loginFieldLabel;
  $form['pass']['#title'] = $passwordFieldLabel;

  return $form;
}

/**
 * Process user registration form.
 */
function yoti_register_submit($form, &$form_state) {
  $_SESSION['yoti_nolink'] = !empty($form_state['input']['yoti_nolink']);
  user_login_submit($form, $form_state);
}
