<?php

namespace Drupal\Tests\yoti\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Yoti block.
 *
 * @group yoti
 */
class YotiBlockTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['yoti', 'node', 'block'];

  /**
   * Test Yoti block.
   */
  public function testYotiBlock() {
    // Place the block.
    $block = $this->drupalPlaceBlock('yoti_block');

    // Load the homepage.
    $this->drupalGet('<front>');

    // Check the block has been placed.
    $this->assertSession()->elementExists('css', 'span[data-yoti-application-id][data-yoti-type=\'inline\'][data-yoti-scenario-id][data-size=\'small\']');
    $this->assertSession()->elementExists('css', 'script[src*=\'js/browser-loader.js\']');
  }

}
