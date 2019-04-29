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
    return new YotiClient(
      $this->config->getSdkId(),
      $this->config->getPemContents(),
      YotiClient::DEFAULT_CONNECT_API,
      self::SDK_IDENTIFIER
    );
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
