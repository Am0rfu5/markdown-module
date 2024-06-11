<?php

namespace Drupal\markdown\Traits;

/**
 * Trait for utilizing the Renderer service.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
trait RendererTrait {

  /**
   * The Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected static $renderer;

  /**
   * Retrieves the Renderer service.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The Renderer service.
   */
  protected function renderer() {
    if (!static::$renderer) {
      static::$renderer = \Drupal::service('renderer');
    }
    return static::$renderer;
  }

}
