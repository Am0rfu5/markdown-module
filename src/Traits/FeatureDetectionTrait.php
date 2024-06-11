<?php

namespace Drupal\markdown\Traits;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Trait for implementing feature detection.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
trait FeatureDetectionTrait {

  /**
   * An array of test features.
   *
   * @var array
   *   An associative array of booleans where the key is the feature name.
   */
  protected static $features = [];

  /**
   * Determines whether a feature exists.
   *
   * @param string $name
   *   The name of the feature. This name will be converted into a camel case
   *   version with "feature" as the prefix (i.e. name => featureName). This
   *   will be used to look for a static method on the object this trait is
   *   used in. If found, it will be invoked and cast to a boolean value.
   *
   * @return bool
   *   TRUE or FALSE
   */
  protected static function featureExists($name) {
    if (!isset(static::$features[$name])) {
      $class = static::class;
      $method = (new CamelCaseToSnakeCaseNameConverter())->denormalize("feature_$name");
      static::$features[$name] = method_exists($class, $method) ? !!$class::$method() : FALSE;
    }
    return static::$features[$name];
  }

}
