<?php

namespace Drupal\Tests\yoti\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\RoleInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the Yoti Permissions.
 *
 * @group yoti
 */
class YotiPermissionTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['yoti'];

  /**
   * Test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * User's role ID.
   *
   * @var string
   */
  protected $rid;

  /**
   * Setup permission tests.
   */
  protected function setUp() {
    parent::setUp();

    // Create a test user.
    $this->testUser = $this->drupalCreateUser([
      'access content',
    ]);

    // Get the new role ID.
    $all_rids = $this->testUser
      ->getRoles();
    unset($all_rids[array_search(RoleInterface::AUTHENTICATED_ID, $all_rids)]);
    $this->rid = reset($all_rids);

    // Log test user in before each test.
    $this->drupalLogin($this->testUser);
  }

  /**
   * Test admin form access when permission is granted.
   */
  public function testYotiPermissionGranted() {
    $role = Role::load($this->rid);
    $role->grantPermission('administer yoti');
    $role->save();

    $this->drupalGet('admin/config/people/yoti');

    $assertSession = $this->assertSession();
    $assertSession->statusCodeEquals(200);
    $assertSession->pageTextContains('YOTI DASHBOARD');
    $assertSession->elementExists('css', '#yoti-admin-form');
  }

  /**
   * Test admin form access when permission is not granted.
   */
  public function testYotiPermissionNotGranted() {
    $this->drupalGet('admin/config/people/yoti');

    $assertSession = $this->assertSession();
    $assertSession->statusCodeEquals(403);
    $assertSession->pageTextContains('ACCESS DENIED');
    $assertSession->elementNotExists('css', '#yoti-admin-form');
  }

}
