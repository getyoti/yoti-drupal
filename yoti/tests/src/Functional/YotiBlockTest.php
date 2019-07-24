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
      ->set('yoti_scenario_id', 'test_scenario_id')
      ->set('yoti_sdk_id', 'test_sdk_id')
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
    $assert = $this->assertSession();
    $assert->elementExists('css', "div[id='yoti-button-yoti_block']");
    $assert->elementExists('css', 'script[src*=\'js/browser-loader.js\']');
    $assert->responseMatches('~"domId":.*?"yoti-button-yoti_block"~');
    $assert->responseMatches('~"scenarioId":.*?"test_scenario_id"~');
    $assert->responseMatches('~"clientSdkId":.*?"test_sdk_id"~');
    $assert->responseMatches('~"label":.*?"Use Yoti"~');

    $this->drupalLogin($this->unlinkedUser);
    $this->drupalGet('<front>');
    $assert->responseMatches('~"label":.*?"Link to Yoti"~');
  }

}
