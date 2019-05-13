<?php

namespace Drupal\Tests\yoti\Functional;

/**
 * Tests the Yoti block.
 *
 * @group yoti
 */
class YotiBlockTest extends YotiBrowserTestBase {

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
    $this->drupalPlaceBlock('yoti_block');

    // Load the homepage.
    $this->drupalGet('<front>');

    // Check the block has been placed.
    $span_attributes = [
      '[data-yoti-application-id=\'test_app_id\']',
      '[data-yoti-type=\'inline\']',
      '[data-yoti-scenario-id=\'test_scenario_id\']',
      '[data-size=\'small\']',
    ];
    $assert = $this->assertSession();
    $assert->elementExists('css', 'span' . implode('', $span_attributes));
    $assert->elementExists('css', 'script[src*=\'js/browser-loader.js\']');
  }

}
