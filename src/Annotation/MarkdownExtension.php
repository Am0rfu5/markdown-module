<?php

namespace Drupal\markdown\Annotation;

/**
 * Markdown Extension Annotation.
 *
 * @Annotation
 */
class MarkdownExtension extends InstallablePlugin {

  /**
   * An array of extension plugin identifiers that is required.
   *
   * @var string[]
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   Use the "requirements" property instead.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  public $requires = [];

}
