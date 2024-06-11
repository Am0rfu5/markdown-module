<?php

namespace Drupal\markdown\PluginManager;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterPluginManager;
use Drupal\markdown\Annotation\InstallablePlugin;
use Drupal\markdown\Annotation\MarkdownAllowedHtml;
use Drupal\markdown\Annotation\InstallableRequirement;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\ExtensibleParserInterface;
use Drupal\markdown\Plugin\Markdown\ExtensionInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\Util\FilterAwareInterface;
use Drupal\markdown\Util\FilterFormatAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Markdown Allowed HTML Plugin Manager.
 *
 * @method \Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface createInstance($plugin_id, array $configuration = [])
 * @method \Drupal\markdown\Annotation\MarkdownAllowedHtml getDefinition($plugin_id, $exception_on_invalid = TRUE)
 * @method \Drupal\markdown\Annotation\MarkdownAllowedHtml|void getDefinitionByClassName($className)
 * @method \Drupal\markdown\Annotation\MarkdownAllowedHtml[] getDefinitions($includeFallback = TRUE)
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */
class AllowedHtmlManager extends InstallablePluginManager {

  /**
   * The Markdown Extension Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\ExtensionManagerInterface
   */
  protected $extensionManager;

  /**
   * The Filter Plugin Manager service.
   *
   * @var \Drupal\filter\FilterPluginManager
   */
  protected $filterManager;

  /**
   * The Markdown Parser Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\ParserManagerInterface
   */
  protected $parserManager;

  /**
   * The Theme Handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface|string
   */
  protected $themeHandler;

  /**
   * The Theme Manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ConfigFactoryInterface $configFactory, LoggerInterface $logger, ModuleHandlerInterface $module_handler, FilterPluginManager $filterManager, ThemeHandlerInterface $themeHandler, ThemeManagerInterface $themeManager, ParserManagerInterface $parserManager, ExtensionManagerInterface $extensionManager) {
    // Add in theme namespaces.
    // @todo Fix when theme namespaces are properly registered.
    // @see https://www.drupal.org/project/drupal/issues/2941757
    $namespaces = iterator_to_array($namespaces);
    foreach ($themeHandler->listInfo() as $extension) {
      $namespaces['Drupal\\' . $extension->getName()] = [DRUPAL_ROOT . '/' . $extension->getPath() . '/src'];
    }
    parent::__construct('Plugin/Markdown', new \ArrayObject($namespaces), $configFactory, $logger, $module_handler, AllowedHtmlInterface::class, MarkdownAllowedHtml::class);
    $this->setCacheBackend($cache_backend, 'markdown_allowed_html_info');
    $this->alterInfo($this->cacheKey);
    $this->filterManager = $filterManager;
    $this->themeHandler = $themeHandler;
    $this->themeManager = $themeManager;
    $this->parserManager = $parserManager;
    $this->extensionManager = $extensionManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container = NULL) {
    if (!$container) {
      $container = \Drupal::getContainer();
    }
    $instance = new static(
      $container->get('container.namespaces'),
      $container->get('cache.discovery'),
      $container->get('config.factory'),
      $container->get('logger.channel.markdown'),
      $container->get('module_handler'),
      $container->get('plugin.manager.filter'),
      $container->get('theme_handler'),
      $container->get('theme.manager'),
      $container->get('plugin.manager.markdown.parser'),
      $container->get('plugin.manager.markdown.extension')
    );
    $instance->setContainer($container);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions, $runtime = FALSE) {
    foreach ($definitions as $definition) {
      if ($definition instanceof InstallablePlugin) {
        $this->alterDefinition($definition, $runtime);
      }
    }
    if ($hook = $this->alterHook) {
      if ($runtime) {
        $hook = "_runtime";
      }
      $this->moduleHandler->alter($hook, $definitions);
      $this->themeManager->alter($hook, $definitions);
    }
  }

  /**
   * Retrieves plugins that apply to a parser and active theme.
   *
   * Note: this is primarily for use when actually parsing markdown.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   A markdown parser.
   * @param \Drupal\Core\Theme\ActiveTheme $activeTheme
   *   Optional. The active them. This is used as an indicator when in
   *   "render mode".
   * @param array $definitions
   *   Optional. Specific plugin definitions.
   *
   * @return \Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface[]
   *   Plugins that apply to the $parser.
   */
  public function appliesTo(ParserInterface $parser, ActiveTheme $activeTheme = NULL, array $definitions = NULL) {
    $instances = [];
    foreach ($this->getGroupedDefinitions($definitions) as $group => $groupDefinitions) {
      // Filter group definitions based on enabled status of the parser when
      // an active theme has been provided.
      if ($activeTheme) {
        $groupDefinitions = array_intersect_key($groupDefinitions, array_filter($parser->getAllowedHtmlPlugins()));
      }

      switch ($group) {
        case 'extension':
          $groupDefinitions = $this->getExtensionDefinitions($parser, $groupDefinitions, $activeTheme);
          break;

        case 'filter':
          $filter = $parser instanceof FilterAwareInterface ? $parser->getFilter() : NULL;
          $filterFormat = $filter instanceof FilterFormatAwareInterface ? $filter->getFilterFormat() : NULL;
          $groupDefinitions = $this->getFilterDefinitions($filterFormat, $groupDefinitions, $activeTheme);
          break;

        case 'parser':
          $groupDefinitions = $this->getParserDefinitions($parser, $groupDefinitions, $activeTheme);
          break;

        case 'theme':
          // If an active theme was provided, then filter out the theme
          // based plugins that are supported by the active theme.
          if ($activeTheme) {
            $groupDefinitions = $this->getThemeDefinitions($groupDefinitions, $activeTheme);
          }
          break;
      }
      foreach (array_keys($groupDefinitions) as $plugin_id) {
        try {
          $instances[$plugin_id] = $this->createInstance($plugin_id, [
            'activeTheme' => $activeTheme,
            'parser' => $parser,
          ]);
        }
        catch (PluginException $e) {
          // Intentionally do nothing.
        }
      }
    }
    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id = NULL, array $configuration = []) {
    return $plugin_id;
  }

  /**
   * Retrieves definitions supported by parser extensions.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   A parser.
   * @param array $definitions
   *   Optional. Specific definitions to filter, if not provided then all
   *   plugins with an "extension" type will be filtered.
   * @param \Drupal\Core\Theme\ActiveTheme $activeTheme
   *   Optional. The active them. This is used as an indicator when in
   *   "render mode".
   *
   * @return array
   *   A filtered list of definitions supported by parser extensions.
   */
  public function getExtensionDefinitions(ParserInterface $parser, array $definitions = NULL, ActiveTheme $activeTheme = NULL) {
    // Immediately return if parser isn't extensible.
    if (!($parser instanceof ExtensibleParserInterface)) {
      return [];
    }
    $definitions = isset($definitions) ? $definitions : $this->getType('extension');

    // Extension only applies to parser when it's supported by it.
    foreach ($definitions as $plugin_id => $definition) {
      $class = static::normalizeClassName($definition->getClass());
      foreach ($parser->extensionInterfaces() as $interface) {
        if (is_subclass_of($class, static::normalizeClassName($interface))) {
          continue 2;
        }
      }
      unset($definitions[$plugin_id]);
    }

    return $definitions;
  }

  /**
   * Retrieves definitions required by filters.
   *
   * @param \Drupal\filter\Entity\FilterFormat $filterFormat
   *   A filter format.
   * @param array $definitions
   *   Optional. Specific definitions to filter, if not provided then all
   *   plugins with a "filter" type will be filtered.
   * @param \Drupal\Core\Theme\ActiveTheme $activeTheme
   *   Optional. The active them. This is used as an indicator when in
   *   "render mode".
   *
   * @return array
   *   A filtered list of definitions supported by the required filter.
   */
  public function getFilterDefinitions(FilterFormat $filterFormat = NULL, array $definitions = NULL, ActiveTheme $activeTheme = NULL) {
    // Immediately return if no filter format was provided.
    if (!$filterFormat) {
      return [];
    }
    /** @var \Drupal\markdown\Annotation\MarkdownAllowedHtml[] $definitions */
    $definitions = isset($definitions) ? $definitions : $this->getType('filter', $definitions);
    $filters = $filterFormat->filters();
    foreach ($definitions as $plugin_id => $definition) {
      // Remove definitions if:
      // 1. Doesn't have "requiresFilter" set.
      // 2. Filter specified by "requiresFilter" doesn't exist.
      // 3. Filter specified by "requiresFilter" isn't actually being used
      //    (status/enabled) during time of render (ActiveTheme).
      if (!$definition->getRequirementsByType('filter', $plugin_id) || !$filters->has($plugin_id) || ($activeTheme && !$filters->get($plugin_id)->status)) {
        unset($definitions[$plugin_id]);
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupedDefinitions(array $definitions = NULL, $label_key = 'label') {
    $definitions = $this->getSortedDefinitions(isset($definitions) ? $definitions : $this->installedDefinitions(), $label_key);
    $grouped_definitions = [];
    foreach ($definitions as $id => $definition) {
      $grouped_definitions[(string) $definition['type']][$id] = $definition;
    }
    return $grouped_definitions;
  }

  /**
   * Retrieves the definition provided by the parser.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   A parser.
   * @param array $definitions
   *   Optional. Specific definitions to filter, if not provided then all
   *   plugins with an "extension" type will be filtered.
   * @param \Drupal\Core\Theme\ActiveTheme $activeTheme
   *   Optional. The active them. This is used as an indicator when in
   *   "render mode".
   *
   * @return array
   *   A filtered list of definitions provided by the parser.
   */
  public function getParserDefinitions(ParserInterface $parser, array $definitions = NULL, ActiveTheme $activeTheme = NULL) {
    $definitions = isset($definitions) ? $definitions : $this->getType('parser');
    $parserClass = static::normalizeClassName(get_class($parser));
    foreach ($definitions as $plugin_id => $definition) {
      $class = static::normalizeClassName($definition->getClass());
      if ($parserClass !== $class && !is_subclass_of($parser, $class)) {
        unset($definitions[$plugin_id]);
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortedDefinitions(array $definitions = NULL, $label_key = 'label') {
    // Sort the plugins first by type, then by label.
    $definitions = isset($definitions) ? $definitions : $this->installedDefinitions();
    uasort($definitions, function ($a, $b) use ($label_key) {
      if ($a['type'] != $b['type']) {
        return strnatcasecmp($a['type'], $b['type']);
      }
      return strnatcasecmp($a[$label_key], $b[$label_key]);
    });
    return $definitions;
  }

  /**
   * Retrieves definitions supported by the active theme.
   *
   * @param array $definitions
   *   Optional. Specific definitions to filter, if not provided then all
   *   plugins with a "theme" type will be filtered.
   * @param \Drupal\Core\Theme\ActiveTheme $activeTheme
   *   Optional. The active them. This is used as an indicator when in
   *   "render mode".
   *
   * @return array
   *   A filtered list of definitions supported by the active theme.
   */
  public function getThemeDefinitions(array $definitions = NULL, ActiveTheme $activeTheme = NULL) {
    $definitions = isset($definitions) ? $definitions : $this->getType('theme');

    // Only use definitions found in the active theme or its base theme(s).
    if ($activeTheme) {
      $themeAncestry = array_merge(array_keys($activeTheme->getBaseThemeExtensions()), [$activeTheme->getName()]);
      foreach ($definitions as $plugin_id => $definition) {
        if (($provider = $definition->getProvider()) && $this->themeHandler->themeExists($provider) && !in_array($provider, $themeAncestry, TRUE)) {
          unset($definitions[$plugin_id]);
        }
      }
    }

    return $definitions;
  }

  /**
   * Retrieves plugins matching a specific type.
   *
   * @param string $type
   *   The type to retrieve.
   * @param array[]|null $definitions
   *   (optional) The plugin definitions to group. If omitted, all plugin
   *   definitions are used.
   *
   * @return array[]
   *   Keys are type names, and values are arrays of which the keys are
   *   plugin IDs and the values are plugin definitions.
   */
  protected function getType($type, array $definitions = NULL) {
    $grouped_definitions = $this->getGroupedDefinitions($definitions);
    return isset($grouped_definitions[$type]) ? $grouped_definitions[$type] : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getProviderName($provider) {
    if ($this->moduleHandler->moduleExists($provider)) {
      return $this->moduleHandler->getName($provider);
    }
    if ($this->themeHandler->themeExists($provider)) {
      return $this->themeHandler->getName($provider);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinition(InstallablePlugin $definition, $runtime = FALSE) {
    // Immediately return if not altering the runtime definition.
    if (!$runtime) {
      return;
    }

    /** @var \Drupal\markdown\Annotation\MarkdownAllowedHtml $definition */
    switch ($definition->type) {
      case 'extension':
        if (($extensionRequirement = current($definition->getRequirementsByType('extension'))) && ($extensionDefinition = $this->extensionManager->getDefinition($extensionRequirement->getTypeId()))) {
          $definition->merge($extensionDefinition, ['ui']);
        }
        break;

      case 'filter':
        if (($filterRequirement = current($definition->getRequirementsByType('filter'))) && ($filterDefinition = $this->filterManager->getDefinition($filterRequirement->getTypeId()))) {
          $definition->merge($filterDefinition, ['ui']);
          if (empty($definition->label) && !empty($filterDefinition['title'])) {
            $definition->label = $filterDefinition['title'];
          }
        }
        break;

      case 'parser':
        if (($parserRequirement = current($definition->getRequirementsByType('parser'))) && ($parserDefinition = $this->parserManager->getDefinition($parserRequirement->getTypeId()))) {
          $definition->merge($parserDefinition, ['ui']);
        }
        break;
    }

    // Provide a default label if none was provided.
    if (empty($definition->label)) {
      // Use the provider name if plugin identifier is the same.
      if ($definition->id === $definition->provider) {
        $definition['label'] = $this->getProviderName($definition->provider);
      }
      // Otherwise, create a human readable label from plugin identifier,
      // if not an extension.
      elseif ($definition->type !== 'extension') {
        $definition['label'] = ucwords(trim(str_replace(['_', '-'], ' ', $definition->id)));
      }
    }

    // Prefix label with provider (if not the same).
    if (in_array($definition->type, ['filter', 'module', 'theme']) && $definition->id !== $definition->provider) {
      $definition['label'] = $this->getProviderName($definition->provider) . ': ' . $definition['label'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    if (!($definition instanceof MarkdownAllowedHtml) || !($class = $definition->getClass()) || $definition->requirementViolations) {
      return;
    }

    // Handle deprecated "requiresFilter" property.
    // @todo Deprecated property, remove before 3.0.0.
    if ($filter = $definition->requiresFilter) {
      if (!$definition->getRequirementsByType('filter', $filter)) {
        $requirement = InstallableRequirement::create();
        $requirement->id = "filter:$filter";
        $definition->requirements[] = $requirement;
      }
      unset($definition->requiresFilter);
    }

    // Certain types need to be determined prior to parent method processing.
    if (!isset($definition->type)) {
      $definition->type = 'other';

      // Allow dependencies on filters.
      if ($definition->getRequirementsByType('filter')) {
        $definition->type = 'filter';
      }
      // Allow parsers to provide their own allowed HTML.
      elseif (is_subclass_of($class, ParserInterface::class)) {
        $definition->type = 'parser';
        if ($parserDefinition = $this->parserManager->getDefinitionByClassName($class)) {
          $requirement = InstallableRequirement::create();
          $requirement->id = 'parser:' . $parserDefinition->id;
          $definition->requirements[] = $requirement;
        }
      }
      // Allow extensions to provide their own allowed HTML.
      elseif (is_subclass_of($class, ExtensionInterface::class)) {
        $definition->type = 'extension';
        if ($extensionDefinition = $this->extensionManager->getDefinitionByClassName($class)) {
          $requirement = InstallableRequirement::create();
          $requirement->id = 'extension:' . $extensionDefinition->id;
          $definition->requirements[] = $requirement;
        }
      }
      // Otherwise, determine the extension type and set it as the "type".
      elseif ($this->moduleHandler->moduleExists($definition->provider)) {
        $definition->type = 'module';
      }
      elseif ($this->themeHandler->themeExists($plugin_id)) {
        $definition->type = 'theme';
      }
    }

    parent::processDefinition($definition, $plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists($provider) {
    return $this->moduleHandler->moduleExists($provider) || $this->themeHandler->themeExists($provider);
  }

}
