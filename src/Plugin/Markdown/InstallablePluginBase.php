<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\DiffArray;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Plugin\PluginBase as CoreBasePlugin;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\markdown\Annotation\InstallableLibrary;
use Drupal\markdown\BcSupport\ObjectWithPluginCollectionInterface;
use Drupal\markdown\Config\ImmutableMarkdownConfig;
use Drupal\markdown\Traits\MoreInfoTrait;
use Drupal\markdown\Util\FilterAwareInterface;
use Drupal\markdown\Util\FilterFormatAwareInterface;
use Drupal\markdown\Util\ParserAwareInterface;
use Drupal\markdown\Util\SortArray;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for installable plugins.
 *
 * @property \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition
 * @method \Drupal\markdown\Annotation\InstallablePlugin getPluginDefinition()
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
abstract class InstallablePluginBase extends CoreBasePlugin implements InstallablePluginInterface {

  use ContainerAwareTrait;
  use MoreInfoTrait;
  use PluginDependencyTrait {
    getPluginDependencies as getPluginDependenciesTrait;
  }

  /**
   * The config for this plugin.
   *
   * @var \Drupal\markdown\Config\ImmutableMarkdownConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
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
  public function buildLibrary(InstallableLibrary $library = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildStatus() {
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'class' => [
          'installable-libraries',
        ],
      ],
    ];
    $build['library'] = [
      '#theme' => 'installable_library',
      '#plugin' => $this,
      '#library' => $this->getInstalledLibrary() ?: $this->getPreferredLibrary(),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSupportedLibraries() {
    $build = [];

    $libraries = $this->pluginDefinition->libraries;
    if ($libraryCount = count($libraries)) {
      $build = [
        '#type' => 'fieldset',
        '#open' => TRUE,
        '#title' => $this->t('Supported Libraries'),
      ];
      $build['libraries'] = [
        '#theme' => 'item_list__markdown__supported_libraries',
        '#attributes' => [
          'class' => ['markdown', 'list-unstyled'],
        ],
        '#items' => [],
      ];
      foreach ($libraries as $library) {
        // Skip libraries that shouldn't be displayed in the UI.
        if (!$library->ui) {
          continue;
        }
        $suffix = [];
        if ($libraryCount > 1 && $library->preferred) {
          $suffix[] = $this->t('preferred');
        }

        $requirements = [];
        foreach ($library->requirements as $requirement) {
          $buildRequirement = '';
          switch ($requirement->getType()) {
            case 'extension':
            case 'parser':
              $object = $requirement->getObject();
              $buildRequirement = $object && $object instanceof InstallablePluginInterface ? $object->getLabel(FALSE) : $requirement->getId();
              $constraints = [];
              foreach ($requirement->constraints as $name => $value) {
                if ($value) {
                  $constraints[] .= "$name: " . (is_array($value) ? implode(', ', $value) : $value);
                }
                else {
                  $constraints[] = $name;
                }
              }
              if ($constraints) {
                $buildRequirement .= ' ' . implode(', ', $constraints);
              }
              break;
          }
          if ($buildRequirement) {
            $requirements[] = $buildRequirement;
          }
        }

        if ($url = $this->pluginDefinition->url) {
          if (UrlHelper::isExternal($url)) {
            $options['attributes']['target'] = '_blank';
            $url = Url::fromUri($url)->setOptions($options);
          }
          else {
            $url = Url::fromUserInput($url);
          }
        }

        if ($installCommand = $library->getInstallCommand()) {
          $item = [
            '#type' => 'html_tag',
            '#tag' => 'kbd',
            '#attributes' => ['class' => ['markdown']],
            '#value' => $installCommand,
            '#url' => $url,
            '#suffix' => $this->moreInfo(NULL, $url),
          ];
        }
        else {
          $item = [
            '#type' => 'link',
            '#title' => $library->label ?: $url->toString(),
            '#url' => $url,
          ];
        }

        $item['#suffix'] = new FormattableMarkup('<small class="markdown-description markdown-no-select">@existing @suffix</small>', [
          '@existing' => !empty($item['#suffix']) ? $item['#suffix'] : '',
          '@suffix' => $suffix ? Markup::create(' (' . implode(', ', $suffix) . ')') : '',
        ]);

        $build['libraries']['#items'][] = ['data' => $item];

        $build['requirements'] = [
          '#theme' => 'item_list__markdown__supported_libraries__requirements',
          '#attributes' => [
            'class' => ['markdown'],
          ],
          '#items' => $requirements,
        ];
      }
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
   * Retrieves the config class used to construct settings.
   *
   * @return string
   *   The config class to use.
   */
  protected function getConfigClass() {
    return ImmutableMarkdownConfig::class;
  }

  /**
   * Retrieves the config type used to construct settings.
   *
   * @return string
   *   The config type to use.
   */
  abstract protected function getConfigType();

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration['id'] = $this->getPluginId();
    $configuration['dependencies'] = $this->getPluginDependencies($this);
    $configuration['weight'] = $this->getWeight();
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
   * {@inheritdoc}
   */
  public function getConfigurationOverrides(array $configuration = NULL) {
    if (!isset($configuration)) {
      $configuration = $this->configuration;
    }
    return DiffArray::diffAssocRecursive($configuration, $this->defaultConfiguration());
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
  public function getDescription() {
    return $this->pluginDefinition->description;
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
   * @TODO: Refactor to use variadic parameters.
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
    if ($version && ($version = $this->getVersion())) {
      $label .= " ({$version})";
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
  public function getProvider() {
    return $this->pluginDefinition->getProvider();
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
  public function getWeight() {
    $weight = $this->config->get('weight');
    return isset($weight) ? (int) $weight : $this->pluginDefinition->weight;
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
    if (($class = $this->getConfigClass()) && ltrim($class, '\\') !== ImmutableMarkdownConfig::class && !is_subclass_of($class, ImmutableMarkdownConfig::class)) {
      throw new \RuntimeException(sprintf('The class %s must be an instance of %s.', $class, ImmutableMarkdownConfig::class));
    }

    // Filter out NULL values, they will be provided by default configuration.
    $configuration = array_filter($configuration, function ($value) {
      return $value !== NULL;
    });

    // Determine the default configuration for the plugin.
    $defaultConfiguration = $this->defaultConfiguration();

    // Merge defaults using union into passed configuration. This ensures that
    // the difference in overrides detected below are not different if they
    // weren't explicitly passed.
    // @todo This should be a nested union merge.
    $configuration += $defaultConfiguration;

    // Generate a new Config object.
    $this->config = $class::create($this->getContainer(), $this->getConfigType(), $defaultConfiguration);

    // Determine if there any configuration overrides.
    if ($overrides = $this->getConfigurationOverrides($configuration)) {
      $this->config->setModuleOverride($overrides);
    }

    // Set all the config data as the property on the plugin.
    $this->configuration = $this->config->get();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function showInUi() {
    return $this->pluginDefinition->ui;
  }


}
