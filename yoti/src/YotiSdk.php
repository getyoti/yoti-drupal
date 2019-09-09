<?php

namespace Drupal\yoti;

use Yoti\YotiClient;

require_once __DIR__ . '/../sdk/boot.php';

/**
 * Class YotiSdk.
 *
 * @package Drupal\yoti
 */
class YotiSdk implements YotiSdkInterface {

  /**
   * Yoti Drupal SDK identifier.
   */
  const SDK_IDENTIFIER = 'Drupal';

  /**
   * Yoti Drupal SDK version.
   */
  const SDK_VERSION = '8.x-2.3';

  /**
   * Yoti plugin config data.
   *
   * @var \Drupal\yoti\YotiConfigInterface
   */
  private $config;

  /**
   * YotiSDK constructor.
   *
   * @param \Drupal\yoti\YotiConfigInterface $config
   *   Yoti plugin config data.
   */
  public function __construct(YotiConfigInterface $config) {
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient() {
    $client = new YotiClient(
      $this->config->getClientSdkId(),
      $this->config->getPemContents(),
      YotiClient::DEFAULT_CONNECT_API
    );
    $client->setSdkIdentifier(self::SDK_IDENTIFIER);
    $client->setSdkVersion(self::SDK_VERSION);
    return $client;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginUrl() {
    if ($appId = $this->config->getAppId()) {
      return YotiClient::getLoginUrl($appId);
    }
  }

}
