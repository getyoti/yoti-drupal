<?php

namespace Drupal\Tests\yoti\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\yoti\YotiSdk;
use Drupal\yoti\YotiConfigInterface;
use Yoti\YotiClient;

/**
 * @coversDefaultClass \Drupal\yoti\YotiSdk
 *
 * @group yoti
 */
class YotiSdkTest extends UnitTestCase {

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

}
