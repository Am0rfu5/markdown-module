<?php

namespace Drupal\markdown\PluginManager;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;
use Drupal\markdown\Plugin\Markdown\ExtensibleParserInterface;
use Drupal\markdown\Util\ParserAwareInterface;

/**
 * Collection of extension plugins based on relevant parser.
 *
 * @property \Drupal\markdown\PluginManager\ExtensionManager $manager
 */
class ExtensionCollection extends DefaultLazyPluginCollection {

  /**
   * The Markdown Parser instance this extension collection belongs to.
   *
   * @var \Drupal\markdown\Plugin\Markdown\ExtensibleParserInterface
   */
  protected $parser;

  /**
   * ExtensionCollection constructor.
   *
   * @param \Drupal\markdown\PluginManager\ExtensionManagerInterface $manager
   *   The Markdown Extension Plugin Manager service.
   * @param \Drupal\markdown\Plugin\Markdown\ExtensibleParserInterface $parser
   *   A markdown parser instance.
   */
  public function __construct(ExtensionManagerInterface $manager, ExtensibleParserInterface $parser) {
    $this->parser = $parser;
    $extensionInterfaces = $parser->extensionInterfaces();

    // Filter out extensions that the parser doesn't support.
    $definitions = array_filter($manager->getDefinitions(FALSE), function ($definition) use ($extensionInterfaces) {
      $supported = FALSE;
      foreach ($extensionInterfaces as $interface) {
        if (is_subclass_of($definition->getClass(), $interface)) {
          $supported = TRUE;
          break;
        }
      }
      return $supported;
    });

    // Process passed configurations with known extension definitions.
    $configurations = $parser->config()->get('extensions') ?: [];
    foreach ($configurations as $key => &$configuration) {
      $originalKey = $key;

      // Ensure the plugin key is set in the configuration.
      if (isset($definitions[$key])) {
        $configuration[$this->pluginKey] = $key;
        continue;
      }

      // Configuration defined a plugin key, use it.
      $key = isset($configuration[$this->pluginKey]) ? $configuration[$this->pluginKey] : NULL;
      if (isset($key)) {
        $configurations[$key] = $configuration;
      }

      // Remove unknown configurations.
      if ($key !== $originalKey) {
        unset($configurations[$originalKey]);
      }
    }

    // Ensure required dependencies are enabled.
    // Note: property is prefixed with an underscore to denote it as internal.
    // @see \Drupal\markdown\PluginManager\ExtensionManager::alterDefinitions
    // @todo Figure out a better way to handle this.
    foreach ($definitions as $pluginId => $definition) {
      if (!empty($definition['_requiredBy'])) {
        foreach ($definition['_requiredBy'] as $dependent) {
          // Ensure dependent is a string.
          $dependent = (string) $dependent;
          if (isset($configurations[$dependent]) && (!isset($configurations[$dependent]['enabled']) || !empty($configurations[$dependent]['enabled']))) {
            if (!isset($configurations[$pluginId])) {
              $configurations[$pluginId] = ['id' => $pluginId];
            }
            $configurations[$pluginId]['enabled'] = TRUE;
            break;
          }
        }
      }
    }

    // Fill in missing definitions.
    $pluginIds = array_keys($definitions);
    $configurations += array_combine($pluginIds, array_map(function ($pluginId) {
      return [$this->pluginKey => $pluginId];
    }, $pluginIds));

    // Sort configurations by using the keys of the already sorted definitions.
    $configurations = array_replace(array_flip(array_keys(array_intersect_key($definitions, $configurations))), $configurations);

    parent::__construct($manager, $configurations);
  }

  /**
   * {@inheritdoc}
   */
  public function addInstanceId($id, $configuration = NULL) {
    // Ensure instance identifier is a string.
    parent::addInstanceId((string) $id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function &get($instance_id) {
    // Ensure instance identifier is a string.
    return parent::get((string) $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function has($instance_id) {
    // Ensure instance identifier is a string.
    return parent::has((string) $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    // Ensure instance identifier is a string.
    $instance_id = (string) $instance_id;
    parent::initializePlugin($instance_id);

    // Associate the parser with the extension.
    $extension = $this->get($instance_id);
    if ($extension instanceof ParserAwareInterface) {
      $extension->setParser($this->parser);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function remove($instance_id) {
    // Ensure instance identifier is a string.
    parent::remove((string) $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function removeInstanceId($instance_id) {
    // Ensure instance identifier is a string.
    parent::removeInstanceId((string) $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function set($instance_id, $value) {
    // Ensure instance identifier is a string.
    parent::set((string) $instance_id, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function sort() {
    // Intentionally do nothing, it's already sorted.
    return $this;
  }

}
