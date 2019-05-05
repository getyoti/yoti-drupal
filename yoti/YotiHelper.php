<?php

use Yoti\YotiClient;
use Yoti\ActivityDetails;
use Yoti\Entity\Profile;

require_once __DIR__ . '/sdk/boot.php';

/**
 * Class YotiHelper.
 *
 * @package Drupal\yoti
 */
class YotiHelper {

  /**
   * Yoti user database table name.
   */
  const YOTI_USER_TABLE_NAME = 'users_yoti';

  /**
     * Age verification attribute.
     */
  const AGE_VERIFICATION_ATTR = 'age_verified';

  /**
   * Yoti link button default text.
   */
  const YOTI_LINK_BUTTON_DEFAULT_TEXT = 'Use Yoti';

  /**
   * Yoti Drupal SDK identifier.
   */
  const SDK_IDENTIFIER = 'Drupal';

  /**
   * Yoti secure files upload location.
   */
  const YOTI_SECURE_FILES_UPLOAD_LOCATION = 'private://yoti';

  /**
     * Yoti SDK javascript library.
     */
  const YOTI_SDK_JAVASCRIPT_LIBRARY = 'https://sdk.yoti.com/clients/browser.2.1.0.js';

  /**
   * Yoti module config.
   *
   * @var array
   */
  private $config;

  /**
   * YotiHelper constructor.
   */
  public function __construct() {
    $this->config = self::getConfig();
  }

  /**
   * Link drupal user to Yoti user.
   *
   * @param mixed $currentUser
   *   Logged in user.
   *
   * @return bool
   *   true if successful, false otherwise.
   *
   * @throws Exception
   */
  public function link($currentUser = NULL) {
    if (!$currentUser) {
      global $user;
      $currentUser = $user;
    }

    $token = (!empty($_GET['token'])) ? $_GET['token'] : NULL;

    // If no token then ignore.
    if (!$token) {
      self::setFlash('Could not get Yoti token.', 'error');

      return FALSE;
    }

    // Init Yoti client and attempt to request user details.
    try {
      $yotiClient = new YotiClient(
          $this->config['yoti_sdk_id'],
          $this->config['yoti_pem']['contents'],
          YotiClient::DEFAULT_CONNECT_API,
          self::SDK_IDENTIFIER
      );
      $activityDetails = $yotiClient->getActivityDetails($token);
      $profile = $activityDetails->getProfile();
    }
    catch (Exception $e) {
      self::setFlash('Yoti could not successfully connect to your account.', 'error');

      return FALSE;
    }

    if (!$this->passedAgeVerification($profile)) {
      self::setFlash("Could not log you in as you haven't passed the age verification", 'error');
      return FALSE;
    }

    // Check if Yoti user exists.
    $drupalUid = $this->getDrupalUid($activityDetails->getRememberMeId());

    // If Yoti user exists in db but isn't linked to a drupal account
    // (orphaned row) then delete it.
    if (
        $drupalUid
        && $currentUser
        && $currentUser->uid !== $drupalUid
        && !user_load($drupalUid)
    ) {
      // Remove users account.
      $this->deleteYotiUser($drupalUid);
    }

    // If user isn't logged in.
    if (!$currentUser->uid) {
      // Register new user.
      if (!$drupalUid) {
        $errMsg = NULL;

        // Attempt to connect by email.
        $drupalUid = $this->shouldLoginByEmail($activityDetails);

        // If config only existing enabled then check if user exists, if not
        // then redirect to login page.
        if (!$drupalUid) {
          if (empty($this->config['yoti_only_existing'])) {
            try {
              $drupalUid = $this->createUser($activityDetails);
            }
            catch (Exception $e) {
              $errMsg = $e->getMessage();
            }
          }
          else {
            self::storeYotiUser($activityDetails);
            drupal_goto('/yoti/register');
          }
        }

        // No user id? no account.
        if (!$drupalUid) {
          // If couldn't create user then bail.
          self::setFlash("Could not create user account. $errMsg", 'error');

          return FALSE;
        }
      }

      // Log user in.
      $this->loginUser($drupalUid);
    }
    else {
      // If currently logged in user doesn't match Yoti user registered
      // then bail.
      if ($drupalUid && $currentUser->uid !== $drupalUid) {
        self::setFlash('This Yoti account is already linked to another account.', 'error');
      }
      // If Drupal user not found in yoti table then create new yoti user.
      elseif (!$drupalUid) {
        $this->createYotiUser($currentUser->uid, $activityDetails);
        self::setFlash('Your Yoti account has been successfully linked.');
      }
    }

    return TRUE;
  }

  /**
   * Check if age verification applies and is valid.
   *
   * @param \Yoti\Entity\Profile $profile
   *   Yoti user profile Object.
   *
   * @return bool
   *   Return TRUE or FALSE
   */
  public function passedAgeVerification(Profile $profile) {
    return !($this->config['yoti_age_verification'] && !$this->oneAgeIsVerified($profile));
  }

  /**
   * Check that one age verification passes.
   *
   * @param \Yoti\Entity\Profile $profile
   *   Yoti user profile Object.
   *
   * @return bool
   *   True when one age verification passes.
   */
  private function oneAgeIsVerified(Profile $profile) {
    $ageVerificationsArr = $this->processAgeVerifications($profile);
    return empty($ageVerificationsArr) || in_array('Yes', array_values($ageVerificationsArr));
  }

  /**
   * Attempt to log user in by email.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user profile Object.
   *
   * @return null|int
   *   Yoti user Id.
   *
   * @throws Exception
   */
  private function shouldLoginByEmail(ActivityDetails $activityDetails) {
    $drupalUid = NULL;
    $emailConfig = $this->config['yoti_user_email'];
    $profile = $activityDetails->getProfile();
    $email = $profile->getEmailAddress() ? $profile->getEmailAddress()->getValue() : NULL;
    // Attempt to connect by email.
    if ($email && !empty($emailConfig)) {
      $byMail = user_load_by_mail($email);
      if ($byMail) {
        $drupalUid = $byMail->uid;
        $this->createYotiUser($drupalUid, $activityDetails);
      }
    }
    return $drupalUid;
  }

  /**
   * Unlink account from currently logged in.
   */
  public function unlink() {
    global $user;

    // Unlink Yoti user.
    if ($user) {
      $this->deleteYotiUser($user->uid);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Store Yoti user details in the session as a serialised object.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details Object.
   */
  public static function storeYotiUser(ActivityDetails $activityDetails) {
    drupal_session_start();
    $_SESSION['yoti-user'] = serialize($activityDetails);
  }

  /**
   * Retrieve Yoti user details from the session.
   *
   * @return \Yoti\ActivityDetails|null
   *   Returns Yoti user details from the session or Null.
   */
  public static function getYotiUserFromStore() {
    drupal_session_start();
    return array_key_exists('yoti-user', $_SESSION) ? unserialize($_SESSION['yoti-user']) : NULL;
  }

  /**
   * Remove Yoti user details from the session.
   */
  public static function clearYotiUserStore() {
    drupal_session_start();
    unset($_SESSION['yoti-user']);
  }

  /**
   * Set notification message.
   *
   * @param string $message
   *   Notification message to be displayed.
   * @param string $type
   *   Type of notification, example status.
   */
  public static function setFlash($message, $type = 'status') {
    drupal_set_message($message, $type);
  }

  /**
   * Check a username has valid characters.
   *
   * @param string $username
   *   Username to be validated.
   *
   * @return bool
   *   Return TRUE or FALSE
   */
  protected function isValidUsername($username) {
    return (NULL === user_validate_name($username));
  }

  /**
   * Generate new Yoti username or nickname.
   *
   * @param \Yoti\Entity\Profile $profile
   *   Yoti user details Object.
   * @param string $prefix
   *   Yoti user nickname prefix.
   *
   * @return string
   *   Yoti generic username.
   */
  private function generateUsername(Profile $profile, $prefix = 'yoti.user') {
    $givenName = $this->getUserGivenNames($profile);
    $familyName = $profile->getFamilyName()->getValue();

    // If GivenName and FamilyName are provided use them as user nickname/login.
    if (NULL !== $givenName && NULL !== $familyName) {
      $userFullName = $givenName . ' ' . $familyName;
      $userProvidedPrefix = strtolower(str_replace(' ', '.', $userFullName));
      $prefix = $this->isValidUsername($userProvidedPrefix) ? $userProvidedPrefix : $prefix;
    }

    // Get the number of user name that starts with prefix.
    $userQuery = db_select('users', 'u');
    $userQuery->fields('u', ['name']);
    $userQuery->condition('u.name', db_like($prefix) . '%', 'LIKE');
    $userCount = (int) $userQuery->execute()->rowCount();

    // Generate Yoti unique username.
    $username = $prefix;
    if ($userCount > 0) {
      do {
        $username = $prefix . ++$userCount;
      } while (user_load_by_name($username));
    }

    return $username;
  }

  /**
   * If user has more than one given name return the first one.
   *
   * @param \Yoti\Entity\Profile $profile
   *   Yoti user details.
   *
   * @return null|string
   *   Return single user given name
   */
  private function getUserGivenNames(Profile $profile) {
    $givenNamesObj = $profile->getGivenNames();
    $givenNames = $givenNamesObj ? $givenNamesObj->getValue() : '';
    $givenNamesArr = explode(' ', $givenNames);
    return (count($givenNamesArr) > 1) ? $givenNamesArr[0] : $givenNames;
  }

  /**
   * Generate Yoti user email.
   *
   * @param string $prefix
   *   Yoti user email prefix.
   * @param string $domain
   *   Email domain.
   *
   * @return string
   *   Full generated Yoti user email
   */
  private function generateEmail($prefix = 'yoti.user', $domain = 'example.com') {
    // Get the number of user name that starts with yoti.user prefix.
    $userQuery = db_select('users', 'u');
    $userQuery->fields('u', ['mail']);
    $userQuery->condition('u.mail', db_like($prefix) . '%', 'LIKE');
    $userCount = (int) $userQuery->execute()->rowCount();

    // Generate Yoti unique user email.
    $email = $prefix . "@$domain";
    if ($userCount > 0) {
      do {
        $email = $prefix . ++$userCount . "@$domain";
      } while (user_load_by_mail($email));
    }

    return $email;
  }

  /**
   * Generate user password.
   *
   * @param int $length
   *   Number of characters.
   *
   * @return string
   *   Full generated password
   */
  private function generatePassword($length = 10) {
    // Generate password.
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    // Remember to declare $pass as an array.
    $password = '';
    // Put the length -1 in cache.
    $alphaLength = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
      $n = rand(0, $alphaLength);
      $password .= $alphabet[$n];
    }

    return $password;
  }

  /**
   * Create Yoti user account.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details.
   *
   * @return int
   *   User ID
   *
   * @throws Exception
   */
  private function createUser(ActivityDetails $activityDetails) {
    $user = ['status' => 1];
    $profile = $activityDetails->getProfile();

    $userProvidedEmail = $profile->getEmailAddress() ? $profile->getEmailAddress()->getValue() : NULL;
    // If user has provided an email address and it's not in use then use it,
    // otherwise use Yoti generic email.
    $userProvidedEmailCanBeUsed = valid_email_address($userProvidedEmail) && !user_load_by_mail($userProvidedEmail);
    $userEmail = $userProvidedEmailCanBeUsed ? $userProvidedEmail : $this->generateEmail();

    // Mandatory settings.
    $user['pass'] = $this->generatePassword();
    $user['mail'] = $user['init'] = $userEmail;
    // This username must be unique and accept only a-Z,0-9, - _ @ .
    $user['name'] = $this->generateUsername($profile);

    // The first parameter is sent blank so a new user is created.
    $user = user_save('', $user);

    // Set new id.
    $userId = $user->uid;
    $this->createYotiUser($userId, $activityDetails);

    return $userId;
  }

  /**
   * Returns drupal user unique ID.
   *
   * @param int $yotiId
   *   Yoti user ID.
   * @param string $field
   *   Yoti user identifier.
   *
   * @return int
   *   User unique ID
   */
  private function getDrupalUid($yotiId, $field = 'identifier') {
    $tableName = YotiHelper::YOTI_USER_TABLE_NAME;
    $col = db_query("SELECT uid FROM `{$tableName}` WHERE `{$field}` = '$yotiId' Limit 1")->fetchCol();
    return $col ? reset($col) : NULL;
  }

  /**
   * Create user account with Yoti user details.
   *
   * @param int $userId
   *   Created user ID.
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details.
   *
   * @throws Exception
   */
  public function createYotiUser($userId, ActivityDetails $activityDetails) {
    $profile = $activityDetails->getProfile();
    $meta = $this->processProfileAttributes($profile);

    $this->cleanUserData($meta);

    $selfieFilename = NULL;
    if ($selfieObj = $profile->getSelfie()) {
      $selfie = $selfieObj->getValue()->getContent();
      $uploadDir = self::secureUploadDir();
      if (!is_dir($uploadDir)) {
        drupal_mkdir($uploadDir, 0777, TRUE);
      }

      $selfieFilename = md5("selfie" . time()) . '.png';
      file_put_contents("$uploadDir/$selfieFilename", $selfie);
      $meta = array_merge(
          ['selfie_filename' => $selfieFilename],
          $meta
      );
    }

    db_insert(YotiHelper::YOTI_USER_TABLE_NAME)->fields([
      'uid' => $userId,
      'identifier' => $activityDetails->getRememberMeId(),
      'data' => serialize($meta),
    ])->execute();
  }

  /**
   * Process profile attributes into an associative array.
   *
   * @param \Yoti\Entity\Profile $profile
   *   Yoti user data.
   *
   * @return array
   *   Array of process profile attributes.
   */
  private function processProfileAttributes(Profile $profile) {
    $attrsArr = [];
    $excludedAttrs = [
      Profile::ATTR_DOCUMENT_DETAILS,
      Profile::ATTR_STRUCTURED_POSTAL_ADDRESS,
    ];

    foreach ($profile->getAttributes() as $attrName => $attrObj) {
      if (in_array($attrName, $excludedAttrs) || $attrObj === NULL) {
        continue;
      }
      $value = $attrObj->getValue();
      if ($attrName === Profile::ATTR_DATE_OF_BIRTH && NULL !== $value) {
        $value = $value->format('d-m-Y');
      }
      if ($attrName === Profile::ATTR_SELFIE && NULL !== $value) {
        $value = $value->getContent();
      }
      $attrsArr[$attrName] = $value;
    }

    $ageVerificationsArr = $this->processAgeVerifications($profile);
    if (!empty($ageVerificationsArr)) {
      $attrsArr = array_merge(
          $attrsArr,
          $ageVerificationsArr
      );
    }
    return $attrsArr;
  }

  /**
   * Create associative array of age verifications.
   *
   * @param \Yoti\Entity\Profile $profile
   *   Yoti user profile Object.
   *
   * @return array
   *   Associative array of adge verifications.
   */
  private function processAgeVerifications(Profile $profile) {
    $ageVerificationsArr = [];
    $ageStr = '';
    /** @var \Yoti\Entity\AgeVerification $ageVerification */
    foreach ($profile->getAgeVerifications() as $ageAttr => $ageVerification) {
      $attrName = str_replace(':', '_', ucwords($ageAttr, '_'));
      $result = $ageVerification->getResult() ? 'Yes' : 'No';
      $ageVerificationsArr[$attrName] = $result;
      $ageStr .= $attrName . ': ' . $result . ',';
    }
    if (!empty($ageStr)) {
      // This is for profile display.
      $ageVerificationsArr[self::AGE_VERIFICATION_ATTR] = rtrim($ageStr, ',');
    }
    return $ageVerificationsArr;
  }

  /**
   * Remove unwanted profile attributes.
   *
   * @param mixed $meta
   *   User profile data.
   */
  private function cleanUserData(&$meta) {
    // Don't save selfie to the db.
    unset($meta[Profile::ATTR_SELFIE]);
  }

  /**
   * Delete Yoti user from Drupal.
   *
   * @param int $userId
   *   Drupal user id.
   */
  private function deleteYotiUser($userId) {
    db_delete(YotiHelper::YOTI_USER_TABLE_NAME)->condition('uid', $userId)->execute();
  }

  /**
   * Submit user log in request.
   *
   * @param int $userId
   *   Drupal user ID.
   */
  private function loginUser($userId) {
    $form_state['uid'] = $userId;
    user_login_submit([], $form_state);
  }

  /**
   * Returns Yoti upload directory path.
   *
   * @param bool $realPath
   *   If true returns directory real path, false otherwise.
   *
   * @return string
   *   Yoti upload directory path
   */
  public static function uploadDir($realPath = TRUE) {
    return $realPath ? drupal_realpath('yoti://') : 'yoti://';
  }

  /**
   * Returns Yoti upload directory URL.
   *
   * @return string
   *   Yoti upload directory URL
   */
  public static function uploadUrl() {
    return file_create_url(self::uploadDir());
  }

  /**
   * Returns Yoti secure upload directory path.
   *
   * @param bool $realPath
   *   If true returns directory real path, false otherwise.
   *
   * @return string
   *   Yoti upload directory path
   */
  public static function secureUploadDir($realPath = TRUE) {
    $yotiUploadDir = self::YOTI_SECURE_FILES_UPLOAD_LOCATION;
    return $realPath ? drupal_realpath($yotiUploadDir) : $yotiUploadDir;
  }

  /**
   * Returns Yoti secure upload directory URL.
   *
   * @return string
   *   Yoti upload directory URL
   */
  public static function secureUploadUrl() {
    return file_create_url(self::secureUploadDir());
  }

  /**
   * Returns Yoti config data.
   *
   * @return array
   *   Yoti config data
   */
  public static function getConfig() {
    $pem = variable_get('yoti_pem');
    $name = $contents = NULL;
    if (!empty($pem)) {
      $file = file_load($pem);
      if (is_object($file)) {
        $name = $file->uri;
        $fileFullPath = drupal_realpath($name);
        $contents = (!empty($fileFullPath)) ? file_get_contents($fileFullPath) : NULL;
      }
    }

    $config = [
      'yoti_app_id' => variable_get('yoti_app_id'),
      'yoti_scenario_id' => variable_get('yoti_scenario_id'),
      'yoti_sdk_id' => variable_get('yoti_sdk_id'),
      'yoti_company_name' => variable_get('yoti_company_name'),
      'yoti_age_verification' => variable_get('yoti_age_verification'),
      'yoti_only_existing' => variable_get('yoti_only_existing'),
      'yoti_success_url' => variable_get('yoti_success_url', '/user'),
      'yoti_fail_url' => variable_get('yoti_fail_url', '/'),
      'yoti_user_email' => variable_get('yoti_user_email'),
      'yoti_pem' => compact('name', 'contents'),
    ];

    return $config;
  }

  /**
   * Returns Yoti API URL.
   *
   * @return null|string
   *   Yoti API URL
   */
  public static function getLoginUrl() {
    $config = self::getConfig();
    if (empty($config['yoti_app_id'])) {
      return NULL;
    }
    return YotiClient::getLoginUrl($config['yoti_app_id']);
  }

  /**
   * Get Yoti user profile data.
   *
   * @param int $userUid
   *   Yoti user Id.
   *
   * @return null|array
   *   Yoti user profile data.
   */
  public static function getYotiUserProfile($userUid) {
    $tableName = YotiHelper::YOTI_USER_TABLE_NAME;
    $userProfileArr = NULL;
    $userUid = (int) $userUid;
    if ($userUid) {
      $userProfileArr = db_query("SELECT * from `{$tableName}` WHERE uid=$userUid Limit 1")->fetchAssoc();
    }
    return $userProfileArr;
  }

  /**
   * Get Yoti user profile attributes.
   *
   * @return array
   *   Yoti user profile attributes
   */
  public static function getUserProfileAttributes() {
    return [
      Profile::ATTR_SELFIE => 'Selfie',
      Profile::ATTR_FULL_NAME => 'Full Name',
      Profile::ATTR_GIVEN_NAMES => 'Given Name(s)',
      Profile::ATTR_FAMILY_NAME => 'Family Name',
      Profile::ATTR_PHONE_NUMBER => 'Mobile Number',
      Profile::ATTR_EMAIL_ADDRESS => 'Email Address',
      Profile::ATTR_DATE_OF_BIRTH => 'Date Of Birth',
      self::AGE_VERIFICATION_ATTR => 'Age Verified',
      Profile::ATTR_POSTAL_ADDRESS => 'Postal Address',
      Profile::ATTR_GENDER => 'Gender',
      Profile::ATTR_NATIONALITY => 'Nationality',
    ];
  }

}
