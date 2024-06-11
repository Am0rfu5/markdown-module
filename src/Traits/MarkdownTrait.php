<?php

namespace Drupal\markdown\Traits;

/**
 * Trait for adding the Markdown service to classes.
 */
trait MarkdownTrait {

  /**
   * The Markdown service.
   *
   * @var \Drupal\markdown\MarkdownInterface
   */
  protected static $markdown;

  /**
   * Retrieves the Markdown service.
   *
   * @return \Drupal\markdown\MarkdownInterface
   *   The Markdown service.
   */
  protected static function markdown() {
    if (!isset(static::$markdown)) {
      static::$markdown = \Drupal::service('markdown');
    }
    return static::$markdown;
  }

}
