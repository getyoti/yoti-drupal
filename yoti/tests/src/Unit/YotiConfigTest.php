<?php

namespace Drupal\Tests\yoti\Unit;

use Drupal\yoti\YotiConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\file\FileInterface;

/**
 * @coversDefaultClass \Drupal\yoti\YotiConfig
 *
 * @group yoti
 */
class YotiConfigTest extends YotiUnitTestBase {

  /**
   * PEM file path.
   *
   * @var string
   */
  private $pemFilePath;

  /**
   * Yoti config.
   *
   * @var \Drupal\yoti\YotiConfig
   */
  private $config;

  /**
   * Yoti test settings.
   *
   * @var array
   */
  private $settings = [
    'yoti_app_id' => 'app_id',
    'yoti_scenario_id' => 'scenario_id',
    'yoti_sdk_id' => 'sdk_id',
    'yoti_only_existing' => 1,
    'yoti_success_url' => '/user',
    'yoti_fail_url' => '/',
    'yoti_user_email' => 'user@example.com',
    'yoti_age_verification' => 0,
    'yoti_company_name' => 'company_name',
    'yoti_pem' => [1],
  ];

  /**
   * Create test YotiConfig object.
   */
  public function setup() {
    parent::setup();

    // Create test pem file.
    $this->pemFilePath = $this->tmpDir . DIRECTORY_SEPARATOR . 'yoti_config_test.pem';
    file_put_contents($this->pemFilePath, 'test_pem_content');

    // Mock the config factory with Yoti settings.
    $configFactory = $this->getConfigFactoryStub([
      'yoti.settings' => $this->settings,
    ]);

    $this->config = new YotiConfig(
      $configFactory,
      $this->createMockFileSystem(),
      $this->createMockEntityTypeManager()
    );
  }

  /**
   * Clean up test data.
   */
  public function teardown() {
    // Remove test file.
    if (is_file($this->pemFilePath)) {
      unlink($this->pemFilePath);
    }

    parent::teardown();
  }

  /**
   * @covers ::getSettings
   */
  public function testGetSettings() {
    $expected_settings = $this->settings;
    $expected_settings['yoti_pem'] = [
      'name' => '/tmp/drupal-yoti/yoti_config_test.pem',
      'contents' => 'test_pem_content',
    ];
    $this->assertEquals($expected_settings, $this->config->getSettings());
  }

  /**
   * @covers ::getAppId
   */
  public function testGetAppId() {
    $this->assertEquals($this->config->getAppId(), $this->settings['yoti_app_id']);
  }

  /**
   * @covers ::getScenarioId
   */
  public function testGetScenarioId() {
    $this->assertEquals($this->config->getScenarioId(), $this->settings['yoti_scenario_id']);
  }

  /**
   * @covers ::getSdkId
   */
  public function testGetSdkId() {
    $this->assertEquals($this->config->getSdkId(), $this->settings['yoti_sdk_id']);
  }

  /**
   * @covers ::getOnlyExisting
   */
  public function testGetOnlyExisting() {
    $this->assertEquals($this->config->getOnlyExisting(), $this->settings['yoti_only_existing']);
  }

  /**
   * @covers ::getSuccessUrl
   */
  public function testGetSuccessUrl() {
    $this->assertEquals($this->config->getSuccessUrl(), $this->settings['yoti_success_url']);
  }

  /**
   * @covers ::getFailUrl
   */
  public function testGetFailUrl() {
    $this->assertEquals($this->config->getFailUrl(), $this->settings['yoti_fail_url']);
  }

  /**
   * @covers ::getUserEmail
   */
  public function testGetUserEmail() {
    $this->assertEquals($this->config->getUserEmail(), $this->settings['yoti_user_email']);
  }

  /**
   * @covers ::getAgeVerification
   */
  public function testGetAgeVerification() {
    $this->assertEquals($this->config->getAgeVerification(), $this->settings['yoti_age_verification']);
  }

  /**
   * @covers ::getCompanyName
   */
  public function testGetCompanyName() {
    $this->assertEquals($this->config->getCompanyName(), $this->settings['yoti_company_name']);
  }

  /**
   * @covers ::getPemContents
   */
  public function testGetPemContents() {
    $this->assertEquals($this->config->getPemContents(), 'test_pem_content');
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
      ->willReturn($this->pemFilePath);

    return $file_system;
  }

  /**
   * Mock the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Entity type manager.
   */
  private function createMockEntityTypeManager() {
    $file = $this->createMock(FileInterface::class);
    $file
      ->method('getFileUri')
      ->willReturn($this->pemFilePath);

    $file_storage = $this->createMock(EntityStorageInterface::class);
    $file_storage
      ->method('load')
      ->willReturn($file);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager
      ->method('getStorage')
      ->willReturn($file_storage);

    return $entity_type_manager;
  }

}
