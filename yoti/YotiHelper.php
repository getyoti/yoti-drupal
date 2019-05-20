<?php

use Yoti\ActivityDetails;
use Yoti\YotiClient;

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
  const YOTI_SDK_JAVASCRIPT_LIBRARY = 'https://sdk.yoti.com/clients/browser.2.0.1.js';

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
    }
    catch (Exception $e) {
      self::setFlash('Yoti could not successfully connect to your account.', 'error');

      return FALSE;
    }

    // If unsuccessful then bail.
    if ($yotiClient->getOutcome() !== YotiClient::OUTCOME_SUCCESS) {
      self::setFlash('Yoti could not successfully connect to your account.', 'error');

      return FALSE;
    }

    if (!$this->passedAgeVerification($activityDetails)) {
      return FALSE;
    }

    // Check if Yoti user exists.
    $drupalYotiUid = $this->getDrupalUid($activityDetails->getUserId());

    // If Yoti user exists in db but isn't linked to a drupal account
    // (orphaned row) then delete it.
    if (
        $drupalYotiUid
        && $currentUser
        && $currentUser->uid !== $drupalYotiUid
        && !user_load($drupalYotiUid)
    ) {
      // Remove users account.
      $this->deleteYotiUser($drupalYotiUid);
    }

    // If user isn't logged in.
    if (!$currentUser->uid) {
      // Register new user.
      if (!$drupalYotiUid) {
        $errMsg = NULL;

        // Attempt to connect by email.
        $drupalYotiUid = $this->shouldLoginByEmail($activityDetails);

        // If config only existing enabled then check if user exists, if not
        // then redirect to login page.
        if (!$drupalYotiUid) {
          if (empty($this->config['yoti_only_existing'])) {
            try {
              $drupalYotiUid = $this->createUser($activityDetails);
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
        if (!$drupalYotiUid) {
          // If couldn't create user then bail.
          self::setFlash("Could not create user account. $errMsg", 'error');

          return FALSE;
        }
      }

      // Log user in.
      $this->loginUser($drupalYotiUid);
    }
    else {
      // If currently logged in user doesn't match Yoti user registered
      // then bail.
      if ($drupalYotiUid && $currentUser->uid !== $drupalYotiUid) {
        self::setFlash('This Yoti account is already linked to another account.', 'error');
      }
      // If Drupal user not found in yoti table then create new yoti user.
      elseif (!$drupalYotiUid) {
        $this->createYotiUser($currentUser->uid, $activityDetails);
        self::setFlash('Your Yoti account has been successfully linked.');
      }
    }

    return TRUE;
  }

  /**
   * Check if age verification applies and is valid.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user profile Object.
   *
   * @return bool
   *   Return TRUE or FALSE
   */
  public function passedAgeVerification(ActivityDetails $activityDetails) {
    $ageVerified = $activityDetails->isAgeVerified();
    if ($this->config['yoti_age_verification'] && is_bool($ageVerified) && !$ageVerified) {
      $verifiedAge = $activityDetails->getVerifiedAge();
      self::setFlash("Could not log you in as you haven't passed the age verification ({$verifiedAge})", 'error');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Attempt to log user in by email.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details Object.
   *
   * @return null|int
   *   Yoti user Id.
   *
   * @throws Exception
   */
  private function shouldLoginByEmail(ActivityDetails $activityDetails) {
    $drupalYotiUid = NULL;
    $emailConfig = $this->config['yoti_user_email'];
    $email = $activityDetails->getEmailAddress();
    // Attempt to connect by email.
    if ($email && !empty($emailConfig)) {
      $byMail = user_load_by_mail($email);
      if ($byMail) {
        $drupalYotiUid = $byMail->uid;
        $this->createYotiUser($drupalYotiUid, $activityDetails);
      }
    }
    return $drupalYotiUid;
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
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details Object.
   * @param string $prefix
   *   Yoti user nickname prefix.
   *
   * @return string
   *   Yoti generic username.
   */
  private function generateUsername(ActivityDetails $activityDetails, $prefix = 'yoti.user') {
    $givenName = $this->getUserGivenNames($activityDetails);
    $familyName = $activityDetails->getFamilyName();

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
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details.
   *
   * @return null|string
   *   Return single user given name
   */
  private function getUserGivenNames(ActivityDetails $activityDetails) {
    $givenNames = $activityDetails->getGivenNames();
    $givenNamesArr = explode(' ', $activityDetails->getGivenNames());
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

    $userProvidedEmail = $activityDetails->getEmailAddress();
    // If user has provided an email address and it's not in use then use it,
    // otherwise use Yoti generic email.
    $userProvidedEmailCanBeUsed = valid_email_address($userProvidedEmail) && !user_load_by_mail($userProvidedEmail);
    $userEmail = $userProvidedEmailCanBeUsed ? $userProvidedEmail : $this->generateEmail();

    // Mandatory settings.
    $user['pass'] = $this->generatePassword();
    $user['mail'] = $user['init'] = $userEmail;
    // This username must be unique and accept only a-Z,0-9, - _ @ .
    $user['name'] = $this->generateUsername($activityDetails);

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
   *
   * @return int
   *   User unique ID
   */
  private function getDrupalUid($yotiId) {
    $col = db_select(YotiHelper::YOTI_USER_TABLE_NAME, 'u')
      ->fields('u', array('uid'))
      ->condition('identifier', $yotiId)
      ->range(0, 1)
      ->execute()
      ->fetchCol();
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
    $meta = $activityDetails->getProfileAttribute();

    $this->cleanUserData($meta);

    $selfieFilename = NULL;
    if ($content = $activityDetails->getSelfie()) {
      $uploadDir = self::secureUploadDir();
      if (!is_dir($uploadDir)) {
        drupal_mkdir($uploadDir, 0777, TRUE);
      }

      $selfieFilename = md5("selfie" . time()) . '.png';
      file_put_contents("$uploadDir/$selfieFilename", $content);
      $meta['selfie_filename'] = $selfieFilename;
    }

    // Extract age verification values if the option is set in the dashboard
    // and in the Yoti's config in Drupal admin.
    $meta[self::AGE_VERIFICATION_ATTR] = 'N/A';
    $ageVerified = $activityDetails->isAgeVerified();
    if (is_bool($ageVerified) && $this->config['yoti_age_verification']) {
      $ageVerified = $ageVerified ? 'yes' : 'no';
      $verifiedAge = $activityDetails->getVerifiedAge();
      $meta[self::AGE_VERIFICATION_ATTR] = "({$verifiedAge}) : $ageVerified";
    }

    db_insert(YotiHelper::YOTI_USER_TABLE_NAME)->fields([
      'uid' => $userId,
      'identifier' => $activityDetails->getUserId(),
      'data' => serialize($meta),
    ])->execute();
  }

  /**
   * Remove unwanted profile attributes.
   *
   * @param mixed $meta
   *   User profile data.
   */
  private function cleanUserData(&$meta) {
    $providedAttr = array_keys($meta);
    $wantedAttr = array_keys(self::getUserProfileAttributes());
    $unwantedAttr = array_diff($providedAttr, $wantedAttr);

    foreach ($unwantedAttr as $attr) {
      unset($meta[$attr]);
    }

    // Don't save selfie to the db.
    unset($meta[ActivityDetails::ATTR_SELFIE]);
  }

  /**
   * Delete Yoti user from Drupal.
   *
   * @param int $userId
   *   Drupal user id.
   */
  private function deleteYotiUser($userId) {
    $this->deleteSelfie($userId);
    db_delete(YotiHelper::YOTI_USER_TABLE_NAME)->condition('uid', $userId)->execute();
  }

  /**
   * Delete selfie for given user ID.
   *
   * @param int $userId
   *   The Drupal user ID.
   */
  private function deleteSelfie($userId) {
    $dbProfile = YotiHelper::getYotiUserProfile($userId);
    if (!$dbProfile) {
      return;
    }

    $userProfileArr = unserialize($dbProfile['data']);
    if (!isset($userProfileArr['selfie_filename'])) {
      return;
    }

    $selfieFullPath = YotiHelper::selfieFilePath($userProfileArr['selfie_filename']);
    if (is_file($selfieFullPath)) {
      unlink($selfieFullPath);
    }
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
   * Returns the selfie file path.
   *
   * @param string $fileName
   *   The name of the selfie file including extension.
   *
   * @return string|null
   *   The path to the selfie or NULL when it doesn't exist.
   */
  public static function selfieFilePath($fileName) {
    $selfieFullPath = YotiHelper::secureUploadDir() . '/' . $fileName;

    // Make it backward compatible by checking the old files directory.
    $oldSelfieFullPath = YotiHelper::uploadDir() . '/' . $fileName;

    if (is_file($selfieFullPath)) {
      return $selfieFullPath;
    }
    elseif (is_file($oldSelfieFullPath)) {
      return $oldSelfieFullPath;
    }
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
    $userProfileArr = NULL;
    $userUid = (int) $userUid;
    if ($userUid) {
      $userProfileArr = db_select(YotiHelper::YOTI_USER_TABLE_NAME, 'u')
        ->fields('u')
        ->condition('uid', $userUid)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
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
      ActivityDetails::ATTR_SELFIE => 'Selfie',
      ActivityDetails::ATTR_FULL_NAME => 'Full Name',
      ActivityDetails::ATTR_GIVEN_NAMES => 'Given Name(s)',
      ActivityDetails::ATTR_FAMILY_NAME => 'Family Name',
      ActivityDetails::ATTR_PHONE_NUMBER => 'Mobile Number',
      ActivityDetails::ATTR_EMAIL_ADDRESS => 'Email Address',
      ActivityDetails::ATTR_DATE_OF_BIRTH => 'Date Of Birth',
      self::AGE_VERIFICATION_ATTR => 'Age Verified',
      ActivityDetails::ATTR_POSTAL_ADDRESS => 'Postal Address',
      ActivityDetails::ATTR_GENDER => 'Gender',
      ActivityDetails::ATTR_NATIONALITY => 'Nationality',
    ];
  }

}
