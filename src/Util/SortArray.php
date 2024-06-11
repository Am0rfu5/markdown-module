<?php

namespace Drupal\markdown\Util;

use Drupal\Component\Utility\SortArray as CoreSortArray;

/**
 * Array sorting helper methods.
 */
class SortArray extends CoreSortArray {

  /**
   * Sorts a definitions array.
   *
   * This sorts the definitions array first by the weight column, and then by
   * the plugin label, ensuring a stable, deterministic, and testable ordering
   * of plugins.
   *
   * @param array[]|object[] $array
   *   The definitions array to sort.
   * @param array $properties
   *   Optional. The properties to sort by.
   */
  public static function multisortProperties(array &$array, array $properties) {
    // Immediately return if there's not enough definitions to sort.
    if (!$array || ($count = count($array)) === 1) {
      return;
    }

    $args = [];
    foreach ($properties as $property) {
      $values = [];
      $comparison = NULL;

      // Don't use array_column() here, PHP versions less than 7.0.0 don't work
      // as expected due to the fact that the definitions are objects.
      // @todo Use array_column() in 3.0.0 when PHP minimum is much higher.
      array_walk($array, function ($item, $key) use ($property, &$comparison, &$values) {
        if ($item instanceof \Iterator || $item instanceof \Traversable) {
          $item = iterator_to_array($item);
        }
        if (!is_array($item)) {
          return;
        }
        if (isset($item[$property])) {
          $value = $item[$property];
          if (!isset($comparison)) {
            $comparison = !is_string($value) && is_numeric($value) ? SORT_NUMERIC : SORT_NATURAL;
          }
          $values[$key] = $comparison === SORT_NATURAL ? preg_replace("/[^a-z0-9]/", '', strtolower($value)) : $value;
        }
      });
      if ($values && ($valueCount = count($values)) === $count) {
        $args = array_merge($args, [$values, $comparison]);
      }
    }

    // Array multi-sort.
    if ($args) {
      $args = array_merge($args, [&$array]);
      // @todo Use variable unpacking in 3.0.0, i.e. array_multisort(...$args).
      call_user_func_array('array_multisort', $args);
    }
    else {
      ksort($array);
    }
  }

  /**
   * Recursively sorts an array by key.
   *
   * @param array $array
   *   An array to sort, passed by reference.
   */
  public static function recursiveKeySort(array &$array) {
    // First, sort the main array.
    ksort($array);

    // Then check for child arrays.
    foreach ($array as $key => &$value) {
      if (is_array($value)) {
        static::recursiveKeySort($value);
      }
    }
  }

}
