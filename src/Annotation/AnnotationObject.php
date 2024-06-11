<?php

namespace Drupal\markdown\Annotation;

use Drupal\Component\Annotation\AnnotationBase;
use Drupal\Component\Annotation\AnnotationInterface;
use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Base annotation class for retrieving the annotation as an object.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins
 *   or as a separate project https://www.drupal.org/project/annotation_object.
 *
 * @property Identifier $id
 */
abstract class AnnotationObject extends AnnotationBase implements \ArrayAccess, \IteratorAggregate, PluginDefinitionInterface {

  use DependencySerializationTrait {
    __sleep as __sleepTrait;
    __wakeup as __wakeupTrait;
  }

  const DEPRECATED_REGEX = '/@deprecated ([^@]+|\n)+(?:@see (.*))?/';

  /**
   * Stores deprecated values.
   *
   * Note: this is primarily used in order to trigger deprecation messages.
   *
   * @var mixed[]
   */
  protected $_deprecated = []; // phpcs:ignore

  /**
   * A list of deprecation messages, keyed by the deprecated property name.
   *
   * @var string[]
   */
  protected $_deprecatedProperties = []; // phpcs:ignore

  /**
   * A list of triggered deprecations.
   *
   * Ensures they're displayed only once per request.
   *
   * @var array
   */
  private $_triggeredDeprecations = []; // phpcs:ignore

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * A human-readable label.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The weight of the plugin.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * AnnotationObject constructor.
   *
   * @param array $values
   *   Optional. The initial values to populate the annotation with.
   */
  public function __construct(array $values = []) {
    $this->validateIdentifier(Identifier::createFromArray($values));

    // Look for deprecated properties so notices can be trigger when accessing
    // them using \ArrayAccess.
    foreach (array_keys(get_object_vars($this)) as $name) {
      try {
        $ref = new \ReflectionProperty($this, $name);

        // Skip non-public properties.
        if (!$ref->isPublic()) {
          continue;
        }

        // Handle deprecated properties.
        if (($doc = $ref->getDocComment()) && preg_match(static::DEPRECATED_REGEX, $doc, $matches)) {
          $deprecation = array_filter(array_map(function ($line) {
            return preg_replace('/^\s*\*?\s*/', '', $line);
          }, explode("\n", $matches[1])));
          array_unshift($deprecation, static::class . "::\$$name is deprecated");
          if (!empty($matches[2])) {
            $deprecation[] = 'See ' . $matches[2];
          }
          $this->_deprecatedProperties[$name] = implode(' ', $deprecation);

          // Now, remove the property from the class so it uses magic methods.
          // This allows deprecated properties accessed using object notation
          // (i.e. $definition->deprecatedProperty) to trigger notices.
          unset($this->$name);
        }
      }
      catch (\ReflectionException $e) {
        // Intentionally do nothing.
      }
    }

    // Now actually set the annotation values.
    // Note: this will trigger deprecations notices for definitions still using
    // deprecated properties.
    $this->doMerge($values);
  }

  /**
   * Allows the creation of new objects statically, for easier chainability.
   *
   * @param array|\Traversable $values
   *   Optional. The initial values to populate the annotation with.
   *
   * @return static
   */
  public static function create($values = []) {
    if ($values instanceof \Traversable) {
      $values = iterator_to_array($values);
    }
    return new static($values);
  }

  /**
   * {@inheritdoc}
   */
  public function &__get($name) {
    return $this->offsetGet($name);
  }

  /**
   * {@inheritdoc}
   */
  public function __isset($name) {
    return $this->offsetExists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function __set($name, $value) {
    $this->offsetSet($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    unset($this->_triggeredDeprecations);
    return $this->__sleepTrait();
  }

  /**
   * {@inheritdoc}
   */
  public function __unset($name) {
    $this->offsetUnset($name);
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup() {
    // Remove the properties from the class so it uses magic methods.
    // This allows deprecated properties accessed using object notation
    // (i.e. $definition->deprecatedProperty) to trigger notices.
    // @see __construct
    foreach (array_keys($this->_deprecatedProperties) as $property) {
      unset($this->$property);
    }
    $this->__wakeupTrait();
  }

  /**
   * Merges values with this plugin.
   *
   * @param array|\Traversable $values
   *   The values to merge.
   * @param array $excludedProperties
   *   Optional. The properties to exclude when merging values.
   *
   * @return static
   */
  protected function doMerge($values, array $excludedProperties = []) {
    if ($values instanceof \Traversable) {
      $values = iterator_to_array($values);
    }
    if (!is_array($values)) {
      return $this;
    }
    if ($excludedProperties) {
      $excludedProperties = array_unique($excludedProperties);
    }
    foreach ($values as $key => $value) {
      // Skip excluded properties.
      if ($excludedProperties && in_array($key, $excludedProperties, TRUE)) {
        continue;
      }
      if ($key === 'id' && !($value instanceof Identifier)) {
        $value = new Identifier($value);
      }
      if (property_exists($this, $key) || $key[0] === '_') {
        if (is_array($value)) {
          $existing = $this->offsetGet($key);
          if (is_array($existing)) {
            $value = NestedArray::mergeDeep($existing, $value);
          }
        }
        if (isset($value)) {
          $this->offsetSet($key, $value);
        }
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return (string) parent::getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable {
    $iterator = new \ArrayIterator($this);
    foreach ($this->_deprecated as $key => $value) {
      $iterator->offsetSet($key, $value);
    }
    return $iterator;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getId();
  }

  /**
   * Merges values with this plugin.
   *
   * @param array|\Traversable $values
   *   The values to merge.
   * @param array $excludedProperties
   *   Optional. The properties to exclude when merging values.
   *
   * @return static
   */
  public function merge($values, array $excludedProperties = []) {
    // Do the merge, merging any excluded properties with the protected
    // properties. This ensures that no public consumer can override them.
    return $this->doMerge($values, array_merge($excludedProperties, $this->protectedProperties()));
  }

  /**
   * Normalizes a value to ensure its ready to be merged with the definition.
   *
   * @param mixed $value
   *   The value to normalize.
   *
   * @return array
   *   The normalized value.
   */
  protected function normalizeValue($value) {
    $normalized = [];
    if ($value instanceof AnnotationInterface) {
      return $value->get();
    }
    elseif (is_array($value) || $value instanceof \Traversable) {
      foreach ($value as $k => $v) {
        $normalized[$k] = $this->normalizeValue($v);
      }
    }
    else {
      return $value;
    }
    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($offset): bool {
    if (array_key_exists($offset, $this->_deprecatedProperties)) {
      return isset($this->_deprecated[$offset]);
    }
    return isset($this->$offset);
  }

  /**
   * {@inheritdoc}
   *
   * @todo add "mixed" return type as soon as Drupal 9.5 is no longer supported.
   */
  #[\ReturnTypeWillChange]
  public function &offsetGet($offset) {
    $value = NULL;
    if (array_key_exists($offset, $this->_deprecatedProperties)) {
      if (isset($this->_deprecated[$offset])) {
        $this->triggerDeprecation($offset);
        $value = &$this->_deprecated[$offset];
      }
    }
    elseif (property_exists($this, $offset)) {
      $value = &$this->$offset;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet($offset, $value = NULL): void {
    if (array_key_exists($offset, $this->_deprecatedProperties)) {
      $this->_deprecated[$offset] = $this->normalizeValue($value);
      $this->triggerDeprecation($offset);
    }
    else {
      $this->$offset = $this->normalizeValue($value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function offsetUnset($offset): void {
    if (array_key_exists($offset, $this->_deprecatedProperties)) {
      unset($this->_deprecated[$offset]);
    }
    elseif (property_exists($this, $offset)) {
      unset($this->$offset);
    }
  }

  /**
   * Indicates properties that should never be overridden after instantiation.
   *
   * @return string[]
   *   The protected properties.
   */
  protected function protectedProperties() {
    return ['id', 'class', 'provider'];
  }

  /**
   * Triggers a deprecation notice for a given property.
   *
   * @param string $name
   *   The name of the property.
   */
  private function triggerDeprecation($name) {
    if (isset($this->_deprecatedProperties[$name]) && !isset($this->_triggeredDeprecations[$name]) && isset($this->_deprecated[$name])) {
      @trigger_error($this->_deprecatedProperties[$name], E_USER_DEPRECATED); // phpcs:ignore
      $this->_triggeredDeprecations[$name] = TRUE;
    }
  }

  /**
   * Helper method for validating the definition identifier.
   *
   * @param Identifier $id
   *   The identifier to validate.
   */
  protected function validateIdentifier(Identifier $id) {
  }

}
