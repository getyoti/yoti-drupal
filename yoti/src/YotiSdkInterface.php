<?php

namespace Drupal\yoti;

/**
 * Interface YotiSdk.
 *
 * @package Drupal\yoti
 */
interface YotiSdkInterface {

  /**
   * Creates a new YotiClient.
   *
   * @return \Yoti\YotiClient
   *   Yoti Client.
   */
  public function getClient();

}