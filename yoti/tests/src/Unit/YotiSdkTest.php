<?php

namespace Drupal\Tests\yoti\Unit;

use Drupal\yoti\YotiSdk;
use Drupal\yoti\YotiConfigInterface;
use Yoti\Entity\AmlProfile;
use Yoti\Http\RequestHandlerInterface;
use Yoti\Http\Response;
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
      ->method('getClientSdkId')
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

  /**
   * @covers ::getClient
   */
  public function testSdkVersionHeaders() {
    $sdk = new YotiSdk($this->config);

    $response = $this->createMock(Response::class);
    $response->method('getStatusCode')->willReturn(200);
    $response->method('getBody')->willReturn(json_encode([
      'on_pep_list' => FALSE,
      'on_watch_list' => FALSE,
      'on_fraud_list' => FALSE,
    ]));

    $requestHandler = $this->createMock(RequestHandlerInterface::class);
    $requestHandler
      ->expects($this->once())
      ->method('execute')
      ->with($this->callback(function ($request) {
        $this->assertEquals(
          YotiSdk::SDK_IDENTIFIER,
          $request->getHeaders()['X-Yoti-SDK']
        );
        $this->assertEquals(
          YotiSdk::SDK_IDENTIFIER . '-' . YotiSdk::SDK_VERSION,
          $request->getHeaders()['X-Yoti-SDK-Version']
        );
        return TRUE;
      }))
      ->willReturn($response);

    $client = $sdk->getClient();
    $client->setRequestHandler($requestHandler);
    $client->performAmlCheck($this->createMock(AmlProfile::class));
  }

}
