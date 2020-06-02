<?php

namespace Drupal\Tests\yoti\Unit;

/**
 * Allows mocking of functions.
 */
class MockFunctions {

  /**
   * Map of mocked function.
   *
   * @var callable[]
   */
  private static $functions = [];

  /**
   * Resets all mocked functions.
   */
  public static function reset(): void {
    self::$functions = [];
  }

  /**
   * Mock a global function with provided callback function.
   *
   * The function being mocked must be implemented in the same
   * namespace as the class being tested and must return
   * ::call(__FUNCTION__, func_get_args());
   *
   * @param string $function
   *   The function name to mock.
   * @param callable $callback
   *   The callback used to mock the return value.
   */
  public static function mock($function, callable $callback): void {
    self::$functions[$function] = $callback;
  }

  /**
   * Mock function with given arguments.
   *
   * @param string $function
   *   The mock function to call.
   * @param array $args
   *   The arguments to pass to the mock function.
   *
   * @return mixed
   *   The return value from the mocked function.
   */
  public static function call($function, array $args = []) {
    $function_name_parts = explode('\\', $function);
    $function_name = array_pop($function_name_parts);
    $function = self::$functions[$function_name] ?? NULL;

    if ($function !== NULL) {
      return $function(...$args);
    }

    return call_user_func_array("\\{$function_name}", $args);
  }

}
