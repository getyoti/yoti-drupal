<?php

namespace Drupal\Tests\yoti\Unit;

use Drupal\yoti\YotiSdk;
use Drupal\yoti\YotiConfigInterface;
use Yoti\YotiClient;

/**
 * @coversDefaultClass \Drupal\yoti\YotiSdk
 *
 * @group yoti
 */
class YotiSdkTest extends YotiUnitTestBase {

  /**
   * Yoti config.
   *
   * @var \Drupal\yoti\YotiConfigInterface
   */
  private $config;

  /**
   * Setup Yoti SDK tests.
   */
  public function setup() {
    parent::setup();

    $config = $this->createMock(YotiConfigInterface::class);
    $config
      ->method('getSdkId')
      ->willReturn('test_sdk_id');
    $config
      ->method('getAppId')
      ->willReturn('test_app_id');

    openssl_pkey_export(openssl_pkey_new(), $pem_contents);
    $config
      ->method('getPemContents')
      ->willReturn($pem_contents);

    $this->config = $config;
  }

  /**
   * @covers ::getClient
   */
  public function testGetClient() {
    $sdk = new YotiSdk($this->config);
    $this->assertInstanceOf(YotiClient::class, $sdk->getClient());
  }

  /**
   * @covers ::getLoginUrl
   */
  public function testGetLoginUrl() {
    $sdk = new YotiSdk($this->config);
    $this->assertEquals('https://www.yoti.com/connect/test_app_id', $sdk->getLoginUrl());
  }

}
