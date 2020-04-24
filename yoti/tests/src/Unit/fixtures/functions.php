<?php

/**
 * @file
 * Mock functions in the Drupal\yoti namespace.
 */

namespace Drupal\yoti;

use Drupal\Tests\yoti\Unit\Util\MockFunctions;

if (!function_exists('user_load_by_mail')) {

  /**
   * Mock user_load_by_mail().
   */
  function user_load_by_mail() {
    return MockFunctions::call(__FUNCTION__, func_get_args());
  }

}

if (!function_exists('user_password')) {

  /**
   * Mock user_password().
   */
  function user_password() {
    return MockFunctions::call(__FUNCTION__, func_get_args());
  }

}
