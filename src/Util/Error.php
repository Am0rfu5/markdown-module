<?php

namespace Drupal\markdown\Util;

use Drupal\Core\Utility\Error as CoreError;

class Error extends CoreError {

  /**
   * Suppresses any errors or exceptions while executing a callback.
   *
   * @param callable $callback
   *   The callback to execute and suppress any errors or exceptions.
   * @param array $exceptions
   *   An array of exceptions that were thrown, if any, passed by reference.
   * @param int $errorTypes
   *   The error types to catch.
   *
   * @return mixed|null
   *   The return value of the callback.
   */
  public static function suppress(callable $callback, array &$exceptions = [], $errorTypes = E_ERROR | E_RECOVERABLE_ERROR | E_USER_ERROR) {
    set_error_handler(function ($severity, $message, $file, $line) {
      throw new \ErrorException($message, 0, $severity, $file, $line);
    }, $errorTypes);

    $result = NULL;
    try {
      $result = $callback();
    }
    catch (\Throwable $exception) {
      $exceptions[] = $exception;
    }
    catch (\Exception $exception) {
      $exceptions[] = $exception;
    }

    restore_error_handler();

    return $result;
  }

}
