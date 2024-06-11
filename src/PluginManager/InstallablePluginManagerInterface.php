<?php

namespace Drupal\markdown\PluginManager;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Installable plugin manger interface.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
interface InstallablePluginManagerInterface extends CacheableDependencyInterface, CachedDiscoveryInterface, ContainerAwareInterface, ContainerInjectionInterface, PluginManagerInterface, FallbackPluginManagerInterface {

  /**
   * Retrieves all registered plugins.
   *
   * @param array $configuration
   *   The configuration used to create plugin instances.
   * @param bool $includeFallback
   *   Flag indicating whether to include the fallback plugin.
   *
   * @return \Drupal\markdown\Plugin\Markdown\InstallablePluginInterface[]
   *   An array of installed plugins instances, keyed by plugin identifier.
   */
  public function all(array $configuration = [], $includeFallback = FALSE);

  /**
   * Creates a pre-configured instance of a plugin.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\markdown\Plugin\Markdown\InstallablePluginInterface
   *   A fully configured plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = []);

  /**
   * Retrieves the first installed plugin identifier.
   *
   * @return string
   *   The first installed plugin identifier.
   */
  public function firstInstalledPluginId();

  /**
   * Retrieves the cache key to use.
   *
   * @param bool $runtime
   *   Flag indicating whether to retrieve runtime definitions.
   *
   * @return string
   *   The cache key.
   */
  public function getCacheKey($runtime = FALSE);

  /**
   * Retrieves all cache tags that the plugin manager may implement.
   *
   * @return string[]
   *   An array of cache tags.
   */
  public function getCacheTags();

  /**
   * Retrieves a definition by class name.
   *
   * @param string $className
   *   The class name to match.
   *
   * @return \Drupal\markdown\Annotation\InstallablePlugin|void
   *   The first plugin definition matching the class name or NULL if not found.
   */
  public function getDefinitionByClassName($className);

  /**
   * Retrieves a definition by library identifier.
   *
   * @param string $libraryId
   *   The library identifier to match.
   *
   * @return \Drupal\markdown\Annotation\InstallablePlugin|void
   *   The first plugin definition matching the first library identifier or
   *   NULL if not found.
   */
  public function getDefinitionByLibraryId($libraryId);

  /**
   * Gets the definition of all plugins for this type.
   *
   * @param bool $includeFallback
   *   Flag indicating whether to include the "fallback" definition.
   *
   * @return \Drupal\markdown\Annotation\InstallablePlugin[]
   *   An array of plugin definitions (empty array if no definitions were
   *   found). Keys are plugin IDs.
   */
  public function getDefinitions($includeFallback = TRUE);

  /**
   * Retrieves all installed plugins.
   *
   * @param array $configuration
   *   The configuration used to create plugin instances.
   *
   * @return \Drupal\markdown\Plugin\Markdown\InstallablePluginInterface[]
   *   An array of installed plugins instances, keyed by plugin identifier.
   */
  public function installed(array $configuration = []);

  /**
   * Retrieves installed plugin definitions.
   *
   * @return array[]
   *   An array of plugin definitions, keyed by identifier.
   */
  public function installedDefinitions();

}
