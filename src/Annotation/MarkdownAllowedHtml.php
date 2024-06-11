<?php

namespace Drupal\markdown\Annotation;

/**
 * Markdown Allowed HTML Annotation.
 *
 * @Annotation
 */
class MarkdownAllowedHtml extends InstallablePlugin {

  /**
   * A specific filter that is required for this plugin to work.
   *
   * @var string
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   Use the "requirements" properties instead.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  public $requiresFilter;

  /**
   * The provider of the annotated class.
   *
   * @var string
   */
  public $provider;

  /**
   * The type of object this allowed HTML is associated with.
   *
   * Can be one of: extension, filter, parser, module, theme.
   *
   * @var string
   */
  public $type;

  /**
   * {@inheritdoc}
   */
  protected function protectedProperties() {
    return array_merge(parent::protectedProperties(), ['type']);
  }

}
