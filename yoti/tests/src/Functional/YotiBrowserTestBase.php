<?php

namespace Drupal\Tests\yoti\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\yoti\YotiHelper;

/**
 * BrowserTestBase for Yoti tests.
 *
 * @group yoti
 */
class YotiBrowserTestBase extends BrowserTestBase {

  /**
   * Linked User.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $linkedUser;

  /**
   * Unlinked User.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $unlinkedUser;

  /**
   * Selfie file path.
   *
   * @var string
   */
  protected $selfieFilePath;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['yoti'];

  /**
   * Setup Tests.
   */
  public function setup() {
    parent::setup();

    // Create linked user.
    $this->createLinkedUser();

    // Create unlinked user.
    $this->unlinkedUser = $this->drupalCreateUser([
      'access content',
    ]);
  }

  /**
   * Create a linked Drupal User.
   */
  private function createLinkedUser() {
    // Create linked user.
    $this->linkedUser = $this->drupalCreateUser([
      'access content',
    ]);

    // Generate test user data from known attributes.
    foreach (yoti_map_params() as $field => $label) {
      $user_data[$field] = $label . ' value';
    }

    // Create test selfie file.
    mkdir(YotiHelper::uploadDir(), 0777, TRUE);
    $this->selfieFilePath = YotiHelper::uploadDir() . DIRECTORY_SEPARATOR . 'test_selfie.jpg';
    file_put_contents($this->selfieFilePath, 'test_selfie_contents');
    $user_data[YotiHelper::ATTR_SELFIE_FILE_NAME] = basename($this->selfieFilePath);

    \Drupal::database()->insert(YotiHelper::YOTI_USER_TABLE_NAME)->fields([
      'uid' => $this->linkedUser->id(),
      'identifier' => 'some-remember-me-id',
      'data' => serialize($user_data),
    ])->execute();

  }

  /**
   * Teardown Tests.
   */
  public function teardown() {
    // Cleanup selfie.
    if (is_file($this->selfieFilePath)) {
      unlink($this->selfieFilePath);
    }

    // Cleanup private directory.
    rmdir(YotiHelper::uploadDir());

    parent::teardown();
  }

}
