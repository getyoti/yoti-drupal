<?php

namespace Drupal\Tests\yoti\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Drupal\yoti\YotiConfigInterface;
use Drupal\yoti\YotiHelper;
use Drupal\yoti\YotiSdkInterface;
use Egulias\EmailValidator\EmailValidatorInterface;
use Psr\Log\LoggerInterface;
use Yoti\YotiClient;
use Yoti\Entity\Profile;
use Yoti\Exception\ActivityDetailsException;
use Yoti\ActivityDetails;

require_once __DIR__ . '/../../../sdk/boot.php';

/**
 * @coversDefaultClass \Drupal\yoti\YotiHelper
 *
 * @group yoti
 */
class YotiHelperTest extends YotiUnitTestBase {

  /**
   * Selfie file path.
   *
   * @var string
   */
  private $selfieFilePath;

  /**
   * Setup for YotiHelper tests.
   */
  public function setUp() {
    parent::setup();

    // Create test selfie file.
    $this->selfieFilePath = $this->tmpDir . DIRECTORY_SEPARATOR . 'test_selfie.jpg';
    file_put_contents($this->selfieFilePath, 'test_selfie_contents');

    $this->createContainer();
  }

  /**
   * Clean up test data.
   */
  public function teardown() {
    // Remove test file.
    if (is_file($this->selfieFilePath)) {
      unlink($this->selfieFilePath);
    }

    parent::teardown();
  }

  /**
   * @covers ::link
   * @backupGlobals enabled
   */
  public function testLinkInvalidToken() {
    // Cache should not be invalidated if invalid token is provided.
    $cacheTagsInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $cacheTagsInvalidator
      ->expects($this->exactly(0))
      ->method('invalidateTags');

    // Throw exception to simulate a share failure.
    $client = $this->createMock(YotiClient::class);
    $client
      ->method('getActivityDetails')
      ->will($this->throwException(new ActivityDetailsException()));

    $sdk = $this->createMock(YotiSdkInterface::class);
    $sdk
      ->method('getClient')
      ->willReturn($client);

    $helper = new YotiHelper(
      $this->createMockEntityTypeManager(),
      $cacheTagsInvalidator,
      $this->createMock(LoggerChannelFactoryInterface::class),
      $sdk,
      $this->createMock(YotiConfigInterface::class)
    );

    // Attempt link with no token.
    $result = $helper->link();
    $this->assertFalse($result);

    // Attempt link with an invalid token.
    $_GET['token'] = 'test_token';
    $result = $helper->link();
    $this->assertFalse($result);
  }

  /**
   * @covers ::link
   * @backupGlobals enabled
   */
  public function testUserSaveFailure() {
    // Set current user to anonymous so that a new user is created.
    $current_user = $this->createMockCurrentUser(0);

    // Return FALSE when new user is saved.
    $user = $this->createMock(UserInterface::class);
    $user
      ->method('save')
      ->willReturn(FALSE);
    $entity_storage = $this->createMockEntityStorage($user);

    $this->setContainerService(
      'entity_type.manager',
      $this->createMockEntityTypeManagerStorage($entity_storage)
    );

    // Expect error to be logged.
    $logger = $this->createMock(LoggerInterface::class);
    $logger
      ->expects($this->once())
      ->method('error')
      ->with($this->equalTo('Could not save Yoti user'));

    $helper = new YotiHelper(
      $this->createMockEntityTypeManager(),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMockLoggerFactory($logger),
      $this->createMockSdk(),
      $this->createMock(YotiConfigInterface::class)
    );

    $_GET['token'] = 'test_token';
    $result = $helper->link($current_user);
    $this->assertFalse($result);
  }

  /**
   * @covers ::unlink
   */
  public function testUnlinkCacheInvalidation() {
    // Cache should be invalidated when user is unlinked.
    $cacheTagsInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $cacheTagsInvalidator
      ->expects($this->once())
      ->method('invalidateTags')
      ->with($this->equalTo(['tag:1', 'tag:2']));

    $helper = new YotiHelper(
      $this->createMockEntityTypeManager(),
      $cacheTagsInvalidator,
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(YotiSdkInterface::class),
      $this->createMock(YotiConfigInterface::class)
    );

    $helper->unlink();
  }

  /**
   * @covers ::unlink
   */
  public function testUnlinkRemoveSelfie() {
    $helper = new YotiHelper(
      $this->createMockEntityTypeManager(),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(YotiSdkInterface::class),
      $this->createMock(YotiConfigInterface::class)
    );

    $this->assertFileExists($this->selfieFilePath);
    $helper->unlink();
    $this->assertFileNotExists($this->selfieFilePath);
  }

  /**
   * @covers ::getLoginUrl
   */
  public function testGetLoginUrl() {
    $this->assertEquals('https://www.yoti.com/connect/test_app_id', YotiHelper::getLoginUrl());
  }

  /**
   * Creates mock entity storage.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Mocked entity.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   Mocked entity type manager
   */
  private function createMockEntityStorage(EntityInterface $entity) {
    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage
      ->method('create')
      ->willReturn($entity);
    return $entity_storage;
  }

  /**
   * Creates mock entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Mocked entity type manager
   */
  private function createMockEntityTypeManager() {
    // Mock user storage.
    $user = $this->createMock(EntityInterface::class);
    $user
      ->method('id')
      ->willReturn('2');
    $user
      ->method('getCacheTagsToInvalidate')
      ->willReturn(['tag:1', 'tag:2']);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage
      ->method('load')
      ->willReturn($user);

    // Mock entity type manager.
    $entityManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityManager
      ->method('getStorage')
      ->will($this->returnValueMap([
        ['user', $userStorage],
      ]));

    return $entityManager;
  }

  /**
   * Create mock logger factory.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Mocked logger.
   *
   * @return \Drupal\Core\Logger\LoggerChannelFactoryInterface
   *   Logger factory.
   */
  private function createMockLoggerFactory(LoggerInterface $logger) {
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory
      ->method('get')
      ->willReturn($logger);

    return $loggerFactory;
  }

  /**
   * Creates mock database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   Mocked database connection.
   */
  private function createMockDatabase() {
    // Mock database connection.
    $database = $this->createMock(Connection::class);

    // Mock delete query.
    $deleteQuery = $this->getMockBuilder(Delete::class)
      ->disableOriginalConstructor()
      ->setMethods(['condition', 'execute'])
      ->getMockForAbstractClass();
    $deleteQuery->method('condition')
      ->willReturn($deleteQuery);
    $deleteQuery->method('execute')
      ->willReturn([]);
    $database
      ->method('delete')
      ->willReturn($deleteQuery);

    // Mock the user data select query.
    $userDataSelectQuery = $this->getMockBuilder(Select::class)
      ->disableOriginalConstructor()
      ->setMethods(['condition', 'execute', 'fetchAll', 'escapeLike'])
      ->getMockForAbstractClass();
    $userDataSelectQuery
      ->method('fetchAll')
      ->willReturn([]);
    $userDataSelectQuery->method('execute')
      ->willReturn($userDataSelectQuery);

    // Mock the Yoti user data select query.
    $yotiUserSelectQuery = $this->getMockBuilder(Select::class)
      ->disableOriginalConstructor()
      ->setMethods(['condition', 'execute', 'range', 'fetchAssoc'])
      ->getMockForAbstractClass();

    foreach (['condition', 'execute', 'range'] as $method) {
      $yotiUserSelectQuery
        ->method($method)
        ->willReturn($yotiUserSelectQuery);
    }

    $yotiUserSelectQuery->method('fetchAssoc')
      ->willReturn([
        'data' => serialize([
          'selfie_filename' => basename($this->selfieFilePath),
        ]),
      ]);

    // Return mocked query depending on table.
    $database
      ->method('select')
      ->will(
        $this->returnValueMap([
          ['users_field_data', 'uf', [], $userDataSelectQuery],
          [YotiHelper::YOTI_USER_TABLE_NAME, 'u', [], $yotiUserSelectQuery],
        ])
      );

    return $database;
  }

  /**
   * Creates mock config factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   Mocked config factory.
   */
  private function createMockConfigFactory() {
    return $this->getConfigFactoryStub([
      'yoti.settings' => [
        'yoti_app_id' => 'app_id',
        'yoti_scenario_id' => 'scenario_id',
        'yoti_sdk_id' => 'sdk_id',
        'yoti_only_existing' => 1,
        'yoti_success_url' => '/user',
        'yoti_fail_url' => '/',
        'yoti_user_email' => 'user@example.com',
        'yoti_age_verification' => 0,
        'yoti_company_name' => 'company_name',
        'yoti_pem' => [
          'name' => 'pem_name',
          'contents' => 'pem_contents',
        ],
      ],
    ]);
  }

  /**
   * Creates mock email validator.
   *
   * @return \Egulias\EmailValidator\EmailValidatorInterface
   *   Email validator.
   */
  private function createMockEmailValidator() {
    $email_validator = $this->createMock(EmailValidatorInterface::class);
    $email_validator
      ->method('isValid')
      ->willReturn(TRUE);
    return $email_validator;
  }

  /**
   * Creates mock entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   Mocked entity storage.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Entity type manager.
   */
  private function createMockEntityTypeManagerStorage(EntityStorageInterface $entity_storage) {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager
      ->method('getStorage')
      ->willReturn($entity_storage);

    return $entity_type_manager;
  }

  /**
   * Creates mock language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   Language manager.
   */
  private function createMockLanguageManager() {
    $language = $this->createMock(LanguageInterface::class);
    $language
      ->method('getId')
      ->willReturn('en');

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager
      ->method('getCurrentLanguage')
      ->willReturn($language);

    return $language_manager;
  }

  /**
   * Setup container to handle dependencies that haven't been injected.
   */
  private function createContainer() {
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->createMockConfigFactory());
    $container->set('current_user', $this->createMockCurrentUser(2));
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('database', $this->createMockDatabase());
    $container->set('email.validator', $this->createMockEmailValidator());
    $container->set('entity_type.repository', $this->createMock(EntityTypeRepositoryInterface::class));
    $container->set('language_manager', $this->createMockLanguageManager());
    $container->set('file_system', $this->createMockFileSystem());
    $container->set('yoti.sdk', $this->createMockSdk());
    \Drupal::setContainer($container);
  }

  /**
   * Create mock current user.
   *
   * @param int $user_id
   *   The mock user ID.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   Current user.
   */
  private function createMockCurrentUser($user_id) {
    $current_user = $this->createMock(AccountProxyInterface::class);

    if ($user_id == 0) {
      $current_user
        ->method('isAnonymous')
        ->willReturn(TRUE);
    }
    else {
      $current_user
        ->method('isAnonymous')
        ->willReturn(FALSE);
      $current_user
        ->method('id')
        ->willReturn($user_id);
    }

    return $current_user;
  }

  /**
   * Mock the file system.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   File system.
   */
  private function createMockFileSystem() {
    $file_system = $this->createMock(FileSystemInterface::class);

    $file_system
      ->method('realpath')
      ->will(
        $this->returnValueMap([
          [YotiHelper::YOTI_PEM_FILE_UPLOAD_LOCATION, $this->tmpDir],
        ])
      );

    return $file_system;
  }

  /**
   * Creates mock SDK.
   *
   * @return \Drupal\yoti\YotiSdkInterface
   *   Yoti SDK service.
   */
  private function createMockSdk() {
    $profile = $this->createMock(Profile::class);
    $profile
      ->method('getAttributes')
      ->willReturn([]);
    $profile
      ->method('getAgeVerifications')
      ->willReturn([]);

    $activityDetails = $this->createMock(ActivityDetails::class);
    $activityDetails
      ->method('getProfile')
      ->willReturn($profile);

    $client = $this->createMock(YotiClient::class);
    $client
      ->method('getActivityDetails')
      ->willReturn($activityDetails);

    $sdk = $this->createMock(YotiSdkInterface::class);
    $sdk
      ->method('getClient')
      ->willReturn($client);
    $sdk
      ->method('getLoginUrl')
      ->willReturn('https://www.yoti.com/connect/test_app_id');

    return $sdk;
  }

  /**
   * Sets a service.
   *
   * @param string $id
   *   The service identifier.
   * @param object $service
   *   The service instance.
   */
  private function setContainerService($id, $service) {
    $container = \Drupal::getContainer();
    $container->set($id, $service);
    \Drupal::setContainer($container);
  }

}

/**
 * Mock global functions.
 */
namespace Drupal\yoti;

if (!function_exists('user_load_by_mail')) {

  /**
   * Mock user_load_by_mail().
   *
   * @return bool
   *   True if a user with mail exists.
   */
  function user_load_by_mail() {
    return FALSE;
  }

}
