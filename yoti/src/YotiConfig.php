<?php

namespace Drupal\yoti;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class YotiConfig.
 *
 * @package Drupal\yoti
 */
class YotiConfig implements YotiConfigInterface {

  /**
   * Config factory.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * File Storage.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
  private $fileStorage;

  /**
   * File System.
   *
   * @var Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * Yoti plugin settings.
   *
   * @var array
   */
  private $settings;

  /**
   * YotiConfig constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param Drupal\Core\File\FileSystemInterface $file_system
   *   File system.
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->setConfig();
  }

  /**
   * Set config array from config storage.
   */
  private function setConfig() {
    $settings = $this->configFactory->get('yoti.settings');

    $pem = $settings->get('yoti_pem');
    $name = $contents = NULL;

    if (isset($pem[0]) && ($file = $this->fileStorage->load($pem[0]))) {
      $name = $file->getFileUri();
      $contents = file_get_contents($this->fileSystem->realpath($name));
    }
    $this->settings = [
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
  public function getSettings() {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppId() {
    return $this->settings['yoti_app_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getScenarioId() {
    return $this->settings['yoti_scenario_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSdkId() {
    return $this->settings['yoti_sdk_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOnlyExisting() {
    return $this->settings['yoti_only_existing'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSuccessUrl() {
    return $this->settings['yoti_success_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFailUrl() {
    return $this->settings['yoti_fail_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUserEmail() {
    return $this->settings['yoti_user_email'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAgeVerification() {
    return $this->settings['yoti_age_verification'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCompanyName() {
    return $this->settings['yoti_company_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPemContents() {
    return $this->settings['yoti_pem']['contents'];
  }

}
