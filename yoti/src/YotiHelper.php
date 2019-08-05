<?php

namespace Drupal\yoti;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\user\Entity\User;
use Drupal\yoti\Models\YotiUserModel;
use Exception;
use Yoti\ActivityDetails;
use Yoti\YotiClient;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

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
   * Yoti Hub URL.
   */
  const YOTI_HUB_URL = 'https://hub.yoti.com';

  /**
   * Permission to view Yoti selfie images.
   */
  const YOTI_PERMISSION_VIEW_SELFIE = 'view yoti selfie images';

  /**
   * Field for selfie bin file.
   */
  const YOTI_BIN_FIELD_SELFIE = 'selfie';

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
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Yoti plugin config data.
   *
   * @var \Drupal\yoti\YotiConfigInterface
   */
  private $config;

  /**
   * Yoti SDK Service.
   *
   * @var \Drupal\yoti\YotiSdkInterface
   */
  private $sdk;

  /**
   * Cache tag invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private $cacheTagsInvalidator;

  /**
   * YotiHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   Entity Type Manager.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   Cache tags invalidator.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger Factory.
   * @param \Drupal\yoti\YotiSdkInterface $sdk
   *   Yoti SDK.
   * @param \Drupal\yoti\YotiConfigInterface $config
   *   Yoti Configuration.
   */
  public function __construct(
    EntityTypeManagerInterface $entityManager,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator = NULL,
    LoggerChannelFactoryInterface $loggerFactory = NULL,
    YotiSdkInterface $sdk = NULL,
    YotiConfigInterface $config = NULL
  ) {
    try {
      $this->userStorage = $entityManager->getStorage('user');
    }
    catch (\Exception $e) {
      YotiHelper::setFlash('Could not retrieve user data', 'error');
    }

    // Fetch services for backwards compatibility.
    // All injected services will be required in the next major release.
    if (is_null($cacheTagsInvalidator)) {
      $cacheTagsInvalidator = \Drupal::service('cache_tags.invalidator');
    }
    if (is_null($loggerFactory)) {
      $loggerFactory = \Drupal::service('logger.factory');
    }
    if (is_null($sdk)) {
      $sdk = \Drupal::service('yoti.sdk');
    }
    if (is_null($config)) {
      $config = \Drupal::service('yoti.config');
    }

    $this->config = $config;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->logger = $loggerFactory->get('yoti');
    $this->sdk = $sdk;
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
      $currentUser = \Drupal::currentUser();
    }

    $token = (!empty($_GET['token'])) ? $_GET['token'] : NULL;

    // If no token then ignore.
    if (!$token) {
      YotiHelper::setFlash('Could not get Yoti token.', 'error');

      return FALSE;
    }

    // Init yoti client and attempt to request user details.
    try {
      $yotiClient = $this->sdk->getClient();
      $activityDetails = $yotiClient->getActivityDetails($token);
    }
    catch (Exception $e) {
      YotiHelper::setFlash('Yoti could not successfully connect to your account.', 'error');

      return FALSE;
    }

    // If unsuccessful then bail.
    if ($yotiClient->getOutcome() !== YotiClient::OUTCOME_SUCCESS) {
      YotiHelper::setFlash('Yoti could not successfully connect to your account.', 'error');
      return FALSE;
    }

    if (!$this->passedAgeVerification($activityDetails)) {
      return FALSE;
    }

    // Invalidate cache for current user.
    $this->invalidateUserCache($currentUser->id());

    // Check if Yoti user exists.
    $drupalUid = $this->getDrupalUid($activityDetails->getUserId());

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
        // Attempt to connect by email.
        $drupalUid = $this->shouldLoginByEmail($activityDetails);

        // If config 'only log in existing user' is enabled then check
        // if user exists, if not then redirect to login page.
        if (!$drupalUid) {
          if (empty($this->config->getOnlyExisting())) {
            try {
              $drupalUid = $this->createUser($activityDetails);
            }
            catch (Exception $e) {
              $this->logger->error($e->getMessage());
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
          self::setFlash('Could not create user account.', 'error');

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
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user profile Object.
   *
   * @return bool
   *   Return TRUE or FALSE
   */
  public function passedAgeVerification(ActivityDetails $activityDetails) {
    $ageVerified = $activityDetails->isAgeVerified();
    if ($this->config->getAgeVerification() && is_bool($ageVerified) && !$ageVerified) {
      $verifiedAge = $activityDetails->getVerifiedAge();
      self::setFlash("Could not log you in as you haven't passed the age verification ({$verifiedAge})", 'error');
      return FALSE;
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
    $fullUrl = \Drupal::request()->getBaseUrl();
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
    $currentUser = \Drupal::currentUser();
    // Unlink Yoti user.
    if (!$currentUser->isAnonymous()) {
      $this->deleteSelfie($currentUser->id());
      YotiUserModel::deleteYotiUserById($currentUser->id());
      $this->invalidateUserCache($currentUser->id());
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Delete selfie for given user ID.
   *
   * @param int $userId
   *   The Drupal user ID.
   */
  private function deleteSelfie($userId) {
    $dbProfile = YotiUserModel::getYotiUserById($userId);
    if (!$dbProfile) {
      return;
    }

    $userProfileArr = unserialize($dbProfile['data']);
    if (!isset($userProfileArr[self::ATTR_SELFIE_FILE_NAME])) {
      return;
    }

    $selfieFileName = $userProfileArr[self::ATTR_SELFIE_FILE_NAME];
    $selfieFullPath = self::uploadDir() . '/' . $selfieFileName;
    if (is_file($selfieFullPath)) {
      unlink($selfieFullPath);
    }
  }

  /**
   * Invalidate cache for current user.
   *
   * @param int $userId
   *   The Drupal user ID.
   */
  private function invalidateUserCache($userId) {
    if ($user = $this->userStorage->load($userId)) {
      $this->cacheTagsInvalidator->invalidateTags($user->getCacheTagsToInvalidate());
    }
  }

  /**
   * Store Yoti user in the session.
   *
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user details.
   */
  public static function storeYotiUser(ActivityDetails $activityDetails) {
    $session = \Drupal::service('session');
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
    $session = \Drupal::service('session');
    if (!$session->isStarted()) {
      $session->migrate();
    }
    return array_key_exists('yoti-user', $_SESSION) ? unserialize($_SESSION['yoti-user']) : NULL;
  }

  /**
   * Remove Yoti user from the session.
   */
  public static function clearYotiUserStore() {
    $session = \Drupal::service('session');
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
    \Drupal::messenger()->addMessage($message, $type);
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
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $user = User::create();

    $userProvidedEmail = $activityDetails->getEmailAddress();
    // If user has provided an email address and it's not in use then use it,
    // otherwise use Yoti generic email.
    $isValidEmail = \Drupal::service('email.validator')->isValid($userProvidedEmail);
    $userProvidedEmailCanBeUsed = $isValidEmail && !user_load_by_mail($userProvidedEmail);
    $userEmail = $userProvidedEmailCanBeUsed ? $userProvidedEmail : $this->generateEmail();

    // Mandatory settings.
    $user->setPassword($this->generatePassword());
    $user->enforceIsNew();
    $user->setEmail($userEmail);
    // This username must be unique and accept only a-Z,0-9, - _ @ .
    $user->setUsername($this->generateUsername($activityDetails));

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
   */
  public function createYotiUser($userId, ActivityDetails $activityDetails) {
    $meta = $activityDetails->getProfileAttribute();

    $this->cleanUserData($meta);

    $selfieFilename = NULL;
    if ($content = $activityDetails->getSelfie()) {
      $uploadDir = self::uploadDir(FALSE);
      if (!is_dir($uploadDir)) {
        \Drupal::service('file_system')->mkdir($uploadDir, 0777, TRUE);
      }

      $selfieFilename = md5("selfie_$userId" . time()) . '.png';
      file_put_contents(self::uploadDir() . "/$selfieFilename", $content);

      $meta[self::ATTR_SELFIE_FILE_NAME] = $selfieFilename;
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

    $this->formatDateOfBirth($meta);

    YotiUserModel::createYotiUser($userId, $activityDetails, $meta);
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
    unset($profileArr[ActivityDetails::ATTR_SELFIE]);
  }

  /**
   * Format Date Of birth to d-m-Y.
   *
   * @param mixed $profileArr
   *   User profile data.
   */
  private function formatDateOfBirth(&$profileArr) {
    if (isset($profileArr[ActivityDetails::ATTR_DATE_OF_BIRTH])) {
      $dateOfBirth = $profileArr[ActivityDetails::ATTR_DATE_OF_BIRTH];
      $profileArr[ActivityDetails::ATTR_DATE_OF_BIRTH] = date('d-m-Y', strtotime($dateOfBirth));
    }
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
    return $realPath ? \Drupal::service('file_system')->realpath($yotiPemUploadDir) : $yotiPemUploadDir;
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
   * @deprecated use `yoti.config` service instead.
   *
   * @return array
   *   Config data as array.
   */
  public static function getConfig() {
    return \Drupal::service('yoti.config')->getSettings();
  }

  /**
   * Get Yoti app login URL.
   *
   * @deprecated use `yoti.sdk` service instead.
   *
   * @return null|string
   *   Yoti App URL.
   */
  public static function getLoginUrl() {
    return \Drupal::service('yoti.sdk')->getLoginUrl();
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
    $email = $activityDetails->getEmailAddress();
    $emailConfig = $this->config->getUserEmail();

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
