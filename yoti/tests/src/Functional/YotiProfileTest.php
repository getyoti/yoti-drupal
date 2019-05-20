<?php

namespace Drupal\Tests\yoti\Functional;

use Drupal\yoti\YotiHelper;
use Yoti\Entity\Profile;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the user profile.
 *
 * @group yoti
 */
class YotiProfileTest extends YotiBrowserTestBase {

  /**
   * Test profile for linked users.
   */
  public function testProfileLinked() {
    $this->drupalLogin($this->linkedUser);
    $this->drupalGet('user');
    $this->assertProfileFields(yoti_map_params());
  }

  /**
   * Test profile with customised display.
   */
  public function testProfileCustomiseDisplay() {
    $disable_fields = [
      Profile::ATTR_FULL_NAME,
      Profile::ATTR_GIVEN_NAMES,
    ];

    // Import config and hide user fields.
    $this->importUserDisplayConfig();
    $this->hideUserDisplayFields($disable_fields);

    $this->drupalLogin($this->linkedUser);
    $this->drupalGet('user');

    // Create array of display and disabled fields.
    $profile_data = yoti_map_params();
    foreach ($disable_fields as $disable_field) {
      unset($profile_data[$disable_field]);
      $disabled_data[$disable_field] = yoti_map_params()[$disable_field];
    }

    $this->assertProfileFields($profile_data);
    $this->assertNotProfileFields($disabled_data);
  }

  /**
   * Test profile for unlinked users.
   */
  public function testProfileUnlinked() {
    $this->drupalLogin($this->unlinkedUser);
    $this->drupalGet('user');
    $this->assertNotProfileFields(yoti_map_params());
  }

  /**
   * Assert that the provided profile fields are present on profile.
   *
   * @param array $profile_data
   *   Array of profile data to check.
   */
  private function assertProfileFields(array $profile_data) {
    $assert = $this->assertSession();

    // Check profile data without selfie.
    unset($profile_data[YotiHelper::ATTR_SELFIE_FILE_NAME]);
    unset($profile_data[Profile::ATTR_SELFIE]);

    foreach ($profile_data as $label) {
      $assert->responseContains($label . ' value');
    }

    // Check unlink button is present.
    $assert->elementTextContains(
      'css',
      "#yoti-unlink-button[href='/yoti/unlink']",
      'Unlink Yoti account'
    );

    // Check selfie image is present.
    $assert->elementExists('css', "img[src*='/yoti/bin-file/selfie'][width='100']");
    $this->drupalGet('yoti/bin-file/selfie');
    $assert->responseContains('test_selfie_contents');
  }

  /**
   * Assert that the provided profile fields are not present on profile.
   *
   * @param array $profile_data
   *   Array of profile data to check.
   */
  private function assertNotProfileFields(array $profile_data) {
    foreach ($profile_data as $label) {
      $this->assertSession()->responseNotContains($label);
    }
  }

  /**
   * Get the user display configuration.
   *
   * @return \Drupal\Core\Config\Config
   *   Editable configuration object.
   */
  private function getUserDisplayConfig() {
    return \Drupal::service('config.factory')
      ->getEditable('core.entity_view_display.user.user.default');
  }

  /**
   * Import the default user display configuration.
   */
  private function importUserDisplayConfig() {
    $config = $this->getUserDisplayConfig();

    $yaml = file_get_contents(__DIR__ . '/../../fixtures/config/core.entity_view_display.user.user.default.yml');

    $config
      ->setData(Yaml::parse($yaml))
      ->save();
  }

  /**
   * Hide the specified field from user display.
   *
   * @param array $field_names
   *   Fields to hide from user display.
   */
  private function hideUserDisplayFields(array $field_names) {
    $config = $this->getUserDisplayConfig();

    foreach ($field_names as $field_name) {
      $config
        ->set('hidden', [$field_name => TRUE])
        ->save();

      $content = $config->get('content');
      unset($content[$field_name]);

      $config
        ->set('content', $content)
        ->save();
    }
  }

}
