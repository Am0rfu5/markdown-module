<?php

namespace Drupal\markdown;

use Drupal\Component\Discovery\DiscoveryException;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\markdown\Plugin\Markdown\MarkdownInstallablePluginInterface;
use Drupal\markdown\Plugin\Markdown\MarkdownPluginSettingsInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

abstract class BaseMarkdownPluginManager extends DefaultPluginManager implements MarkdownPluginManagerInterface {

  use ContainerAwareTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    $this->sortDefinitions($definitions);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function all(array $configuration = [], $includeBroken = FALSE) {
    return array_map(function (array $definition) use ($configuration) {
      return $this->createInstance($definition['id'], $configuration);
    }, $this->getDefinitions($includeBroken));
  }

  /**
   * {@inheritdoc}
   */
  public function firstInstalledPluginId() {
    return current(array_keys($this->installedDefinitions())) ?: $this->getFallbackPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions($includeBroken = TRUE) {
    $definitions = parent::getDefinitions();
    if ($includeBroken) {
      return $definitions;
    }
    unset($definitions['_broken']);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id = NULL, array $configuration = []) {
    return '_broken';
  }

  /**
   * {@inheritdoc}
   */
  public function installed(array $configuration = []) {
    return array_map(function (array $definition) use ($configuration) {
      return $this->createInstance($definition['id'], $configuration);
    }, $this->installedDefinitions());
  }

  /**
   * {@inheritdoc}
   */
  public function labels($installed = TRUE, $version = TRUE) {
    $labels = [];
    $parsers = $installed ? $this->installed() : $this->all();
    foreach ($parsers as $id => $parser) {
      // Cast to string for Drupal 7.
      $labels[$id] = (string) $parser->getLabel($version);
    }
    return $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function installedDefinitions() {
    return array_filter($this->getDefinitions(FALSE), function (array $definition) {
      return $definition['id'] !== '_broken' && !empty($definition['installed']);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    // Immediately return if plugin is not installable.
    if (!is_array($definition) || !($class = $definition['class'] ?? NULL)) {
      return;
    }

    // Determine if plugin has settings.
    if (is_subclass_of($class, MarkdownPluginSettingsInterface::class)) {
      if (!isset($definition['settings'])) {
        $definition['settings'] = $class::defaultSettings();
      }
    }

    if (is_subclass_of($class, MarkdownInstallablePluginInterface::class)) {
      // Determine if plugin is installed, if not explicitly specified.
      if (!isset($definition['installed'])) {
        $definition['installed'] = $class::installed();
      }
      elseif (is_string($definition['installed'])) {
        $definition['installed'] = class_exists($definition['installed']);
      }
      elseif (!is_bool($definition['installed'])) {
        throw new DiscoveryException('The "installed" property must either be a class name that is checked for existence or a boolean. If complex requirements are needed, use \Drupal\markdown\Plugin\Markdown\MarkdownInstallablePluginInterface::installed() instead of setting the value in the plugin annotation.');
      }

      // Determine if plugin version, if not explicitly specified.
      if (!isset($definition['version'])) {
        $definition['version'] = $class::version();
      }
      elseif (is_string($definition['version'])) {
        if (defined($definition['version'])) {
          $definition['version'] = constant($definition['version']);
        }
        elseif (strpos($definition['version'], '::')) {
          [$class, $const] = explode('::', $definition['version']);
          $definition['version'] = $class::$const;
        }
        elseif (is_callable($definition['version'])) {
          $definition['version'] = $definition['version']();
        }
        else {
          throw new DiscoveryException('The "version" property must either be a constant or public class constant or property that exists in code somewhere. If complex requirements are needed, use \Drupal\markdown\Plugin\Markdown\MarkdownInstallablePluginInterface::version() instead of setting the value in the plugin annotation.');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists($provider) {
    // It's known that plugins provided by this module exist. Explicitly and
    // always return TRUE for this case. This is needed during install when
    // the module is not yet (officially) installed.
    // @see markdown_requirements()
    if ($provider === 'markdown') {
      return TRUE;
    }
    return parent::providerExists($provider);
  }

  /**
   * Sorts a definitions array.
   *
   * This sorts the definitions array first by the weight column, and then by
   * the plugin label, ensuring a stable, deterministic, and testable ordering
   * of plugins.
   *
   * @param array $definitions
   *   The definitions array to sort.
   */
  protected function sortDefinitions(array &$definitions) {
    $weight = array_column($definitions, 'weight', 'id');
    $label = array_map(function ($label) {
      return preg_replace("/[^a-z0-9]/", '', strtolower($label));
    }, array_column($definitions, 'label', 'id'));
    array_multisort($weight, SORT_ASC, SORT_NUMERIC, $label, SORT_ASC, SORT_NATURAL, $definitions);
  }

}
