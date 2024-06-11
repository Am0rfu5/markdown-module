<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\markdown\BcSupport\ConfigurableInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Interface for annotated plugins.
 *
 * @method \Drupal\markdown\Annotation\AnnotationObject getPluginDefinition()
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
interface AnnotatedPluginInterface extends ConfigurableInterface, ContainerAwareInterface, ContainerFactoryPluginInterface, PluginInspectionInterface {

  /**
   * Retrieves the configuration overrides for the plugin.
   *
   * @param array $configuration
   *   Optional. Specific configuration to check. If not set, the currently
   *   set configuration will be used.
   *
   * @return array
   *   An array of configuration overrides.
   */
  public function getConfigurationOverrides(array $configuration = NULL);

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
   * Retrieves the original plugin identifier.
   *
   * This is the identifier that was initially called, but may have changed
   * to the fallback identifier because it didn't exist.
   *
   * @return string
   *   The original plugin identifier.
   */
  public function getOriginalPluginId();

  /**
   * Returns the provider (extension name) of the plugin.
   *
   * @return string
   *   The provider of the plugin.
   */
  public function getProvider();

  /**
   * Returns the weight of the plugin (used for sorting).
   *
   * @return int
   *   The plugin weight.
   */
  public function getWeight();

}
