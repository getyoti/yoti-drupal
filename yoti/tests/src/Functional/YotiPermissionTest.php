<?php

namespace Drupal\Tests\yoti\Functional;

use Drupal\user\RoleInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the Yoti Permissions.
 *
 * @group yoti
 */
class YotiPermissionTest extends YotiBrowserTestBase {

  /**
   * User's role ID.
   *
   * @var string
   */
  protected $rid;

  /**
   * Setup permission tests.
   */
  public function setUp() {
    parent::setUp();

    // Get the new role ID.
    $all_rids = $this->unlinkedUser
      ->getRoles();
    unset($all_rids[array_search(RoleInterface::AUTHENTICATED_ID, $all_rids)]);
    $this->rid = reset($all_rids);

    // Log test user in before each test.
    $this->drupalLogin($this->unlinkedUser);
  }

  /**
   * Test admin form access when permission is granted.
   */
  public function testYotiPermissionGranted() {
    $role = Role::load($this->rid);
    $role->grantPermission('administer yoti');
    $role->save();

    $this->drupalGet('admin/config/people/yoti');

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('YOTI DASHBOARD');
    $assert->elementExists('css', '#yoti-admin-form');
  }

  /**
   * Test admin form access when permission is not granted.
   */
  public function testYotiPermissionNotGranted() {
    $this->drupalGet('admin/config/people/yoti');

    $assert = $this->assertSession();
    $assert->statusCodeEquals(403);
    $assert->pageTextContains('ACCESS DENIED');
    $assert->elementNotExists('css', '#yoti-admin-form');
  }

}
