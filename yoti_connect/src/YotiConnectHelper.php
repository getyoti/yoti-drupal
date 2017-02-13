<?php
namespace Drupal\yoti_connect;

use Drupal;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Yoti\ActivityDetails;
use Yoti\YotiClient;

/**
 * Class YotiConnectHelper
 *
 * @package Drupal\yoti_connect
 * @author Simon Tong <simon.tong@yoti.com>
 */
class YotiConnectHelper
{
    /**
     * @var Connection
     */
    protected $database;

    /**
     * @var UserDataInterface
     */
    protected $userData;

    /**
     * @var Drupal\Core\Entity\EntityStorageInterface
     */
    protected $userStorage;

    /**
     * YotiConnectHelper constructor.
     * @param Connection $database
     * @param Drupal\Core\Entity\EntityManager $entity_manager
     * @param UserDataInterface $user_data
     */
    public function __construct(Connection $database, Drupal\Core\Entity\EntityManager $entity_manager, UserDataInterface $user_data)
    {
        $this->database = $database;
        $this->userStorage = $entity_manager->getStorage('user');
        $this->userData = $user_data;
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
     * @return bool
     */
    public function link()
    {
        $currentUser = Drupal::currentUser();
        $config = self::getConfig();
        $token = Drupal::request()->get('token');

        // if no token then ignore
        if (!$token)
        {
            $this->setFlash('Could not get Yoti token.', 'error');

            return false;
        }

        // init yoti client and attempt to request user details
        try
        {
            $yotiClient = new YotiClient($config['yoti_sdk_id'], $config['yoti_pem']['contents']);
            $yotiClient->setMockRequests(self::mockRequests());
            $activityDetails = $yotiClient->getActivityDetails($token);
        }
        catch (Exception $e)
        {
            $this->setFlash('Yoti could not successfully connect to your account.', 'error');

            return false;
        }

        // if unsuccessful then bail
        if ($yotiClient->getOutcome() != YotiClient::OUTCOME_SUCCESS)
        {
            $this->setFlash('Yoti could not successfully connect to your account.', 'error');

            return false;
        }

        // check if yoti user exists
        $userId = $this->getUserIdByYotiId($activityDetails->getUserId());

        // if yoti user exists in db but isn't an actual account then remove it from yoti table
        if ($userId && $currentUser->id() != $userId && !User::load($userId)->id())
        {
            // remove users account
            $this->deleteYotiUser($userId);
        }

        // if user isn't logged in
        if ($currentUser->isAnonymous())
        {
            // register new user
            if (!$userId)
            {
                $errMsg = $userId = null;
                try
                {
                    $userId = $this->createUser($activityDetails);
                }
                catch (Exception $e)
                {
                    $errMsg = $e->getMessage();
                }

                // no user id? no account
                if (!$userId)
                {
                    // if couldn't create user then bail
                    $this->setFlash("Could not create user account. $errMsg", 'error');

                    return false;
                }
            }

            // log user in
            $this->loginUser($userId);
        }
        else
        {
            // if current logged in user doesn't match yoti user registered then bail
            if ($userId && $currentUser->id() != $userId)
            {
                $this->setFlash('This Yoti account is already linked to another account.', 'error');
            }
            // if joomla user not found in yoti table then create new yoti user
            elseif (!$userId)
            {
                $this->createYotiUser($currentUser->id(), $activityDetails);
                $this->setFlash('Your Yoti account has been successfully linked.');
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
        if (!$currentUser->isAnonymous())
        {
            $this->deleteYotiUser($currentUser->id());
            return true;
        }

        return false;
    }

    /**
     * @param $message
     * @param string $type
     */
    private function setFlash($message, $type = 'status')
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
        do
        {
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
        do
        {
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
        for ($i = 0; $i < $length; $i++)
        {
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
        if (!$user->save())
        {
            throw new Exception("Could not save Yoti user");
        }

        // set new id
        $userId = $user->id();
        $this->createYotiUser($userId, $activityDetails);

        return $userId;
    }

    /**
     * @param $yotiId
     * @return int
     */
    private function getUserIdByYotiId($yotiId)
    {
        $qry = $this->database->select('users', 'u');
        $qry->innerJoin('users_data', 'ud', 'ud.uid = u.uid');

        return $qry->fields('u', array('uid'))
            ->condition('module', 'yoti_connect')
            ->condition('ud.name', 'identifier')
            ->condition('ud.value', $yotiId)
            ->execute()->fetchField();
    }

    /**
     * @param $userId
     * @param ActivityDetails $activityDetails
     */
    private function createYotiUser($userId, ActivityDetails $activityDetails)
    {
        $selfieFilename = null;
        if (($content = $activityDetails->getProfileAttribute('selfie')))
        {
            $uploadDir = self::uploadDir(false);
            if (!is_dir($uploadDir))
            {
                drupal_mkdir($uploadDir, 0777, true);
            }

            $selfieFilename = md5("selfie_$userId" . time()) . ".png";
            file_save_data($content, "$uploadDir/$selfieFilename");
            //      file_put_contents(self::uploadDir() . "/$selfieFilename", $activityDetails->getUserProfile('selfie'));
        }

        $user = array(
            //            'joomla_userid' => $userId,
            'identifier' => $activityDetails->getUserId(),
            'phone_number' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_PHONE_NUMBER),
            'date_of_birth' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_DATE_OF_BIRTH),
            'given_names' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_GIVEN_NAMES),
            'family_name' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_FAMILY_NAME),
            'nationality' => $activityDetails->getProfileAttribute(ActivityDetails::ATTR_NATIONALITY),
            'selfie_filename' => $selfieFilename,
        );

        foreach ($user as $key => $value)
        {
            $this->userData->set('yoti_connect', $userId, $key, $value);
        }
    }

    /**
     * @param int $userId joomla user id
     */
    private function deleteYotiUser($userId)
    {
        $this->userData->delete('yoti_connect', $userId);
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
        // not used
        return null;
    }

    /**
     * @param bool $realPath
     * @return string
     */
    public static function uploadDir($realPath = true)
    {
        return ($realPath) ? drupal_realpath("public://yoti") : 'public://yoti';
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
        if (self::mockRequests())
        {
            $config = require_once __DIR__ . '/../sdk/sample-data/config.php';
            return $config;
        }

        $config = Drupal::config('yoti_connect.settings')->get();
        $name = $contents = null;
        if (!empty($config['yoti_pem']))
        {
            $file = File::load($config['yoti_pem'][0]);
            $name = $file->getFileUri();
            $contents = file_get_contents(drupal_realpath($name));
        }
        return array(
            'yoti_pem' => array(
                'name' => $name,
                'contents' => $contents,
            ),
        ) + $config;
    }

    /**
     * @return null|string
     */
    public static function getLoginUrl()
    {
        $config = self::getConfig();
        if (empty($config['yoti_app_id']))
        {
            return null;
        }

        //https://staging0.www.yoti.com/connect/ad725294-be3a-4688-a26e-f6b2cc60fe70
        //https://staging0.www.yoti.com/connect/990a3996-5762-4e8a-aa64-cb406fdb0e68

        return YotiClient::getLoginUrl($config['yoti_app_id']);
    }
}