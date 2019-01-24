<?php

namespace Drupal\yoti;

use Drupal\Core\Url;
use Drupal;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\yoti\Models\YotiUserModel;
use Exception;
use Yoti\ActivityDetails;
use Yoti\YotiClient;
use Yoti\Entity\Profile;
use Yoti\Entity\AgeVerification;

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
     * Yoti selfie filename attribute.
     */
  const ATTR_SELFIE_FILE_NAME = 'selfie_filename';

  /**
     * Yoti Drupal SDK identifier.
     */
  const SDK_IDENTIFIER = 'Drupal';

  /**
     * Age verification attribute.
     */
  const AGE_VERIFICATION_ATTR = 'age_verified';

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
   * Yoti plugin config data.
   *
   * @var array
   */
  private $config;

  /**
   * YotiHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   Entity Type Manager.
   */
  public function __construct(EntityTypeManagerInterface $entityManager) {
    try {
      $this->userStorage = $entityManager->getStorage('user');
    }
    catch (\Exception $e) {
      YotiHelper::setFlash('Could not retrieve user data', 'error');
    }

    $this->config = self::getConfig();
  }

  /**
   * Link Drupal user to Yoti user.
   *
   * @param mixed $currentUser
   *   Drupal user object.
   *
   * @return mixed
   *   TRUE|FALSE|redirect.
   */
  public function link($currentUser = NULL) {
    if (!$currentUser) {
      $currentUser = Drupal::currentUser();
    }

    $token = (!empty($_GET['token'])) ? $_GET['token'] : NULL;

    // If no token then ignore.
    if (!$token) {
      YotiHelper::setFlash('Could not get Yoti token.', 'error');

      return FALSE;
    }

    // Init yoti client and attempt to request user details.
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
      YotiHelper::setFlash('Yoti could not successfully connect to your account.', 'error');

      return FALSE;
    }

    if (!$this->passedAgeVerification($profile)) {
      self::setFlash("Could not log you in as you haven't passed the age verification", 'error');
      return FALSE;
    }

    // Check if Yoti user exists.
    $drupalUid = $this->getDrupalUid($activityDetails->getRememberMeId());

    // If Yoti user exists in db but isn't the current account
    // then remove it from Yoti table.
    if (
        $drupalUid
        && $currentUser
        && $currentUser->id() !== $drupalUid
        && !User::load($drupalUid)
    ) {
      // Remove user account.
      YotiUserModel::deleteYotiUserById($drupalUid);
    }

    // If user isn't logged in.
    if ($currentUser->isAnonymous()) {
      // Register new user.
      if (!$drupalUid) {
        $errMsg = NULL;

        // Attempt to connect by email.
        $drupalUid = $this->shouldLoginByEmail($activityDetails);

        // If config 'only log in existing user' is enabled then check
        // if user exists, if not then redirect to login page.
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
            // Generate the registration path.
            $pathToRegister = YotiHelper::getPathFullUrl(Url::fromRoute('yoti.register')->getInternalPath());
            return new TrustedRedirectResponse($pathToRegister);
          }
        }

        // No user id? no account.
        if (!$drupalUid) {
          // If unable to create user then bail.
          self::setFlash("Could not create user account. $errMsg", 'error');

          return FALSE;
        }
      }

      // Log user in.
      $this->loginUser($drupalUid);
    }
    else {
      // If current logged in user doesn't match yoti user registered then bail.
      if ($drupalUid && $currentUser->id() !== $drupalUid) {
        self::setFlash('This Yoti account is already linked to another account.', 'error');
      }
      // If Drupal user not found in Yoti table then create new Yoti user.
      elseif (!$drupalUid) {
        $this->createYotiUser($currentUser->id(), $activityDetails);
      }
    }

    return TRUE;
  }

  /**
   * Check if age verification applies and is valid.
   *
   * @param Profile $profile
   *   Yoti user profile Object.
   *
   * @return bool
   *   Return TRUE or FALSE
   */
  public function passedAgeVerification(Profile $profile) {
    return !($this->config['yoti_age_verification'] && !$this->oneAgeIsVerified($profile));
  }

  private function oneAgeIsVerified(Profile $profile) {
    $ageVerificationsArr = $this->processAgeVerifications($profile);
    return empty($ageVerificationsArr) || in_array('Yes', array_values($ageVerificationsArr));
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
   * @param Profile $profile
   *   Yoti user data.
   * @param string $prefix
   *   Yoti username prefix.
   *
   * @return string
   *   Yoti username.
   */
  private function generateUsername(Profile $profile, $prefix = 'yoti.user') {
    $givenNames = $this->getUserGivenNames($profile);
    $familyName = $profile->getFamilyName() ? $profile->getFamilyName()->getValue() : NULL;

    if (NULL !== $givenNames && NULL !== $familyName) {
      $userFullName = $givenNames . ' ' . $familyName;
      $userProvidedPrefix = strtolower(str_replace(' ', '.', $userFullName));
      $prefix = $this->isValidUsername($userProvidedPrefix) ? $userProvidedPrefix : $prefix;
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
   * @param Profile $profile
   *   Yoti user details.
   *
   * @return null|string
   *   Return single user given name
   */
  private function getUserGivenNames(Profile $profile) {
    $givenNamesObj = $profile->getGivenNames();
    $givenNames = $givenNamesObj ? $givenNamesObj->getValue() : NULL;
    $givenNamesArr = explode(' ', $givenNames);
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
    return (NULL === user_validate_name($username));
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
    $profile = $activityDetails->getProfile();
    $emailObj = $profile->getEmailAddress();
    $userProvidedEmail = $emailObj ? $emailObj->getValue() : NULL;

    // If user has provided an email address and it's not in use then use it,
    // otherwise use Yoti generic email.
    $isValidEmail = Drupal::service('email.validator')->isValid($userProvidedEmail);
    $userProvidedEmailCanBeUsed = $isValidEmail && !user_load_by_mail($userProvidedEmail);
    $userEmail = $userProvidedEmailCanBeUsed ? $userProvidedEmail : $this->generateEmail();

    // Mandatory settings.
    $user->setPassword($this->generatePassword());
    $user->enforceIsNew();
    $user->setEmail($userEmail);
    // This username must be unique and accept only a-Z,0-9, - _ @ .
    $user->setUsername($this->generateUsername($profile));

    // Optional settings.
    $user->set('init', 'email');
    $user->set('langcode', $language);
    $user->set('preferred_langcode', $language);
    $user->set('preferred_admin_langcode', $language);
    $user->activate();
    if (!$user->save()) {
      throw new \Exception('Could not save Yoti user');
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
  private function getDrupalUid($yotiId, $field = 'identifier') {
    $col = YotiUserModel::getUserUidByYotiId($yotiId, $field);
    return $col ? reset($col) : NULL;
  }

  /**
   * Create Yoti user.
   *
   * @param int $userId
   *   Drupal user ID.
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user data.
   *
   * @throws Exception
   */
  public function createYotiUser($userId, ActivityDetails $activityDetails) {
    $profile = $activityDetails->getProfile();
    $meta = $this->processProfileAttributes($profile);

    $this->cleanUserData($meta);

    $selfieFilename = NULL;
    if ($selfie = $profile->getSelfie()) {
      $content = $selfie->getValue()->getContent();
      $uploadDir = self::uploadDir(FALSE);
      if (!is_dir($uploadDir)) {
        Drupal::service('file_system')->mkdir($uploadDir, 0777, TRUE);
      }

      $selfieFilename = md5("selfie_$userId" . time()) . '.png';
      file_put_contents(self::uploadDir() . "/$selfieFilename", $content);

      $meta[self::ATTR_SELFIE_FILE_NAME] = $selfieFilename;
    }

    YotiUserModel::createYotiUser($userId, $activityDetails, $meta);
  }

  private function processProfileAttributes(Profile $profile) {
    $attrsArr = [];
    $excludedAttrs = [
        Profile::ATTR_DOCUMENT_DETAILS,
        Profile::ATTR_STRUCTURED_POSTAL_ADDRESS
    ];

    foreach($profile->getAttributes() as $attrName => $attrObj) {
        if (in_array($attrName, $excludedAttrs)) {
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

  private function processAgeVerifications(Profile $profile){
    $ageVerificationsArr = [];
    $ageStr = '';
    /** @var AgeVerification $ageVerification */
    foreach($profile->getAgeVerifications() as $ageAttr => $ageVerification) {
        $attrName = str_replace(':', '_', ucwords($ageAttr, '_'));
        $result = $ageVerification->getResult() ? 'Yes' : 'No';
        $ageVerificationsArr[$attrName] = $result;
        $ageStr .= $attrName . ': ' . $result . ',';
    }
    if (!empty($ageStr)) {
        // this is for profile display
        $ageVerificationsArr[self::AGE_VERIFICATION_ATTR] = rtrim($ageStr, ',');
    }
    return $ageVerificationsArr;
  }

  /**
   * Remove unwanted profile attributes.
   *
   * @param mixed $profileArr
   *   User profile data.
   */
  private function cleanUserData(&$profileArr) {
    $providedAttr = array_keys($profileArr);
    $wantedAttr = array_keys(self::getUserProfileAttributes());
    $unwantedAttr = array_diff($providedAttr, $wantedAttr);
    foreach ($unwantedAttr as $attr) {
      unset($profileArr[$attr]);
    }
    // Don't save selfie to the db.
    unset($profileArr[Profile::ATTR_SELFIE]);
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
    return $realPath ? Drupal::service('file_system')->realpath($yotiPemUploadDir) : $yotiPemUploadDir;
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
      'yoti_age_verification' => $settings->get('yoti_age_verification'),
      'yoti_company_name' => $settings->get('yoti_company_name'),
      'yoti_pem' => compact('name', 'contents'),
    ];

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
   * Attempt to log user in by email.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details Object.
   *
   * @return null|int
   *   Yoti user Id.
   */
  private function shouldLoginByEmail(ActivityDetails $activityDetails) {
    $drupalUid = NULL;
    $profile = $activityDetails->getProfile();
    $emailObj = $profile->getEmailAddress();

    $email = $emailObj ? $emailObj->getValue() : NULL;
    $emailConfig = $this->config['yoti_user_email'];
    // Attempt to connect by email.
    if ($email && !empty($emailConfig)) {
      $byMail = user_load_by_mail($email);
      if ($byMail) {
        $drupalUid = $byMail->id();
        $this->createYotiUser($drupalUid, $activityDetails);
      }
    }
    return $drupalUid;
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
