<?php

namespace Drupal\markdown\Exception;

class MarkdownUnexpectedValueException extends \UnexpectedValueException implements MarkdownExceptionInterface {

  /**
   * Known key, if value is in an array or Traversable object.
   *
   * @var int|string
   */
  protected $key;

  /**
   * Know parents, if value is nested inside an array or Traversable object.
   *
   * @var array
   */
  protected $parents = [];

  /**
   * The unexpected value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * Creates a new instance of the exception.
   *
   * @param mixed $value
   *   The unexpected value.
   * @param string|int $key
   *   Known key, if value is in an array or Traversable object.
   * @param array $parents
   *   Know parents, if value is nested inside an array or Traversable object.
   * @param \Throwable $previous
   *   A previous exception, if rethrown.
   * @param string[] $templates
   *   An indexed array of templates to be used depending on whether there is
   *   known hierarchy (i.e. if $key and/or $parents were provided):
   *   - (string) No hierarchy known. Only a single variable (%s) will be
   *     replaced with the unexpected value.
   *   - (string) Hierarchy known. Two variables (%s) will be passed, the first
   *     is the unexpected value and the second is the full path in dot'
   *     notation, constructed from passed $key and $parents.
   */
  public function __construct($value, $key = NULL, array $parents = [], $previous = NULL, array $templates = []) {
    $this->value = $value;
    $this->key = $key;
    $this->parents = $parents;

    list($message, $messageHierarchy) = $templates + ['Unexpected value "%s".', 'Unexpected value "%s" set at %s.'];
    if (isset($this->key)) {
      $name = $this->key;
      if ($this->parents) {
        $name = implode('.', $parents) . ".$name";
      }
      $message = sprintf($messageHierarchy, $value, $name);
    }
    else {
      $message = sprintf($message, $value);
    }

    parent::__construct($message, 0, $previous);
  }

  /**
   * Retrieves the known key, if value is in an array or Traversable object.
   *
   * @return string|int|null
   *   The known key, if any.
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * Retrieves the know parents, if value was nested.
   *
   * @return array
   *   The known parents, if any.
   */
  public function getParents() {
    return $this->parents;
  }

  /**
   * The unexpected value.
   *
   * @return mixed
   *   The unexpected value.
   */
  public function getValue() {
    return $this->value;
  }

}
