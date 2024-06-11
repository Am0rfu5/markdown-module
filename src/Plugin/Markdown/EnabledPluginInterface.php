<?php

namespace Drupal\markdown\Plugin\Markdown;

/**
 * Interface for plugins with an "enabled" state.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
interface EnabledPluginInterface extends InstallablePluginInterface {

  /**
   * Indicates the default "enabled" state.
   *
   * The plugin will default to this value when not overridden by passed
   * configuration.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function enabledByDefault();

  /**
   * Indicates whether the plugin is enabled.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isEnabled();

}
