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

    $this->linkedUser = $this->drupalCreateUser([
      'access content',
    ]);
    \Drupal::database()->insert(YotiHelper::YOTI_USER_TABLE_NAME)->fields([
      'uid' => $this->linkedUser->id(),
      'identifier' => 'some-remember-me-id',
      'data' => serialize([]),
    ])->execute();

    $this->unlinkedUser = $this->drupalCreateUser([
      'access content',
    ]);
  }

}
