<?php

namespace Drupal\Tests\yoti\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\yoti\YotiHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Delete;

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
    // Setup container to handle situations where dependencies
    // haven't been injected.
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->createMockConfigFactory());
    $container->set('current_user', $this->getMock(AccountProxyInterface::class));
    $container->set('messenger', $this->getMock(MessengerInterface::class));
    $container->set('database', $this->createMockDatabase());
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::link
   * @backupGlobals enabled
   */
  public function testLinkInvalidToken() {
    // Cache should not be invalidated if invalid token is provided.
    $cacheTagsInvalidator = $this->getMock(CacheTagsInvalidatorInterface::class);
    $cacheTagsInvalidator
      ->expects($this->exactly(0))
      ->method('invalidateTags');

    $helper = new YotiHelper($this->createMockEntityTypeManager(), $cacheTagsInvalidator);

    // Attempt link with no token.
    $result = $helper->link();
    $this->assertFalse($result);

    // Attempt link with an invalid token.
    $_GET['token'] = 'test_token';
    $result = $helper->link();
    $this->assertFalse($result);
  }

  /**
   * @covers ::unlink
   */
  public function testUnlinkCacheInvalidation() {
    // Cache should be invalidated when user is unlinked.
    $cacheTagsInvalidator = $this->getMock(CacheTagsInvalidatorInterface::class);
    $cacheTagsInvalidator
      ->expects($this->once())
      ->method('invalidateTags')
      ->with($this->equalTo(['tag:1', 'tag:2']));

    $helper = new YotiHelper($this->createMockEntityTypeManager(), $cacheTagsInvalidator);
    $helper->unlink();
  }

  /**
   * Creates mock entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Mocked entity type manager
   */
  private function createMockEntityTypeManager() {
    // Mock user storage.
    $user = $this->getMock(EntityInterface::class);
    $user
      ->method('id')
      ->willReturn('2');
    $user
      ->method('getCacheTagsToInvalidate')
      ->willReturn(['tag:1', 'tag:2']);

    $userStorage = $this->getMock(EntityStorageInterface::class);
    $userStorage
      ->method('load')
      ->willReturn($user);

    // Mock entity type manager.
    $entityManager = $this->getMock(EntityTypeManagerInterface::class);
    $entityManager
      ->method('getStorage')
      ->will($this->returnValueMap([
        ['user', $userStorage],
      ]));

    return $entityManager;
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
      ->setMethods(['delete'])
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

}
