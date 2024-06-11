<?php

namespace Drupal\markdown\PluginManager;

/**
 * Interface for the Markdown Extension Plugin Manager.
 *
 * @method \Drupal\markdown\Plugin\Markdown\ExtensionInterface[] all(array $configuration = [], $includeFallback = FALSE) : array
 * @method \Drupal\markdown\Plugin\Markdown\ExtensionInterface createInstance($plugin_id, array $configuration = [])
 * @method \Drupal\markdown\Annotation\MarkdownExtension getDefinition($plugin_id, $exception_on_invalid = TRUE)
 * @method \Drupal\markdown\Annotation\MarkdownExtension|void getDefinitionByClassName($className)
 * @method \Drupal\markdown\Annotation\MarkdownExtension[] getDefinitions($includeFallback = TRUE)
 * @method \Drupal\markdown\Plugin\Markdown\ExtensionInterface[] installed(array $configuration = []) : array
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
interface ExtensionManagerInterface extends InstallablePluginManagerInterface {
}
