<?php

namespace Drupal\Tests\yoti\Functional;

use Drupal\user\RoleInterface;
use Drupal\user\Entity\Role;
use Drupal\file\Entity\File;

/**
 * Tests the Yoti Settings Form.
 *
 * @group yoti
 */
class YotiSettingsFormTest extends YotiBrowserTestBase {

  /**
   * User's role ID.
   *
   * @var string
   */
  protected $rid;

  /**
   * Setup settings form tests.
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

    // Allow user to configure module.
    $role = Role::load($this->rid);
    $role->grantPermission('administer yoti');
    $role->save();

    // Create a test pem file so that admin form can be saved.
    $file = File::create([
      'uri' => 'private://test.pem',
    ]);
    $file->save();
    $this->config('yoti.settings')
      ->set('yoti_pem', [$file->id()])
      ->save();
  }

  /**
   * Test settings form submission.
   */
  public function testSettingsFormSubmission() {
    $this->drupalGet('admin/config/people/yoti');

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);

    $page = $this->getSession()->getPage();

    $form_values = [
      'App ID' => 'test_app_id',
      'Scenario ID' => 'test_scenario_id',
      'Client SDK ID' => 'test_client_sdk_id',
      'Company Name' => 'test_company_name',
      'Success URL' => '/user',
      'Fail URL' => '/fail',
    ];
    foreach ($form_values as $label => $value) {
      $page->fillField($label, $value . "\t \n\r\x0B");
    }
    $page->pressButton('Save configuration');

    foreach ($form_values as $value) {
      $assert->elementExists('css', sprintf("input[value='%s']", htmlspecialchars($value)));
    }

  }

}
