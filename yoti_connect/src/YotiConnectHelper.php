<?php

namespace Drupal\yoti_connect;

use Drupal;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Yoti\ActivityDetails;
use Yoti\YotiClient;

//require_once __DIR__ . '/sdk/boot.php';

/**
 * Class YotiConnectHelper
 *
 * @package Drupal\yoti_connect
 *
 */
class YotiConnectHelper
{
    /**
     * @var Connection
     */
    protected $database;

    /**
     * @var Drupal\Core\Entity\EntityStorageInterface
     */
    protected $userStorage;

    /**
     * @var array
     */
    public static $profileFields = array(
        ActivityDetails::ATTR_SELFIE => 'Selfie',
        ActivityDetails::ATTR_PHONE_NUMBER => 'Phone number',
        ActivityDetails::ATTR_DATE_OF_BIRTH => 'Date of birth',
        ActivityDetails::ATTR_GIVEN_NAMES => 'Given names',
        ActivityDetails::ATTR_FAMILY_NAME => 'Family name',
        ActivityDetails::ATTR_NATIONALITY => 'Nationality',
        ActivityDetails::ATTR_GENDER => 'Gender',
        ActivityDetails::ATTR_EMAIL_ADDRESS => 'Email Address',
        ActivityDetails::ATTR_POSTAL_ADDRESS => 'Postal Address',
    );

    /**
     * YotiConnectHelper constructor.
     * @param Connection $database
     * @param Drupal\Core\Entity\EntityManager $entity_manager
     */
    public function __construct(Connection $database, Drupal\Core\Entity\EntityManager $entity_manager)
    {
        $this->database = $database;
        $this->userStorage = $entity_manager->getStorage('user');
    }

    /**
     * Running mock requests instead of going to yoti
     * @return bool
     */
    public static function mockRequests()
    {
        return defined('YOTI_MOCK_REQUEST') && YOTI_MOCK_REQUEST;
    }

    /**
     * @param null $currentUser
     * @return bool
     */
    public function link($currentUser = null)
    {
        if (!$currentUser) {
            $currentUser = Drupal::currentUser();
        }

        $config = self::getConfig();
        //    print_r($config);exit;
        $token = (!empty($_GET['token'])) ? $_GET['token'] : null;

        // if no token then ignore
        if (!$token) {
            self::setFlash('Could not get Yoti token.', 'error');

            return false;
        }

        // init yoti client and attempt to request user details
        try {
            $yotiClient = new YotiClient($config['yoti_sdk_id'], $config['yoti_pem']['contents']);
            $yotiClient->setMockRequests(self::mockRequests());
            $activityDetails = $yotiClient->getActivityDetails($token);
        }
        catch (Exception $e) {
            self::setFlash('Yoti could not successfully connect to your account.', 'error');

            return false;
        }

        // if unsuccessful then bail
        if ($yotiClient->getOutcome() != YotiClient::OUTCOME_SUCCESS) {
            self::setFlash('Yoti could not successfully connect to your account.', 'error');

            return false;
        }

        // check if yoti user exists
        $drupalYotiUid = $this->getDrupalUid($activityDetails->getUserId());

        // if yoti user exists in db but isn't an actual account then remove it from yoti table
        if ($drupalYotiUid && $currentUser->id() != $drupalYotiUid && !User::load($drupalYotiUid)->id()) {
            // remove users account
            $this->deleteYotiUser($drupalYotiUid);
        }

        // if user isn't logged in
        if ($currentUser->isAnonymous()) {
            // register new user
            if (!$drupalYotiUid) {
                $errMsg = null;

                // attempt to connect by email
                if (!empty($config['yoti_connect_email'])) {
                    if (($email = $activityDetails->getProfileAttribute('email_address'))) {
                        $byMail = user_load_by_mail($email);
                        if ($byMail) {
                            $drupalYotiUid = $byMail->uid;
                            $this->createYotiUser($drupalYotiUid, $activityDetails);
                        }
                    }
                }

                // if config only existing enabled then check if user exists, if not then redirect
                // to login page
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
                        return new TrustedRedirectResponse(\Drupal\Core\Url::fromRoute('yoti_connect.register')->toString());
                    }
                }

                // no user id? no account
                if (!$drupalYotiUid) {
                    // if couldn't create user then bail
                    self::setFlash("Could not create user account. $errMsg", 'error');

                    return false;
                }
            }

            // log user in
            $this->loginUser($drupalYotiUid);
        }
        else {
            // if current logged in user doesn't match yoti user registered then bail
            if ($drupalYotiUid && $currentUser->id() != $drupalYotiUid) {
                self::setFlash('This Yoti account is already linked to another account.', 'error');
            }
            // if joomla user not found in yoti table then create new yoti user
            elseif (!$drupalYotiUid) {
                $this->createYotiUser($currentUser->id(), $activityDetails);
                self::setFlash('Your Yoti account has been successfully linked.');
            }
        }

        return true;
    }

    /**
     * Unlink account from currently logged in
     */
    public function unlink()
    {
        $currentUser = Drupal::currentUser();

        // unlink
        if (!$currentUser->isAnonymous()) {
            $this->deleteYotiUser($currentUser->id());
            return true;
        }

        return false;
    }

    /**
     * @param \Yoti\ActivityDetails $activityDetails
     */
    public static function storeYotiUser(ActivityDetails $activityDetails)
    {
        $session = \Drupal::service('session');
        if (!$session->isStarted()) {
            $session->migrate();
        }
        $_SESSION['yoti-user'] = serialize($activityDetails);
    }

    /**
     * @return ActivityDetails|null
     */
    public static function getYotiUserFromStore()
    {
        $session = \Drupal::service('session');
        if (!$session->isStarted()) {
            $session->migrate();
        }
        return array_key_exists('yoti-user', $_SESSION) ? unserialize($_SESSION['yoti-user']) : null;
    }

    /**
     *
     */
    public static function clearYotiUserStore()
    {
        $session = \Drupal::service('session');
        if (!$session->isStarted()) {
            $session->migrate();
        }
        unset($_SESSION['yoti-user']);
    }

    /**
     * @param $message
     * @param string $type
     */
    public static function setFlash($message, $type = 'status')
    {
        drupal_set_message($message, $type);
    }

    /**
     * @param string $prefix
     * @return string
     */
    private function generateUsername($prefix = 'yoticonnect-')
    {
        // generate username
        $i = 0;
        do {
            $username = $prefix . $i++;
        }
        while ($this->userStorage->loadByProperties(array('name' => $username)));

        return $username;
    }

    /**
     * @param $prefix
     * @param string $domain
     * @return string
     */
    private function generateEmail($prefix = 'yoticonnect-', $domain = 'example.com')
    {
        // generate email
        $i = 0;
        do {
            $email = $prefix . $i++ . "@$domain";
        }
        while ($this->userStorage->loadByProperties(array('mail' => $email)));;

        return $email;
    }

    /**
     * @param int $length
     * @return string
     */
    private function generatePassword($length = 10)
    {
        // generate password
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $password = ''; //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < $length; $i++) {
            $n = rand(0, $alphaLength);
            $password .= $alphabet[$n];
        }

        return $password;
    }

    /**
     * @param ActivityDetails $activityDetails
     * @return int
     * @throws Exception
     */
    private function createUser(ActivityDetails $activityDetails)
    {
        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $user = User::create();

        //Mandatory settings
        $user->setPassword($this->generatePassword());
        $user->enforceIsNew();
        $user->setEmail($this->generateEmail());
        $user->setUsername($this->generateUsername());//This username must be unique and accept only a-Z,0-9, - _ @ .

        //Optional settings
        $user->set("init", 'email');
        $user->set("langcode", $language);
        $user->set("preferred_langcode", $language);
        $user->set("preferred_admin_langcode", $language);
        //$user->set("setting_name", 'setting_value');
        $user->activate();
        if (!$user->save()) {
            throw new Exception("Could not save Yoti user");
        }

        // set new id
        $userId = $user->id();
        $this->createYotiUser($userId, $activityDetails);

        return $userId;
    }

    /**
     * @param $yotiId
     * @param string $field
     * @return int
     */
    private function getDrupalUid($yotiId, $field = "identifier")
    {
        $tableName = self::tableName();
        $col = $this->database->query("SELECT uid FROM `{$tableName}` WHERE `{$field}` = '$yotiId'")->fetchCol();
        return ($col) ? reset($col) : null;
    }

    /**
     * @param $userId
     * @param ActivityDetails $activityDetails
     */
    public function createYotiUser($userId, ActivityDetails $activityDetails)
    {
        //        $user = user_load($userId);
        $meta = $activityDetails->getProfileAttribute();
        unset($meta[ActivityDetails::ATTR_SELFIE]); // don't save se

        $selfieFilename = null;
        if (($content = $activityDetails->getProfileAttribute('selfie'))) {
            $uploadDir = self::uploadDir(false);
            if (!is_dir($uploadDir)) {
                drupal_mkdir($uploadDir, 0777, true);
            }

            $selfieFilename = md5("selfie_$userId" . time()) . ".png";
            file_save_data($content, "$uploadDir/$selfieFilename");
            //      file_put_contents(self::uploadDir() . "/$selfieFilename", $activityDetails->getUserProfile('selfie'));

            $meta['selfie_filename'] = $selfieFilename;
        }

        $this->database->insert(self::tableName())->fields(array(
            'uid' => $userId,
            'identifier' => $activityDetails->getUserId(),
            'phone_number' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_PHONE_NUMBER),
            'date_of_birth' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_DATE_OF_BIRTH),
            'given_names' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_GIVEN_NAMES),
            'family_name' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_FAMILY_NAME),
            'nationality' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_NATIONALITY),
            'gender' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_GENDER),
            'email_address' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_EMAIL_ADDRESS),
            'selfie_filename' => $selfieFilename,
            'data' => serialize($meta),
        ))->execute();
    }

    /**
     * @param int $userId joomla user id
     */
    private function deleteYotiUser($userId)
    {
        $this->database->delete(self::tableName())->condition("uid", $userId)->execute();
    }

    /**
     * @param $userId
     */
    private function loginUser($userId)
    {
        $user = User::load($userId);
        user_login_finalize($user);
    }

    /**
     * not used in this instance
     * @return string
     */
    public static function tableName()
    {
        return 'users_yoti';
    }

    /**
     * @param bool $realPath
     * @return string
     */
    public static function uploadDir($realPath = true)
    {
        return ($realPath) ? drupal_realpath("private://yoti") : 'private://yoti';
    }

    /**
     * @return string
     */
    public static function uploadUrl()
    {
        return file_create_url(self::uploadDir(false));
    }

    /**
     * @return array
     */
    public static function getConfig()
    {
        $settings = Drupal::config('yoti_connect.settings');
        
        $pem = $settings->get('yoti_pem');
        $name = $contents = null;
        if ($pem) {
            $file = \Drupal\file\Entity\File::load($pem[0]);
            $name = $file->getFileUri();
            $contents = file_get_contents(drupal_realpath($name));
        }
        $config = array(
            'yoti_app_id' => $settings->get('yoti_app_id'),
            'yoti_scenario_id' => $settings->get('yoti_scenario_id'),
            'yoti_sdk_id' => $settings->get('yoti_sdk_id'),
            'yoti_only_existing' => $settings->get('yoti_only_existing'),
            'yoti_success_url' => $settings->get('yoti_success_url') ?: '/user',
            'yoti_fail_url' => $settings->get('yoti_fail_url') ?: '/',
            'yoti_connect_email' => $settings->get('yoti_connect_email'),
            'yoti_pem' => array(
                'name' => $name,
                'contents' => $contents,
            ),
        );

        if (self::mockRequests()) {
            $config = array_merge($config, require __DIR__ . '/../sdk/sample-data/config.php');
        }

        return $config;
    }

    /**
     * @return null|string
     */
    public static function getLoginUrl()
    {
        $config = self::getConfig();
        if (empty($config['yoti_app_id'])) {
            return null;
        }

        //https://staging0.www.yoti.com/connect/ad725294-be3a-4688-a26e-f6b2cc60fe70
        //https://staging0.www.yoti.com/connect/990a3996-5762-4e8a-aa64-cb406fdb0e68

        return YotiClient::getLoginUrl($config['yoti_app_id']);
    }
}