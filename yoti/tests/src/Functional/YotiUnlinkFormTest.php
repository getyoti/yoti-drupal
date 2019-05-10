<?php

namespace Drupal\Tests\yoti\Functional;

/**
 * Tests the Yoti unlink form.
 *
 * @group yoti
 */
class YotiUnlinkFormTest extends YotiBrowserTestBase {

  /**
   * Test Unlink Form.
   */
  public function testUnlinkForm() {
    $this->drupalLogin($this->linkedUser);
    $this->drupalGet('user');

    $assert = $this->assertSession();
    $assert->elementTextContains(
      'css',
      "#yoti-unlink-button[href='/yoti/unlink']",
      'Unlink Yoti account'
    );

    $this->clickLink('Unlink Yoti account');

    $assert->elementTextContains(
      'css',
      'h1',
      'Unlink Yoti Account'
    );

    $assert->pageTextContains('Are you sure you want to unlink your account from Yoti?');
    $this->drupalPostForm('yoti/unlink', [], t('Yes'));
    $assert->addressMatches('~^/user/~');

    // Check account was unlinked.
    $this->drupalGet('user');
    $assert->pageTextNotContains('Unlink Yoti Account');
  }

}
