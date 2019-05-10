<?php

namespace Drupal\Tests\yoti\Functional;

/**
 * Tests the Yoti user login form.
 *
 * @group yoti
 */
class YotiUserLoginFormTest extends YotiBrowserTestBase {

  /**
   * Authenticated users can view login form.
   */
  public function testAuthenticatedUser() {
    $this->drupalLogin($this->unlinkedUser);

    $this->drupalGet('yoti/register');

    $assert = $this->assertSession();
    $assert->statusCodeEquals(403);
  }

  /**
   * Anonymous users can view login form.
   */
  public function testAnonymousUser() {
    $this->drupalGet('yoti/register');

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);

    $assert->pageTextContains('Warning: You are about to link your Drupal account to your Yoti account.');
    $assert->pageTextContains("If you don't want this to happen, tick the checkbox below.");
    $assert->elementExists('css', "input[type='checkbox'][name='yoti_nolink']");
  }

}
