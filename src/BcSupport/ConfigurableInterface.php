<?php

namespace Drupal\markdown\BcSupport;

if (!interface_exists('\Drupal\Component\Plugin\ConfigurableInterface')) {
  /* @noinspection PhpIgnoredClassAliasDeclaration */
  class_alias('\Drupal\markdown\BcSupport\BcAliasedInterface', '\Drupal\Component\Plugin\ConfigurableInterface');
}

use Drupal\Component\Plugin\ConfigurableInterface as CoreConfigurableInterface;

/**
 * Provides an interface for a configurable plugin.
 *
 * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
 *   Use \Drupal\Component\Plugin\ConfigurableInterface instead.
 * @see https://www.drupal.org/project/markdown/issues/3103679
 */
interface ConfigurableInterface extends CoreConfigurableInterface {

  /**
   * Gets this plugin's configuration.
   *
   * @return array
   *   An array of this plugin's configuration.
   */
  public function getConfiguration();

  /**
   * Sets the configuration for this plugin instance.
   *
   * @param array $configuration
   *   An associative array containing the plugin's configuration.
   */
  public function setConfiguration(array $configuration);

  /**
   * Gets default configuration for this plugin.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  public function defaultConfiguration();

}
