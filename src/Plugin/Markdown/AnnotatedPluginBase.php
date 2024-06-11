<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Plugin\PluginBase as CoreBasePlugin;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for annotated plugins.
 *
 * @property \Drupal\markdown\Annotation\AnnotationObject $pluginDefinition
 * @method \Drupal\markdown\Annotation\AnnotationObject getPluginDefinition()
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
abstract class AnnotatedPluginBase extends CoreBasePlugin implements AnnotatedPluginInterface {

  use ContainerAwareTrait;

  /**
   * The original plugin_id that was called, not a fallback identifier.
   *
   * @var string
   */
  protected $originalPluginId;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->originalPluginId = isset($configuration['original_plugin_id']) ? $configuration['original_plugin_id'] : $plugin_id;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->setContainer($container);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationOverrides(array $configuration = NULL) {
    if (!isset($configuration)) {
      $configuration = $this->configuration;
    }
    return DiffArray::diffAssocRecursive($configuration, $this->defaultConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration['id'] = $this->getPluginId();
    $configuration['weight'] = $this->getWeight();
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition->label ?: $this->pluginDefinition->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getProvider() {
    return $this->pluginDefinition->getProvider();
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalPluginId() {
    return $this->originalPluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return isset($this->configuration['weight']) ? (int) $this->configuration['weight'] : $this->pluginDefinition->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Filter out NULL values, they will be provided by default configuration.
    $configuration = array_filter($configuration, function ($value) {
      return $value !== NULL;
    });

    $this->configuration = $configuration + $this->defaultConfiguration();
  }

}
