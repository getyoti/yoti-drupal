<?php

namespace Drupal\Tests\yoti\Functional;

/**
 * Tests the user profile.
 *
 * @group yoti
 */
class YotiProfileTest extends YotiBrowserTestBase {

  /**
   * Test user profile.
   */
  public function testProfile() {
    $this->drupalLogin($this->linkedUser);
    $this->drupalGet('user');

    $assert = $this->assertSession();

    foreach (yoti_map_params() as $field => $label) {
      $assert->elementExists('xpath', "//*[@class='label'][contains(text(),'{$label}')]");
    }

    $assert->elementTextContains(
      'css',
      "#yoti-unlink-button[href='/yoti/unlink']",
      'Unlink Yoti account'
    );
  }

}
