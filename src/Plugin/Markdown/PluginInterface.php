<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\markdown\BcSupport\ConfigurableInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Interface for markdown plugins.
 *
 * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
 *   Use \Drupal\markdown\Plugin\Markdown\InstallablePluginInterface instead.
 * @see https://www.drupal.org/project/markdown/issues/3142418
 */
interface PluginInterface extends ConfigurableInterface, ContainerAwareInterface, ContainerFactoryPluginInterface, DependentPluginInterface, PluginInspectionInterface {

  /**
   * Retrieves the config instance for this plugin.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   An immutable config instance for this plugin's configuration.
   */
  public function config();

  /**
   * Retrieves the description of the plugin, if set.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The description.
   */
  public function getDescription();

  /**
   * Displays the human-readable label of the plugin.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getLabel();

  /**
   * Returns the provider (extension name) of the plugin.
   *
   * @return string
   *   The provider of the plugin.
   */
  public function getProvider();

  /**
   * Retrieves the URL of the plugin, if set.
   *
   * @return \Drupal\Core\Url|null
   *   A Url object or NULL if not set.
   */
  public function getUrl();

  /**
   * Returns the weight of the plugin (used for sorting).
   *
   * @return int
   *   The plugin weight.
   */
  public function getWeight();

}
