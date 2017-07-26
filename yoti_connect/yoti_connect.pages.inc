<?php

/**
 * @file
 * User page callback file for the yoti module.
 */

require_once __DIR__ . '/YotiConnectHelper.php';

/**
 * Authenticate Yoti user or unlink if user is already linked to Yoti user.
 */
function yoti_connect_link() {
  global $user;

  // Check if user already has account.
  if ($user) {
    $tableName = YotiConnectHelper::tableName();
    $dbProfile = db_query("SELECT * from `{$tableName}` WHERE uid=$user->uid")->fetchAssoc();
    if ($dbProfile) {
      return yoti_connect_unlink();
    }
  }

  // Resume as normal.
  $helper = new YotiConnectHelper();
  // todo: remove on live.
  if (!array_key_exists('token', $_GET)) {
    if (YotiConnectHelper::mockRequests()) {
      $token = file_get_contents(__DIR__ . '/sdk/sample-data/connect-token.txt');
      return drupal_goto("/yoti-connect/link", array('query' => ["token" => $token]));
    }
    return drupal_goto(YotiConnectHelper::getLoginUrl());
  }

  if (!$helper->link()) {
    return drupal_goto(variable_get('yoti_fail_url'));
  }
  return drupal_goto(variable_get('yoti_success_url'));
}

/**
 * Unlink drupal user from Yoti user.
 */
function yoti_connect_unlink() {
  $helper = new YotiConnectHelper();
  $helper->unlink();
  return drupal_goto('/');
}

/**
 * Display file as an image.
 */
function yoti_connect_bin_file() {
  global $user;

  $current = $user;
  $isAdmin = in_array('administrator', $current->roles);
  $userId = (!empty($_GET['user_id']) && $isAdmin) ? (int) $_GET['user_id'] : $current->uid;
  $tableName = YotiConnectHelper::tableName();
  $dbProfile = db_query("SELECT * from `{$tableName}` WHERE uid=$userId")->fetchAssoc();
  if (!$dbProfile) {
    return;
  }

  $dbProfile = unserialize($dbProfile['data']);

  $field = NULL;
  if (!empty($_GET['field'])) {
    $field = $_GET['field'];
  }

  $field = ($field == 'selfie') ? 'selfie_filename' : $field;
  if (!$dbProfile || !array_key_exists($field, $dbProfile)) {
    return;
  }

  $file = YotiConnectHelper::uploadDir() . "/{$dbProfile[$field]}";
  if (!file_exists($file)) {
    return;
  }

  $type = 'image/png';
  header('Content-Type:' . $type);
  header('Content-Length: ' . filesize($file));
  readfile($file);
}

/**
 * Implements hook_user_insert().
 */
function yoti_connect_user_login(&$edit, $account) {
  $activityDetails = YotiConnectHelper::getYotiUserFromStore();
  if ($activityDetails && empty($_SESSION['yoti_nolink'])) {
    // Link account.
    $helper = new YotiConnectHelper();
    $helper->createYotiUser($account->uid, $activityDetails);
  }

  // Remove session.
  unset($_SESSION['yoti_nolink']);
  YotiConnectHelper::clearYotiUserStore();
}

/**
 * Display the option to Yoti users not to link their account on the login page.
 */
function yoti_connect_register($form, &$form_state) {
  // don't allow unless session.
  if (!YotiConnectHelper::getYotiUserFromStore()) {
    drupal_goto();
  }

  $form['yoti_nolink'] = array(
    '#weight' => -1000,
    '#default_value' => variable_get('yoti_nolink'),
    '#markup' => '<div class="form-item form-type-checkbox form-item-yoti-link messages warning" style="margin: 0 0 15px 0">
                <div><b>Warning: You are about to link Drupal to your Yoti account. Click the box below to keep them separate.</b></div>
                <input type="checkbox" id="edit-yoti-link" name="yoti_nolink" value="1" class="form-checkbox"' . (!empty($form_state['input']['yoti_nolink']) ? ' checked="checked"' : '') . '>
                <label class="option" for="edit-yoti-link">Don\'t link my Yoti account</label>
            </div>',
  );

  $form = user_login($form, $form_state);

  $form['name']['#title'] = t('Your Drupal Username');
  $form['pass']['#title'] = t('Your Drupal Password');

  return $form;
}

/**
 * Process user registration form.
 */
function yoti_connect_register_submit($form, &$form_state) {
  $_SESSION['yoti_nolink'] = !empty($form_state['input']['yoti_nolink']);
  user_login_submit($form, $form_state);
}
