<?php

namespace Drupal\markdown\Util;

/**
 * Utility for converting arrays to key|value pipes and back again.
 *
 * @todo Consider turning into a serializer and automate somehow with config.
 *
 * @internal
 */
class KeyValuePipeConverter {

  /**
   * Normalizes a key|value string into an array.
   *
   * @param mixed $value
   *   The value to normalize.
   *
   * @return array
   *   An associative array containing key/value pairs.
   */
  public static function normalize($value) {
    // Immediately return if already an array.
    if (is_array($value)) {
      return $value;
    }

    $array = [];

    // Normalize new lines.
    if (is_string($value) || (is_object($value) && method_exists($value, '__toString'))) {
      $value = preg_replace('/\R/u', "\n", $value);
      foreach (explode("\n", preg_replace('/\R/u', "\n", $value)) as $line) {
        // Treat lines without a pipe as just indexed values.
        if (strpos($line, '|') === FALSE) {
          $array[] = $line;
        }
        else {
          list($k, $v) = explode('|', $line, 2);
          $array[$k] = $v;
        }
      }
    }

    return $array;
  }

  /**
   * Denormalizes a key|value array into a string.
   *
   * @param mixed $value
   *   The value to denormalize.
   * @param bool $keys
   *   Flag indicating whether to use keys.
   *
   * @return string
   *   The denormalized string.
   */
  public static function denormalize($value, $keys = TRUE) {
    // Immediately return if already a string.
    if (is_string($value)) {
      return $value;
    }
    $lines = [];
    if (is_array($value)) {
      foreach ($value as $k => $v) {
        // Skip multidimensional arrays.
        if (!is_string($v)) {
          continue;
        }
        $lines[] = $keys ? "$k|$v" : $v;
      }
    }
    return implode("\n", $lines);
  }

  public static function denormalizeNoKeys($value) {
    return static::denormalize($value, FALSE);
  }

}
