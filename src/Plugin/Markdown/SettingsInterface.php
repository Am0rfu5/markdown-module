<?php

namespace Drupal\markdown\Plugin\Markdown;

/**
 * Interface for installable plugins that implement settings.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
interface SettingsInterface {

  /**
   * Provides the default settings for the plugin.
   *
   * @param \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition
   *   The plugin definition.
   *
   * @return array
   *   The default settings.
   */
  public static function defaultSettings($pluginDefinition);

  /**
   * Retrieves the default value for the setting.
   *
   * @param string $name
   *   The setting name. This can be a nested value using dot notation (e.g.
   *   "nested.property.key").
   *
   * @return mixed
   *   The settings value or NULL if not set.
   */
  public function getDefaultSetting($name);

  /**
   * Retrieves a setting for the plugin.
   *
   * @param string $name
   *   The setting name. This can be a nested value using dot notation (e.g.
   *   "nested.property.key").
   * @param mixed $default
   *   Optional. The default value to provide if $name isn't set.
   *
   * @return mixed
   *   The settings value or $default if not set.
   */
  public function getSetting($name, $default = NULL);

  /**
   * Retrieves the current settings.
   *
   * @param bool $runtime
   *   Flag indicating whether the request is for runtime values, which
   *   may or may not need to be transformed for whatever is consuming it.
   * @param bool $sorted
   *   Flag indicating whether to sort they settings by property name to
   *   ensure they're always in the same order (configuration consistency).
   *
   * @return array
   *   The settings array
   */
  public function getSettings($runtime = FALSE, $sorted = TRUE);

  /**
   * @param bool $runtime
   *   Flag indicating whether the request is for runtime values, which
   *   may or may not need to be transformed for whatever is consuming it.
   * @param bool $sorted
   *   Flag indicating whether to sort they settings by property name to
   *   ensure they're always in the same order (configuration consistency).
   * @param array $settings
   *   Optional. Specific settings to check. If not set, the currently
   *   set settings will be used.
   *
   * @return array
   */
  public function getSettingOverrides($runtime = FALSE, $sorted = TRUE, array $settings = NULL);

  /**
   * Flag indicating whether a setting exists.
   *
   * @param string $name
   *   The name of the setting to check.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function settingExists($name);

  /**
   * The array key name to use when the settings are nested in another array.
   *
   * @see \Drupal\markdown\Plugin\Markdown\CommonMark\CommonMark::getEnvironment()
   *
   * @return mixed
   *   The settings key.
   */
  public function settingsKey();

}
