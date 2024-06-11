<?php

namespace Drupal\markdown\Annotation;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Crypt;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Creates an identifier for use in annotation objects.
 *
 * @property string camelCase
 * @property string css
 * @property string snakeCase
 * @property string value
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 * @todo Consider extending https://github.com/danielstjules/Stringy
 */
class Identifier implements MarkupInterface {

  /**
   * The converter.
   *
   * @var \Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter
   */
  protected static $converter;

  /**
   * A camel cased variation of the identifier.
   *
   * @var string|null
   */
  protected $camelCase;

  /**
   * The identifier sanitized for use in CSS.
   *
   * @var string|null
   */
  protected $css;

  /**
   * A snake cased variation of the identifier.
   *
   * @var string|null
   */
  protected $snakeCase;

  /**
   * The original value.
   *
   * @var string
   */
  protected $value = '';

  /**
   * Retrieves the converter.
   *
   * @return \Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter
   */
  protected static function converter() {
    if (!static::$converter) {
      static::$converter = new CamelCaseToSnakeCaseNameConverter();
    }
    return static::$converter;
  }

  /**
   * Creates a new Identifier instance.
   *
   * @param string|object $value
   *   An identifier string or an object that implements __toString().
   *
   * @return static
   */
  public static function create($value) {
    return new static($value);
  }

  /**
   * Creates a new Identifier instance from an array of values.
   *
   * @param array $values
   *   The values which contain an identifier, passed by reference.
   * @param string $property
   *   Optional. The property which should contain the identifier.
   *
   * @return static
   */
  public static function createFromArray(array &$values, $property = 'id') {
    // Assign a random identifier if one wasn't provided. This is necessary
    // so that a proper list of definitions can be created.
    if (!isset($values[$property])) {
      $values[$property] = Crypt::hashBase64(Crypt::randomBytesBase64() . serialize($values));
    }
    elseif ($values[$property] instanceof static) {
      return $values[$property];
    }
    $values[$property] = new static($values[$property]);
    return $values[$property];
  }

  /**
   * Identifier constructor.
   *
   * @param string|object $value
   *   An identifier string or an object that implements __toString().
   */
  public function __construct($value = NULL) {
    if (!$value || (!is_string($value) || (is_object($value) && !method_exists($value, '__toString')))) {
      return;
    }
    $this->value = (string) $value;
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    $method = static::converter()->denormalize("get_$name");
    if (method_exists($this, $method)) {
      return call_user_func([$this, $method]);
    }
    @trigger_error('Unknown property: ' . $name, E_USER_WARNING);
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return $this->value;
  }

  /**
   * Indicates whether the identifier contains a value.
   *
   * @param string $value
   *   The value to check.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function contains($value) {
    return strpos($this->value, $value) !== FALSE;
  }

  /**
   * Retrieves the camel case variation of the identifier.
   *
   * @return string
   *   The camel case variation of the identifier.
   */
  protected function getCamelCase() {
    if (!isset($this->camelCase)) {
      $this->camelCase = static::converter()->denormalize($this->getSnakeCase());
    }
    return $this->camelCase;
  }

  /**
   * Retrieves the CSS sanitized variation of the identifier.
   *
   * @return string
   *   The CSS variation of the identifier.
   */
  protected function getCss() {
    if (!isset($this->css)) {
      $this->css = strtr($this->getSnakeCase(), '_', '-');
    }
    return $this->css;
  }

  /**
   * Retrieves the snake case variation of the identifier.
   *
   * @return string
   *   The snake case variation of the identifier.
   */
  protected function getSnakeCase() {
    if (!isset($this->snakeCase)) {
      // Lowercase identifier.
      $this->snakeCase = strtolower($this->value);

      // Convert some special characters to double underscores.
      $this->snakeCase = preg_replace('/[:\/\\\]+/', '__', $this->snakeCase);

      // Convert all non-alphanumeric characters to underscores.
      $this->snakeCase = preg_replace('/[^a-z0-9_]+/', '_', $this->snakeCase);

      // Ensure snake case has no more than two underscores per delimiter.
      $this->snakeCase = preg_replace('/__+/', '__', $this->snakeCase);
    }
    return $this->snakeCase;
  }

  /**
   * Retrieves the original value.
   *
   * @return string
   */
  protected function getValue() {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): string {
    return $this->value;
  }

  /**
   * Removes a specific string from the left side of the identifier.
   *
   * @param string|int $value
   *   A string value or length to remove.
   *
   * @return string|void
   *   A substring of the identifier without the value or length passed.
   */
  public function removeLeft($value) {
    if (!is_numeric($value)) {
      $value = $this->startsWith($value) ? strlen($value) : NULL;
    }
    if (isset($value) && ($return = substr($this->value, (int) $value)) && $return !== FALSE) {
      return $return;
    }
  }

  /**
   * Indicates whether the identifier starts with a specific value.
   *
   * @param string $value
   *   The value to test.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function startsWith($value) {
    return strpos($this->value, $value) === 0;
  }

}
