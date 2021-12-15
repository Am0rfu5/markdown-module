<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Url;
use Drupal\markdown\Annotation\InstallableLibrary;
use Drupal\markdown\BcSupport\ObjectWithPluginCollectionInterface;
use Drupal\markdown\BcSupport\PluginDependencyTrait;
use Drupal\markdown\Exception\MissingVersionException;
use Drupal\markdown\Traits\MoreInfoTrait;
use Drupal\markdown\Util\FilterAwareInterface;
use Drupal\markdown\Util\FilterFormatAwareInterface;
use Drupal\markdown\Util\ParserAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for installable plugins.
 *
 * @property \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition
 * @method \Drupal\markdown\Annotation\InstallablePlugin getPluginDefinition()
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
abstract class InstallablePluginBase extends AnnotatedPluginBase implements InstallablePluginInterface {

  use MoreInfoTrait;
  use PluginDependencyTrait {
    getPluginDependencies as getPluginDependenciesTrait;
  }

  /**
   * The config for this plugin.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public function buildLibrary(InstallableLibrary $library = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildStatus($all = FALSE) {
    $build = [
      '#theme_wrappers' => ['container__installable_libraries'],
      '#attributes' => [
        'class' => [
          'installable-libraries',
        ],
      ],
    ];
    $libraries = $all ? $this->pluginDefinition->libraries : [$this->getInstalledLibrary() ?: $this->getPreferredLibrary()];
    foreach ($libraries as $library) {
      $build[] = [
        '#theme' => 'installable_library',
        '#plugin' => $this,
        '#library' => $library,
      ];
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration['id'] = $this->getPluginId();
    $configuration['weight'] = $this->pluginDefinition->weight;
    if ($this instanceof EnabledPluginInterface) {
      $configuration['enabled'] = $this->enabledByDefault();
    }
    if ($this instanceof SettingsInterface) {
      $pluginDefinition = $this->getPluginDefinition();
      $settings = isset($pluginDefinition['settings']) ? $pluginDefinition['settings'] : [];
      $configuration['settings'] = NestedArray::mergeDeep($settings, static::defaultSettings($pluginDefinition));
    }
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = [];
    if ($this instanceof ObjectWithPluginCollectionInterface) {
      foreach ($this->getPluginCollections() as $pluginCollection) {
        foreach ($pluginCollection as $instance) {
          $dependencies = array_map('array_unique', NestedArray::mergeDeep($dependencies, $this->getPluginDependencies($instance)));
        }
      }
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function config() {
    return $this->config;
  }

  /**
   * Retrieves available installs.
   *
   * @return \Drupal\markdown\Plugin\Markdown\InstallablePluginInterface[]
   */
  public function getAvailableInstalls() {
    $availableInstalls = [];
    foreach ($this->pluginDefinition->libraries as $library) {
      $definition = (clone $this->pluginDefinition);
      $definition->merge($library);
      $definition->libraries = [];
      $availableInstall = new static($this->configuration, $this->pluginId, $definition);
      if ($this instanceof ParserAwareInterface && $availableInstall instanceof ParserAwareInterface) {
        $availableInstall->setParser($this->getParser());
      }
      if ($this instanceof FilterAwareInterface && $availableInstall instanceof FilterAwareInterface) {
        $availableInstall->setFilter($this->getFilter());
      }
      if ($this instanceof FilterFormatAwareInterface && $availableInstall instanceof FilterFormatAwareInterface) {
        $availableInstall->setFilterFormat($this->getFilterFormat());
      }
      $availableInstalls[] = $availableInstall;
    }
    return $availableInstalls;
  }

  /**
   * Returns the configuration name for the plugin.
   *
   * @return string
   *   The configuration name.
   */
  protected function getConfigurationName() {
    return sprintf('installable.plugin.%s_%s', $this->getProvider(), $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();
    $configuration['dependencies'] = $this->getPluginDependencies($this);

    if ($this instanceof EnabledPluginInterface) {
      $configuration['enabled'] = $this->isEnabled();
    }

    if ($this instanceof SettingsInterface) {
      // Only return settings that have changed from the default values.
      $configuration['settings'] = $this->getSettingOverrides();
    }

    return $configuration;
  }

  /**
   * Determines the configuration sort order by weight.
   *
   * @return int[]
   *   An array of weights, keyed by top level configuration property names.
   */
  protected function getConfigurationSortOrder() {
    $order = [
      'dependencies' => -100,
      'id' => -50,
      'weight' => -30,
    ];
    if ($this instanceof EnabledPluginInterface) {
      $order['enabled'] = -20;
    }
    if ($this instanceof SettingsInterface) {
      $order['settings'] = -10;
    }
    return $order;
  }

  /**
   * Retrieves the container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container.
   */
  public function getContainer() {
    return $this->container instanceof ContainerInterface ? $this->container : \Drupal::getContainer();
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecated() {
    return $this->pluginDefinition->deprecated;
  }

  /**
   * {@inheritdoc}
   */
  public function getExperimental() {
    return $this->pluginDefinition->experimental;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstalledId() {
    return $this->pluginDefinition->getInstalledId();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstalledLibrary() {
    return $this->pluginDefinition->getInstalledLibrary();
  }

  /**
   * {@inheritdoc}
   *
   * @todo Refactor to use variadic parameters.
   */
  public function getObject($args = NULL, $_ = NULL) {
    if ($class = $this->getObjectClass()) {
      $ref = new \ReflectionClass($class);
      return $ref->newInstanceArgs(func_get_args());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectClass() {
    return $this->pluginDefinition->object;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel($version = TRUE) {
    $label = $this->pluginDefinition->label ?: $this->pluginDefinition->getId();
    try {
      if ($version && ($version = $this->getVersion())) {
        $label .= " ({$version})";
      }
    }
    catch (MissingVersionException $exception) {
      // Intentionally do nothing.
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getLink($label = NULL, array $options = [], $fallbackToLabel = TRUE) {
    return $this->pluginDefinition->getLink($label, $options, $fallbackToLabel);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginDependencies(PluginInspectionInterface $instance) {
    return array_map('array_unique', $this->getPluginDependenciesTrait($instance));
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredLibrary() {
    return $this->pluginDefinition->getPreferredLibrary();
  }

  /**
   * {@inheritdoc}
   */
  public function getSortedConfiguration() {
    $configuration = $this->getConfiguration();
    $weights = $this->getConfigurationSortOrder() + array_fill_keys(array_keys($configuration), 0);
    uksort($configuration, function ($a, $b) use ($weights) {
      $a = isset($weights[$a]) ? (int) $weights[$a] : 0;
      $b = isset($weights[$b]) ? (int) $weights[$b] : 0;
      if ($a === $b) {
        return 0;
      }
      return $a < $b ? -1 : 1;
    });
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(array $options = []) {
    if ($url = $this->pluginDefinition->url) {
      if (UrlHelper::isExternal($url)) {
        $options['attributes']['target'] = '_blank';
        return Url::fromUri($url)->setOptions($options);
      }
      return Url::fromUserInput($url)->setOptions($options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion() {
    if (!$this->pluginDefinition->version) {
      throw new MissingVersionException(sprintf('The library "%s" did not not specify a version. If the plugin has no version, it must be explicitly set to "0.0.0".', $this->getPluginId()));
    }
    return $this->pluginDefinition->version;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersionConstraint() {
    if ($versionRequirement = current($this->pluginDefinition->getRequirementsByConstraint('Version'))) {
      return $versionRequirement->constraints['Version'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasMultipleLibraries() {
    return count($this->pluginDefinition->libraries) > 1;
  }

  /**
   * {@inheritdoc}
   */
  public function isInstalled() {
    return $this->pluginDefinition->isInstalled();
  }

  /**
   * {@inheritdoc}
   */
  public function isPreferred() {
    return $this->pluginDefinition->preferred;
  }

  /**
   * {@inheritdoc}
   */
  public function isPreferredLibraryInstalled() {
    return $this->pluginDefinition->isPreferredLibraryInstalled();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Filter out NULL values, they will be provided by default configuration.
    $configuration = array_filter($configuration, function ($value) {
      return $value !== NULL;
    });

    // Determine the default configuration for the plugin.
    $defaultConfiguration = $this->defaultConfiguration();

    // Generate a new Config object.
    $this->config = static::createConfig($this->getConfigurationName(), $defaultConfiguration, TRUE, $this->getContainer());

    // Determine if there any configuration overrides. Merge defaults using
    // a union with passed configuration. This ensures that the difference in
    // overrides detected below are not different if they weren't explicitly
    // passed.
    // @todo This should be a nested union merge.
    if ($overrides = $this->getConfigurationOverrides($configuration + $defaultConfiguration)) {
      $this->config->setModuleOverride($overrides);
    }

    // Set all the config data as the property on the plugin.
    parent::setConfiguration($this->config->get());
  }

  protected static function createConfig($name, array $data = [], $immutable = TRUE, ContainerInterface $container = NULL) {
    $class = $immutable ? ImmutableConfig::class : Config::class;
    if (!$container) {
      $container = \Drupal::getContainer();
    }
    $config = new $class($name,
      $container->get('config.storage'),
      $container->get('event_dispatcher'),
      $container->get('config.typed')
    );
    $config->initWithData($data);
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function showInUi() {
    return $this->pluginDefinition->ui;
  }

}
