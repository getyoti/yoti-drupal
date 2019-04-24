<?php

namespace Drupal\Tests\yoti\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
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
class YotiHelperTest extends UnitTestCase {

  /**
   * Setup for YotiHelper tests.
   */
  public function setUp() {
    $this->createContainer();
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
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user
      ->method('isAnonymous')
      ->willReturn(TRUE);

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
    // Mock database connextion.
    $database = $this->getMockBuilder(Connection::class)
      ->disableOriginalConstructor()
      ->setMethods(['delete', 'select', 'escapeLike'])
      ->getMockForAbstractClass();

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

    // Mock the select query.
    $selectQuery = $this->getMockBuilder(Select::class)
      ->disableOriginalConstructor()
      ->setMethods(['condition', 'execute', 'fetchAll', 'escapeLike'])
      ->getMockForAbstractClass();
    $selectQuery
      ->method('fetchAll')
      ->willReturn([]);
    $selectQuery->method('execute')
      ->willReturn($selectQuery);
    $database
      ->method('select')
      ->willReturn($selectQuery);

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
    $container->set('current_user', $this->createMock(AccountProxyInterface::class));
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('database', $this->createMockDatabase());
    $container->set('email.validator', $this->createMockEmailValidator());
    $container->set('entity_type.repository', $this->createMock(EntityTypeRepositoryInterface::class));
    $container->set('language_manager', $this->createMockLanguageManager());
    \Drupal::setContainer($container);
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
