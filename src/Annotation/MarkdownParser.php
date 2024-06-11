<?php

namespace Drupal\markdown\Annotation;

/**
 * Markdown Parser Annotation.
 *
 * @Annotation
 */
class MarkdownParser extends InstallablePlugin {

  /**
   * List of markdown extension plugin identifiers, bundled with the parser.
   *
   * @var string[]
   */
  public $bundledExtensions = [];

  /**
   * A list of extension interface class names.
   *
   * This allows a parser to indicate which extensions belong to it by
   * requiring the extension to implement at least one of these interfaces.
   *
   * @var string[]
   */
  public $extensionInterfaces = [];

}
