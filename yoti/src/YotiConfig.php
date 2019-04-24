<?php

namespace Drupal\yoti;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class YotiConfig.
 *
 * @package Drupal\yoti
 */
class YotiConfig implements YotiConfigInterface {

  /**
   * Yoti plugin config data.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Yoti plugin config data.
   *
   * @var array
   */
  private $config;

  /**
   * YotiConfig constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    $this->setConfig();
  }

  /**
   * Set config array from config storage.
   */
  private function setConfig() {
    $settings = $this->configFactory->get('yoti.settings');

    $pem = $settings->get('yoti_pem');
    $name = $contents = NULL;
    if (isset($pem[0]) && ($file = File::load($pem[0]))) {
      $name = $file->getFileUri();
      $contents = file_get_contents(\Drupal::service('file_system')->realpath($name));
    }
    $this->config = [
      'yoti_app_id' => $settings->get('yoti_app_id'),
      'yoti_scenario_id' => $settings->get('yoti_scenario_id'),
      'yoti_sdk_id' => $settings->get('yoti_sdk_id'),
      'yoti_only_existing' => $settings->get('yoti_only_existing'),
      'yoti_success_url' => $settings->get('yoti_success_url') ?: '/user',
      'yoti_fail_url' => $settings->get('yoti_fail_url') ?: '/',
      'yoti_user_email' => $settings->get('yoti_user_email'),
      'yoti_age_verification' => $settings->get('yoti_age_verification'),
      'yoti_company_name' => $settings->get('yoti_company_name'),
      'yoti_pem' => compact('name', 'contents'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppId() {
    return $this->config['yoti_app_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getScenarioId() {
    return $this->config['yoti_scenario_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSdkId() {
    return $this->config['yoti_sdk_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOnlyExisting() {
    return $this->config['yoti_only_existing'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSuccessUrl() {
    return $this->config['yoti_success_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFailUrl() {
    return $this->config['yoti_fail_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUserEmail() {
    return $this->config['yoti_user_email'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAgeVerification() {
    return $this->config['yoti_age_verification'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCompanyName() {
    return $this->config['yoti_company_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPemContents() {
    return $this->config['yoti_pem']['contents'];
  }

}
