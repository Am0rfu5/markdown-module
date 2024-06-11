<?php

namespace Drupal\markdown\PluginManager;

/**
 * Interface for plugin managers that are "enable" aware.
 *
 * @method \Drupal\markdown\Plugin\Markdown\EnabledPluginInterface[] all(array $configuration = [], $includeFallback = FALSE) : array
 * @method \Drupal\markdown\Plugin\Markdown\EnabledPluginInterface createInstance($plugin_id, array $configuration = [])
 * @method \Drupal\markdown\Plugin\Markdown\EnabledPluginInterface[] installed(array $configuration = []) : array
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
interface EnableAwarePluginManagerInterface extends InstallablePluginManagerInterface {

  /**
   * Retrieves all enabled plugins.
   *
   * @param array $configuration
   *   The configuration used to create plugin instances.
   *
   * @return \Drupal\markdown\Plugin\Markdown\EnabledPluginInterface[]
   *   An array of enabled plugins instances, keyed by plugin identifier.
   */
  public function enabled(array $configuration = []);

}
