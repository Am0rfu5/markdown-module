<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\markdown\BcSupport\ObjectWithPluginCollectionInterface;

/**
 * Interface MarkdownInterface.
 */
interface ExtensibleParserInterface extends ParserInterface, ObjectWithPluginCollectionInterface {

  /**
   * Retrieves a specific extension plugin instance.
   *
   * @param string $extensionId
   *   The identifier of the extension plugin instance to return.
   *
   * @return \Drupal\markdown\Plugin\Markdown\ExtensionInterface|null
   *   A markdown extension instance or NULL if it doesn't exist.
   */
  public function extension($extensionId);

  /**
   * An array of extension interfaces that the parser supports.
   *
   * @return string[]
   *   An indexed array of interfaces.
   */
  public function extensionInterfaces();

  /**
   * Returns the ordered collection of extension plugin instances.
   *
   * @return \Drupal\markdown\PluginManager\ExtensionCollection|\Drupal\markdown\Plugin\Markdown\ExtensionInterface[]
   *   The extension plugin collection.
   */
  public function extensions();

  /**
   * Retrieves plugin identifiers of extensions bundled with the parser.
   *
   * @return string[]
   *   An indexed array of markdown extension plugin identifiers.
   */
  public function getBundledExtensionIds();

}
