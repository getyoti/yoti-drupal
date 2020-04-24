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
    $this->assertSelfieImage();

    // Check unlink button is present.
    $this->assertSession()->elementTextContains(
      'css',
      "#yoti-unlink-button[href*='/yoti/unlink']",
      'Unlink Yoti account'
    );
  }

  /**
   * Test viewing profile as user with only profile permission.
   */
  public function testProfileLinkedAsUserWithProfilePermission() {
    $userWithUserProfilePermission = $this->createLinkedUser([
      'access user profiles',
    ]);
    $this->drupalLogin($userWithUserProfilePermission);

    // Check user can view own profile and selfie.
    $this->drupalGet('user/' . $userWithUserProfilePermission->id());
    $this->assertProfileFields(yoti_map_params());
    $this->assertSelfieImage();

    // Check they cannot see other user profile images.
    $url = $this->getSelfieImageUrl();
    $url['query']['user_id'] = $this->linkedUser->id();
    $this->drupalGet(trim($url['path'], '/'), ['query' => $url['query']]);
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->responseNotContains('test_selfie_contents');

    // Check other users profile.
    $this->drupalGet('user/' . $this->linkedUser->id());

    $profile_data = yoti_map_params();
    unset($profile_data[Profile::ATTR_SELFIE]);
    $this->assertProfileFields($profile_data);

    // Check selfie is not present.
    $this->assertSession()->responseNotContains('/yoti/bin-file/selfie');

    // Check unlink button is not present.
    $this->assertSession()->responseNotContains('/yoti/unlink');
  }

  /**
   * Test viewing profile as user with profile and selfie permission.
   */
  public function testProfileLinkedAsUserWithProfileAndSelfiePermission() {
    $userWithUserProfilePermission = $this->drupalCreateUser([
      'access user profiles',
      'view yoti selfie images',
    ]);
    $this->drupalLogin($userWithUserProfilePermission);
    $this->drupalGet('user/' . $this->linkedUser->id());
    $this->assertProfileFields(yoti_map_params());
    $this->assertSelfieImage();

    // Check unlink button is not present.
    $this->assertSession()->responseNotContains('/yoti/unlink');
  }

  /**
   * Test viewing profile as user without permission.
   */
  public function testProfileLinkedAsUserWithoutPermission() {
    $userWithoutUserProfilePermission = $this->drupalCreateUser();
    $this->drupalLogin($userWithoutUserProfilePermission);
    $this->drupalGet('user/' . $this->linkedUser->id());
    $this->assertSession()->statusCodeEquals(403);
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
    $this->assertSelfieImage();
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

    foreach ($profile_data as $key => $label) {
      $assert->elementExists('css', '#yoti-profile-' . $key);
      $assert->responseContains($label . ' value');
    }
  }

  /**
   * Get selfie url from current page.
   *
   * @return array
   *   Parsed URL consisting of `path` and `query`.
   */
  private function getSelfieImageUrl() {
    // Check selfie image is present.
    $selfie_selector = "img[src*='/yoti/bin-file/selfie'][width='100']";
    $this->assertSession()->elementExists('css', $selfie_selector);

    // Visit selfie using img src attribute.
    $selfie_src_attr = $this
      ->getSession()
      ->getPage()
      ->find('css', $selfie_selector)
      ->getAttribute('src');

    $url = htmlspecialchars_decode($selfie_src_attr);
    $path = parse_url($url, PHP_URL_PATH);
    parse_str(parse_url($url, PHP_URL_QUERY), $query_params);

    return [
      'path' => $path,
      'query' => $query_params,
    ];
  }

  /**
   * Assert that the selfie image on current page is present and can be viewed.
   */
  private function assertSelfieImage() {
    $url = $this->getSelfieImageUrl();

    $this->drupalGet(trim($url['path'], '/'), ['query' => $url['query']]);
    $this->assertSession()->responseContains('test_selfie_contents');

    // Go back to previous page.
    $this->getSession()->back();
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

    $yaml = file_get_contents(__DIR__ . '/fixtures/config/core.entity_view_display.user.user.default.yml');

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
        ->set('hidden.' . $field_name, TRUE)
        ->save();

      $content = $config->get('content');
      unset($content[$field_name]);

      $config
        ->set('content', $content)
        ->save();
    }
  }

}
