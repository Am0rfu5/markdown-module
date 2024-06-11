<?php

namespace Drupal\markdown\PluginManager;

/**
 * Interface for the Markdown Parser Plugin Manager.
 *
 * @method \Drupal\markdown\Plugin\Markdown\ParserInterface[] all(array $configuration = [], $includeFallback = FALSE) : array
 * @method \Drupal\markdown\Plugin\Markdown\ParserInterface createInstance($plugin_id, array $configuration = [])
 * @method \Drupal\markdown\Plugin\Markdown\ParserInterface[] enabled(array $configuration = []) : array
 * @method \Drupal\markdown\Annotation\MarkdownParser getDefinition($plugin_id, $exception_on_invalid = TRUE)
 * @method \Drupal\markdown\Annotation\MarkdownParser|void getDefinitionByClassName($className)
 * @method \Drupal\markdown\Annotation\MarkdownParser[] getDefinitions($includeFallback = TRUE)
 * @method string getFallbackPluginId($plugin_id = NULL, array $configuration = [])
 * @method \Drupal\markdown\Plugin\Markdown\ParserInterface[] installed(array $configuration = []) : array
 */
interface ParserManagerInterface extends EnableAwarePluginManagerInterface {

  /**
   * Retrieves the site-wide default MarkdownParser plugin.
   *
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\markdown\Plugin\Markdown\ParserInterface
   *   A MarkdownParser plugin.
   */
  public function getDefaultParser(array $configuration = []);

}
