<?php

namespace Drupal\Tests\yoti\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Yoti unit test base class.
 */
abstract class YotiUnitTestBase extends UnitTestCase {

  /**
   * Original $_GET value.
   *
   * @var array
   */
  private $originalGet;

  /**
   * Test file directory.
   *
   * @var string
   */
  protected $tmpDir;

  /**
   * Setup Yoti tests.
   */
  public function setup() {
    $this->originalGet = $_GET;

    parent::setup();

    // Create tmp file directory.
    $this->tmpDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'drupal-yoti';
    if (!is_dir($this->tmpDir)) {
      mkdir($this->tmpDir, 0777, TRUE);
    }
  }

  /**
   * Clean up test data.
   */
  public function teardown() {
    $_GET = $this->originalGet;

    // Remove test file directory.
    if (is_dir($this->tmpDir)) {
      rmdir($this->tmpDir);
    }

    parent::teardown();
  }

}
