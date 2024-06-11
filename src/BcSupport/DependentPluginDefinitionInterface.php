<?php

namespace Drupal\markdown\BcSupport;

if (!interface_exists('\Drupal\Core\Plugin\Definition\DependentPluginDefinitionInterface')) {
  /* @noinspection PhpIgnoredClassAliasDeclaration */
  class_alias('\Drupal\markdown\BcSupport\BcAliasedInterface', '\Drupal\Core\Plugin\Definition\DependentPluginDefinitionInterface');
}

use Drupal\Core\Plugin\Definition\DependentPluginDefinitionInterface as CoreDependentPluginDefinitionInterface;

/**
 * Provides an interface for a plugin definition that has dependencies.
 *
 * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
 *   Use \Drupal\Core\Plugin\Definition\DependentPluginDefinitionInterface
 *   instead.
 * @see https://www.drupal.org/project/markdown/issues/3103679
 */
interface DependentPluginDefinitionInterface extends CoreDependentPluginDefinitionInterface {

  /**
   * Gets the config dependencies of this plugin definition.
   *
   * @return array
   *   An array of config dependencies.
   *
   * @see \Drupal\Core\Plugin\PluginDependencyTrait::calculatePluginDependencies()
   */
  public function getConfigDependencies();

  /**
   * Sets the config dependencies of this plugin definition.
   *
   * @param array $config_dependencies
   *   An array of config dependencies.
   *
   * @return $this
   */
  public function setConfigDependencies(array $config_dependencies);

}
