<?php

namespace Drupal\Tests\yoti\Functional;

use Drupal\yoti\YotiHelper;
use Yoti\Entity\Profile;

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

    $assert = $this->assertSession();

    // Check profile data without selfie.
    $profileData = yoti_map_params();
    unset($profileData[YotiHelper::ATTR_SELFIE_FILE_NAME]);
    unset($profileData[Profile::ATTR_SELFIE]);
    foreach ($profileData as $label) {
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
   * Test profile for unlinked users.
   */
  public function testProfileUnlinked() {
    $this->drupalLogin($this->unlinkedUser);
    $this->drupalGet('user');

    foreach (yoti_map_params() as $label) {
      $this->assertSession()->responseNotContains($label);
    }
  }

}
