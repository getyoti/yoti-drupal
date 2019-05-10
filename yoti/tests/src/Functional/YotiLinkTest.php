<?php

namespace Drupal\Tests\yoti\Functional;

/**
 * Tests the link route.
 *
 * @group yoti
 */
class YotiLinkTest extends YotiBrowserTestBase {

  /**
   * Test link menu item for linked users.
   */
  public function testLinkForLinkedUsers() {
    $assert = $this->assertSession();

    $this->drupalLogin($this->linkedUser);
    $this->drupalGet('yoti/link');
    $assert->statusCodeEquals(403);
    $assert->pageTextContains('Access denied');
  }

  /**
   * Test link menu item for unlinked users.
   */
  public function testLinkForUnlinkedUsers() {
    $assert = $this->assertSession();

    $this->drupalLogin($this->unlinkedUser);

    $this->drupalGet('yoti/link', ['query' => ['token' => '']]);
    $assert->pageTextContains('Could not get Yoti token.');

    $this->drupalGet('yoti/link', ['query' => ['token' => 'some-invalid-token']]);
    $assert->pageTextContains('Yoti could not successfully connect to your account.');
  }

}
