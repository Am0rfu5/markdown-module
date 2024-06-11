<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\markdown\PluginManager\ExtensionCollection;
use Drupal\markdown\Util\SortArray;

/**
 * Base class for extensible markdown parsers.
 *
 * @property \Drupal\markdown\Annotation\MarkdownParser $pluginDefinition
 * @method \Drupal\markdown\Annotation\MarkdownParser getPluginDefinition()
 */
abstract class BaseExtensibleParser extends BaseParser implements ExtensibleParserInterface {

  /**
   * The extension configuration.
   *
   * @var array
   */
  protected $extensions = [];

  /**
   * A collection of MarkdownExtension plugins specific to the parser.
   *
   * @var \Drupal\markdown\PluginManager\ExtensionCollection
   */
  protected $extensionCollection;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'extensions' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function extension($extensionId) {
    return $this->extensions()->get($extensionId);
  }

  /**
   * {@inheritdoc}
   */
  public function extensionInterfaces() {
    return isset($this->pluginDefinition['extensionInterfaces']) ? $this->pluginDefinition['extensionInterfaces'] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function extensions() {
    if (!isset($this->extensionCollection)) {
      $this->extensionCollection = new ExtensionCollection($this->getContainer()->get('plugin.manager.markdown.extension'), $this);
    }
    return $this->extensionCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundledExtensionIds() {
    return isset($this->pluginDefinition['bundledExtensions']) ? $this->pluginDefinition['bundledExtensions'] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();

    // Normalize extensions and their settings.
    $extensions = [];
    $extensionCollection = $this->extensions();
    /** @var \Drupal\markdown\Plugin\Markdown\ExtensionInterface $extension */
    foreach ($extensionCollection as $extensionId => $extension) {
      // Only include extensions that have configuration overrides.
      if ($overrides = $extension->getConfigurationOverrides()) {
        $extensionConfiguration = $extension->getSortedConfiguration();

        // This is part of the parser config, the extension dependencies
        // aren't needed as they're determined and merged elsewhere.
        unset($extensionConfiguration['dependencies']);

        $extensions[] = $extensionConfiguration;
      }
    }

    // Sort extensions so they're always in the same order.
    uasort($extensions, function ($a, $b) {
      return SortArray::sortByKeyString($a, $b, 'id');
    });

    // Don't use an associative array, just an indexed list of extensions.
    $configuration['extensions'] = array_values($extensions);

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationSortOrder() {
    return ['extensions' => 100] + parent::getConfigurationSortOrder();
  }

  /**
   * Indicates whether an extension is "required" by another extension.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ExtensionInterface $extension
   *   The extension to check.
   *
   * @return bool
   *   TRUE or FALSE
   */
  protected function isExtensionRequired(ExtensionInterface $extension) {
    // Check whether extension is required by another enabled extension.
    if ($requiredBy = $extension->requiredBy()) {
      foreach ($requiredBy as $dependent) {
        if ($this->extension($dependent)->isEnabled()) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['extensions' => $this->extensions()];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginDependencies(PluginInspectionInterface $instance) {
    // Only include extensions that are enabled or required.
    if (!($instance instanceof ExtensionInterface) || ($instance->isEnabled() || $this->isExtensionRequired($instance))) {
      return parent::getPluginDependencies($instance);
    }
    return [];
  }

  /**
   * Sets the configuration for an extension plugin instance.
   *
   * @param string $extensionId
   *   The identifier of the extension plugin to set the configuration for.
   * @param array $configuration
   *   The extension plugin configuration to set.
   *
   * @return static
   *
   * @todo Actually use this.
   */
  public function setExtensionConfig($extensionId, array $configuration) {
    $this->extensions[$extensionId] = $configuration;
    if (isset($this->extensionCollection)) {
      $this->extensionCollection->setInstanceConfiguration($extensionId, $configuration);
    }
    return $this;
  }

}
