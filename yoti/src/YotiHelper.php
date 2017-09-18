<?php

namespace Drupal\yoti;

use Drupal\Core\Url;
use Drupal;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\yoti\Models\YotiUserModel;
use Exception;
use Yoti\ActivityDetails;
use Yoti\YotiClient;

require_once __DIR__ . '/../sdk/boot.php';

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
   * Yoti link button default text.
   */
  const YOTI_LINK_BUTTON_DEFAULT_TEXT = 'Use Yoti';

  /**
   * Yoti PEM file upload location.
   */
  const YOTI_PEM_FILE_UPLOAD_LOCATION = 'private://yoti';

  /**
   * MySQL Database connection.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * User storage Interface.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * Yoti user profile attributes.
   *
   * @var array
   */
  public static $profileFields = [
    ActivityDetails::ATTR_SELFIE => 'Selfie',
    ActivityDetails::ATTR_PHONE_NUMBER => 'Phone number',
    ActivityDetails::ATTR_DATE_OF_BIRTH => 'Date of birth',
    ActivityDetails::ATTR_GIVEN_NAMES => 'Given names',
    ActivityDetails::ATTR_FAMILY_NAME => 'Family name',
    ActivityDetails::ATTR_NATIONALITY => 'Nationality',
    ActivityDetails::ATTR_GENDER => 'Gender',
    ActivityDetails::ATTR_EMAIL_ADDRESS => 'Email Address',
    ActivityDetails::ATTR_POSTAL_ADDRESS => 'Postal Address',
  ];

  /**
   * YotiHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityManager
   *   Entity Type Manager.
   */
  public function __construct(EntityTypeManager $entityManager) {
    $this->userStorage = $entityManager->getStorage('user');
  }

  /**
   * Running mock requests instead of going to yoti.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public static function mockRequests() {
    return defined('YOTI_MOCK_REQUEST') && YOTI_MOCK_REQUEST;
  }

  /**
   * Link Drupal user to Yoti user.
   *
   * @param mixed $currentUser
   *   Drupal user object.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function link($currentUser = NULL) {
    if (!$currentUser) {
      $currentUser = Drupal::currentUser();
    }

    $config = YotiHelper::getConfig();

    $token = (!empty($_GET['token'])) ? $_GET['token'] : NULL;

    // If no token then ignore.
    if (!$token) {
      YotiHelper::setFlash('Could not get Yoti token.', 'error');

      return FALSE;
    }

    // Init yoti client and attempt to request user details.
    try {
      $yotiClient = new YotiClient($config['yoti_sdk_id'], $config['yoti_pem']['contents']);
      $yotiClient->setMockRequests(self::mockRequests());
      $activityDetails = $yotiClient->getActivityDetails($token);
    }
    catch (Exception $e) {
      YotiHelper::setFlash('Yoti could not successfully connect to your account.', 'error');

      return FALSE;
    }

    // If unsuccessful then bail.
    if ($yotiClient->getOutcome() != YotiClient::OUTCOME_SUCCESS) {
      YotiHelper::setFlash('Yoti could not successfully connect to your account.', 'error');

      return FALSE;
    }

    // Check if Yoti user exists.
    $drupalYotiUid = $this->getDrupalUid($activityDetails->getUserId());

    // If Yoti user exists in db but isn't the current account
    // then remove it from Yoti table.
    if (
        $drupalYotiUid
        && $currentUser
        && $currentUser->id() != $drupalYotiUid
        && !User::load($drupalYotiUid)
    ) {
      // Remove user account.
      YotiUserModel::deleteYotiUserById($drupalYotiUid);
    }

    // If user isn't logged in.
    if ($currentUser->isAnonymous()) {
      // Register new user.
      if (!$drupalYotiUid) {
        $errMsg = NULL;

        // Attempt to connect by email.
        if (!empty($config['yoti_user_email'])) {
          if (($email = $activityDetails->getEmailAddress())) {
            $byMail = user_load_by_mail($email);
            if ($byMail) {
              $drupalYotiUid = $byMail->id();
              $this->createYotiUser($drupalYotiUid, $activityDetails);
            }
          }
        }

        // If config 'only log in existing user' is enabled then check
        // if user exists, if not then redirect to login page.
        if (!$drupalYotiUid) {
          if (empty($config['yoti_only_existing'])) {
            try {
              $drupalYotiUid = $this->createUser($activityDetails);
            }
            catch (Exception $e) {
              $errMsg = $e->getMessage();
            }
          }
          else {
            self::storeYotiUser($activityDetails);
            // Generate the registration path.
            $pathToRegister = YotiHelper::getPathFullUrl(Url::fromRoute('yoti.register')->getInternalPath());
            return new TrustedRedirectResponse($pathToRegister);
          }
        }

        // No user id? no account.
        if (!$drupalYotiUid) {
          // If unable to create user then bail.
          self::setFlash("Could not create user account. $errMsg", 'error');

          return FALSE;
        }
      }

      // Log user in.
      $this->loginUser($drupalYotiUid);
    }
    else {
      // If current logged in user doesn't match yoti user registered then bail.
      if ($drupalYotiUid && $currentUser->id() != $drupalYotiUid) {
        self::setFlash('This Yoti account is already linked to another account.', 'error');
      }
      // If Drupal user not found in yoti table then create new yoti user.
      elseif (!$drupalYotiUid) {
        $this->createYotiUser($currentUser->id(), $activityDetails);
        self::setFlash('Your Yoti account has been successfully linked.');
      }
    }

    return TRUE;
  }

  /**
   * Generate URL based on page path.
   *
   * @param mixed $path
   *   Page path.
   *
   * @return string
   *   Generated path URL
   */
  public static function getPathFullUrl($path = NULL) {
    // Get the root path including any subdomain.
    $fullUrl = Drupal::request()->getBaseUrl();
    if (!empty($path)) {
      // Add the target path to the root path.
      $fullUrl .= ($path[0] === '/') ? $path : '/' . $path;
    }

    return $fullUrl;
  }

  /**
   * Unlink account from currently logged in.
   */
  public function unlink() {
    $currentUser = Drupal::currentUser();

    // Unlink Yoti user.
    if (!$currentUser->isAnonymous()) {
      YotiUserModel::deleteYotiUserById($currentUser->id());
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Store Yoti user in the session.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details.
   */
  public static function storeYotiUser(ActivityDetails $activityDetails) {
    $session = Drupal::service('session');
    if (!$session->isStarted()) {
      $session->migrate();
    }
    $_SESSION['yoti-user'] = serialize($activityDetails);
  }

  /**
   * Retrieve Yoti user from the session.
   *
   * @return \Yoti\ActivityDetails|null
   *   Yoti user details.
   */
  public static function getYotiUserFromStore() {
    $session = Drupal::service('session');
    if (!$session->isStarted()) {
      $session->migrate();
    }
    return array_key_exists('yoti-user', $_SESSION) ? unserialize($_SESSION['yoti-user']) : NULL;
  }

  /**
   * Remove Yoti user from the session.
   */
  public static function clearYotiUserStore() {
    $session = Drupal::service('session');
    if (!$session->isStarted()) {
      $session->migrate();
    }
    unset($_SESSION['yoti-user']);
  }

  /**
   * Set notification message.
   *
   * @param string $message
   *   Notification message.
   * @param string $type
   *   Notification status.
   */
  public static function setFlash($message, $type = 'status') {
    drupal_set_message($message, $type);
  }

  /**
   * Generate Yoti username.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user data.
   * @param string $prefix
   *   Yoti username prefix.
   *
   * @return string
   *   Yoti username.
   */
  private function generateUsername(ActivityDetails $activityDetails, $prefix = 'yoti.user') {
    $givenNames = $this->getUserGivenNames($activityDetails);
    $familyName = $activityDetails->getFamilyName();

    if (!empty($givenNames) && !empty($familyName)) {
      $userFullName = $givenNames . " " . $familyName;
      $userProvidedPrefix = strtolower(str_replace(" ", ".", $userFullName));
      $prefix = ($this->isValidUsername($userProvidedPrefix)) ? $userProvidedPrefix : $prefix;
    }

    // Get the number of user name that starts with prefix.
    $usernameCount = YotiUserModel::getUsernameCountByPrefix($prefix);

    // Generate username.
    $username = $prefix;
    if ($usernameCount > 0) {
      do {
        $username = $prefix . ++$usernameCount;
      } while ($this->userStorage->loadByProperties(['name' => $username]));
    }

    return $username;
  }

  /**
   * Generate Yoti user email.
   *
   * @param string $prefix
   *   User email prefix.
   * @param string $domain
   *   Email domain name.
   *
   * @return string
   *   Yoti user email.
   */
  private function generateEmail($prefix = 'yoti.user', $domain = 'example.com') {
    // Get the number of user name that starts with yoti.user prefix.
    $userEmailCount = YotiUserModel::getUserEmailCountByPrefix($prefix);

    // Generate Yoti unique user email.
    $email = "{$prefix}@{$domain}";
    if ($userEmailCount > 0) {
      do {
        $email = $prefix . ++$userEmailCount . "@$domain";
      } while ($this->userStorage->loadByProperties(['mail' => $email]));
    }

    return $email;
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
    $givenNamesArr = explode(" ", $activityDetails->getGivenNames());
    return (count($givenNamesArr) > 1) ? $givenNamesArr[0] : $givenNames;
  }

  /**
   * Check a username has valid characters.
   *
   * @param string $username
   *   Username to be validated.
   *
   * @return bool|int
   *   Return TRUE or FALSE
   */
  protected function isValidUsername($username) {
    return (empty(user_validate_name($username)));
  }

  /**
   * User generic password.
   *
   * @param int $length
   *   Password length.
   *
   * @return string
   *   Generated password.
   */
  private function generatePassword($length = 10) {
    // Generate user password.
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
   * Create Drupal user.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details.
   *
   * @return int
   *   Yoti user ID.
   *
   * @throws Exception
   */
  private function createUser(ActivityDetails $activityDetails) {
    $language = Drupal::languageManager()->getCurrentLanguage()->getId();
    $user = User::create();

    $userProvidedEmail = $activityDetails->getEmailAddress();
    // If user has provided an email address and it's not in use then use it,
    // otherwise use Yoti generic email.
    $isValidEmail = Drupal::service('email.validator')->isValid($userProvidedEmail);
    $userProvidedEmailCanBeUsed = $isValidEmail && !user_load_by_mail($userProvidedEmail);
    $userEmail = ($userProvidedEmailCanBeUsed) ? $userProvidedEmail : $this->generateEmail();

    // Mandatory settings.
    $user->setPassword($this->generatePassword());
    $user->enforceIsNew();
    $user->setEmail($userEmail);
    // This username must be unique and accept only a-Z,0-9, - _ @ .
    $user->setUsername($this->generateUsername($activityDetails));

    // Optional settings.
    $user->set("init", 'email');
    $user->set("langcode", $language);
    $user->set("preferred_langcode", $language);
    $user->set("preferred_admin_langcode", $language);
    // $user->set("setting_name", 'setting_value');.
    $user->activate();
    if (!$user->save()) {
      throw new Exception("Could not save Yoti user");
    }

    // Set new user ID.
    $userId = $user->id();
    $this->createYotiUser($userId, $activityDetails);

    return $userId;
  }

  /**
   * Get Drupal user UID.
   *
   * @param int $yotiId
   *   Yoti user ID.
   * @param string $field
   *   Drupal option field name.
   *
   * @return int
   *   Returns user UID.
   */
  private function getDrupalUid($yotiId, $field = "identifier") {
    $col = YotiUserModel::getUserUidByYotiId($yotiId, $field);
    return ($col) ? reset($col) : NULL;
  }

  /**
   * Create Yoti user.
   *
   * @param int $userId
   *   Drupal user ID.
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user data.
   */
  public function createYotiUser($userId, ActivityDetails $activityDetails) {
    $meta = $activityDetails->getProfileAttribute();
    // Don't save user selfie in the Database.
    unset($meta[ActivityDetails::ATTR_SELFIE]);

    $selfieFilename = NULL;
    if (($content = $activityDetails->getSelfie())) {
      $uploadDir = self::uploadDir(FALSE);
      if (!is_dir($uploadDir)) {
        Drupal::service('file_system')->mkdir($uploadDir, 0777, TRUE);
      }

      $selfieFilename = md5("selfie_$userId" . time()) . ".png";
      file_put_contents(self::uploadDir() . "/$selfieFilename", $content);

      $meta['selfie_filename'] = $selfieFilename;
    }

    YotiUserModel::createYotiUser($userId, $activityDetails, $meta, $selfieFilename);
  }

  /**
   * Logs user in.
   *
   * @param int $userId
   *   User ID.
   */
  private function loginUser($userId) {
    if ($user = User::load($userId)) {
      user_login_finalize($user);
    }
  }

  /**
   * File upload directory.
   *
   * @param bool $realPath
   *   File path.
   *
   * @return string
   *   Directory full path.
   */
  public static function uploadDir($realPath = TRUE) {
    $yotiPemUploadDir = YotiHelper::YOTI_PEM_FILE_UPLOAD_LOCATION;
    return ($realPath) ? Drupal::service('file_system')->realpath($yotiPemUploadDir) : $yotiPemUploadDir;
  }

  /**
   * File upload dir URL.
   *
   * @return string
   *   Full upload dir URL.
   */
  public static function uploadUrl() {
    return file_create_url(self::uploadDir(FALSE));
  }

  /**
   * Yoti config data.
   *
   * @return array
   *   Config data as array.
   */
  public static function getConfig() {
    $settings = Drupal::config('yoti.settings');

    $pem = $settings->get('yoti_pem');
    $name = $contents = NULL;
    if ($pem) {
      $file = File::load($pem[0]);
      $name = $file->getFileUri();
      $contents = file_get_contents(\Drupal::service('file_system')->realpath($name));
    }
    $config = [
      'yoti_app_id' => $settings->get('yoti_app_id'),
      'yoti_scenario_id' => $settings->get('yoti_scenario_id'),
      'yoti_sdk_id' => $settings->get('yoti_sdk_id'),
      'yoti_only_existing' => $settings->get('yoti_only_existing'),
      'yoti_success_url' => $settings->get('yoti_success_url') ?: '/user',
      'yoti_fail_url' => $settings->get('yoti_fail_url') ?: '/',
      'yoti_user_email' => $settings->get('yoti_user_email'),
      'yoti_company_name' => $settings->get('yoti_company_name'),
      'yoti_pem' => [
        'name' => $name,
        'contents' => $contents,
      ],
    ];

    if (self::mockRequests()) {
      $config = array_merge($config, require __DIR__ . '/../sdk/sample-data/config.php');
    }

    return $config;
  }

  /**
   * Get Yoti Dashboard app URL.
   *
   * @return null|string
   *   Yoti App URL.
   */
  public static function getLoginUrl() {
    $config = self::getConfig();
    if (empty($config['yoti_app_id'])) {
      return NULL;
    }

    return YotiClient::getLoginUrl($config['yoti_app_id']);
  }

  /**
   * Get user postal address.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user profile data.
   *
   * @return mixed
   *   User postal address.
   */
  public static function getUserPostalAddress(ActivityDetails $activityDetails) {
    $userProfile = $activityDetails->getProfileAttribute();
    // Check first if postal address is not empty.
    $postalAddress = $activityDetails->getPostalAddress();
    $value = (!empty($postalAddress)) ? $postalAddress : NULL;
    if (empty($value) && array_key_exists('data', $userProfile)) {
      $dataArr = unserialize($userProfile['data']);
      if (array_key_exists(ActivityDetails::ATTR_POSTAL_ADDRESS, $dataArr)) {
        $value = $dataArr[ActivityDetails::ATTR_POSTAL_ADDRESS];
      }
    }
    return $value;
  }

}
