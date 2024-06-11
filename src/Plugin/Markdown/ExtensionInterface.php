<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\markdown\Util\ParserAwareInterface;

/**
 * Interface for extensions.
 *
 * @method \Drupal\markdown\Annotation\MarkdownExtension getPluginDefinition()
 */
interface ExtensionInterface extends EnabledPluginInterface, ParserAwareInterface {

  /**
   * Indicates whether the extension is automatically installed with the parser.
   *
   * Note: this does not indicate whether the extension is actually being used,
   * just that it is available because it came with the parser.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isBundled();

  /**
   * Retrieves identifiers of extensions that this extension requires.
   *
   * @return string[]
   *   An indexed array of extensions this extension requires.
   */
  public function requires();

  /**
   * Retrieves identifiers of extensions that are required by this extension.
   *
   * @return string[]
   *   An indexed array of extension required by this extension.
   */
  public function requiredBy();

}
