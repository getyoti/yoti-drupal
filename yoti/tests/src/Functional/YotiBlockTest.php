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
   * Setup tests.
   */
  public function setup() {
    parent::setup();

    $this->config('yoti.settings')
      ->set('yoti_app_id', 'test_app_id')
      ->set('yoti_scenario_id', 'test_scenario_id')
      ->save();
  }

  /**
   * Test Yoti block.
   */
  public function testYotiBlock() {
    // Place the block.
    $block = $this->drupalPlaceBlock('yoti_block');

    // Load the homepage.
    $this->drupalGet('<front>');

    // Check the block has been placed.
    $span_attributes = [
      '[data-yoti-application-id=\'test_app_id\']',
      '[data-yoti-type=\'inline\']',
      '[data-yoti-scenario-id=\'test_scenario_id\']',
      '[data-size=\'small\']',
    ];
    $this->assertSession()->elementExists('css', 'span' . implode('', $span_attributes));
    $this->assertSession()->elementExists('css', 'script[src*=\'js/browser-loader.js\']');
  }

}
