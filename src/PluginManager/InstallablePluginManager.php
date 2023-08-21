<?php

namespace Drupal\markdown\PluginManager;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\markdown\Annotation\InstallableLibrary;
use Drupal\markdown\Annotation\InstallablePlugin;
use Drupal\markdown\Annotation\InstallableRequirement;
use Drupal\markdown\Exception\MarkdownUnexpectedValueException;
use Drupal\markdown\Traits\NormalizeTrait;
use Drupal\markdown\Util\Composer;
use Drupal\markdown\Util\Error;
use Drupal\markdown\Util\Semver;
use Drupal\markdown\Util\SortArray;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Installable Plugin Manager.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
abstract class InstallablePluginManager extends DefaultPluginManager implements InstallablePluginManagerInterface {

  use ContainerAwareTrait;
  use NormalizeTrait;
  use StringTranslationTrait;

  /**
   * Cache contexts.
   *
   * @var string[]
   */
  protected $cacheContexts = [];

  /**
   * Cache max-age.
   *
   * @var int
   */
  protected $cacheMaxAge = Cache::PERMANENT;

  /**
   * The cached runtime definitions.
   *
   * @var array[]
   */
  protected static $runtimeDefinitions = [];

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct($subDirectory, \Traversable $namespaces, ConfigFactoryInterface $configFactory, LoggerInterface $logger, ModuleHandlerInterface $module_handler, $plugin_interface = NULL, $plugin_definition_annotation_name = 'Drupal\Component\Annotation\Plugin', array $additional_annotation_namespaces = []) {
    parent::__construct($subDirectory, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name, $additional_annotation_namespaces);
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function all(array $configuration = [], $includeFallback = FALSE) {
    $definitions = $this->getDefinitions($includeFallback);

    uasort($definitions, function (InstallablePlugin $a, InstallablePlugin $b) {
      if ($a->weight === $b->weight) {
        return 0;
      }
      return $a->weight < $b->weight ? -1 : 1;
    });

    return array_map(function (InstallablePlugin $definition) use ($configuration) {
      $id = $definition->getId();
      return $this->createInstance($id, isset($configuration[$id]) ? $configuration[$id] : $configuration);
    }, $definitions);
  }

  /**
   * Allows plugin managers to further alter individual definitions.
   *
   * @param \Drupal\markdown\Annotation\InstallablePlugin $definition
   *   The definition being altered.
   * @param bool $runtime
   *   Flag indicating whether this is a runtime alteration.
   */
  protected function alterDefinition(InstallablePlugin $definition, $runtime = FALSE) {
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
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    parent::clearCachedDefinitions();
    static::$runtimeDefinitions = [];
  }

  /**
   * Converts plugin definitions using the old "installed" method to libraries.
   *
   * @param \Drupal\markdown\Annotation\InstallablePlugin $plugin
   *   The definition being processed.
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   There is no replacement.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  protected function convertInstalledToLibraries(InstallablePlugin $plugin) {
    // Immediately return if "installed" isn't set.
    if (empty($installed = $plugin->installed)) {
      return;
    }

    $installs = [];
    foreach ((array) $plugin->installed as $key => $value) {
      $object = NULL;
      if ($value !== TRUE) {
        $object = static::normalizeClassName(is_string($key) && strpos($key,
          '\\') !== FALSE ? $key : $value);
        $installs[$object] = is_array($value) ? $value : [];
      }
    }
    foreach ($installs as $class => $definition) {
      $library = InstallableLibrary::create()->merge($definition);
      $library->object = $class;
      $plugin->libraries[] = $library;
    }
    unset($plugin->installed);

    // Retrieve the first library and merge any standalone properties on
    // the plugin.
    $library = reset($plugin->libraries);

    // Move URL property over to library.
    if (($url = $plugin->url) && !$library->url) {
      $library->url = $url;
      unset($plugin->url);
    }

    // Move version property over to library.
    if (($version = $plugin->version) && !$library->version) {
      $library->version = $version;
      unset($plugin->version);
    }

    // Move/convert versionConstraint into a requirement on library.
    if ($versionConstraint = $plugin->versionConstraint) {
      $requirement = new InstallableRequirement();
      $requirement->constraints['Version'] = [
        'name' => $plugin->id(),
        'constraint' => $versionConstraint,
      ];
      $library->requirements[] = $requirement;
      unset($plugin->versionConstraint);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    /* @noinspection PhpUnhandledExceptionInspection */
    $instance = parent::createInstance($plugin_id, $configuration);
    if ($instance instanceof ContainerAwareInterface) {
      $instance->setContainer($this->getContainer());
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = $this->getDiscovery()->getDefinitions();

    // If this plugin was provided by a Drupal extension that does not exist,
    // remove the plugin definition.
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $definition */
    foreach ($definitions as $plugin_id => $definition) {
      if (($provider = $definition->getProvider()) && !in_array($provider, ['core', 'component']) && !$this->providerExists($provider)) {
        unset($definitions[$plugin_id]);
      }
    }

    foreach ($definitions as $plugin_id => &$definition) {
      $this->processDefinition($definition, $plugin_id);
    }

    $this->alterDefinitions($definitions);

    return $definitions;
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
  public function getCacheContexts() {
    return $this->cacheContexts;
  }

  /**
   * {@inheritdoc}
   *
   * @param bool $runtime
   *   Flag indicating whether to retrieve runtime definitions.
   */
  protected function getCachedDefinitions($runtime = FALSE) {
    $cacheKey = $this->getCacheKey($runtime);
    if ($runtime) {
      if (!isset(static::$runtimeDefinitions[static::class]) && ($cache = $this->cacheGet($cacheKey))) {
        static::$runtimeDefinitions[static::class] = $cache->data;
      }
      return static::$runtimeDefinitions[static::class];
    }
    else {
      if (!isset($this->definitions) && ($cache = $this->cacheGet($cacheKey))) {
        $this->definitions = $cache->data;
      }
      return $this->definitions;
    }
  }

  /**
   * Retrieves the cache key to use.
   *
   * @param bool $runtime
   *   Flag indicating whether to retrieve runtime definitions.
   *
   * @return string
   *   The cache key.
   */
  public function getCacheKey($runtime = FALSE) {
    $cacheKey = $this->cacheKey;
    if ($runtime) {
      // Prematurely requesting the "active theme" causes the wrong theme
      // to be chosen due to the request not yet being fully populated with
      // the correct route object, or any for that matter.
      $request = \Drupal::request();
      if ($request->attributes->has(RouteObjectInterface::ROUTE_OBJECT)) {
        $cacheKey .= ':runtime:' . \Drupal::theme()->getActiveTheme()->getName();
      }
      else {
        $cacheKey .= ':runtime';
      }
    }
    return $cacheKey;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->cacheMaxAge;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->cacheTags;
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
  public function getDefinitionByClassName($className) {
    $className = static::normalizeClassName($className);

    // Don't use array_column() here, PHP versions less than 7.0.0 don't work
    // as expected due to the fact that the definitions are objects.
    foreach ($this->getDefinitions() as $definition) {
      if ($definition->getClass() === $className) {
        return $definition;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionByLibraryId($libraryId) {
    foreach ($this->getDefinitions() as $definition) {
      foreach ($definition->libraries as $library) {
        if ($library->getId() === (string) $libraryId) {
          return $definition;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions($includeFallback = TRUE) {
    $definitions = $this->getRuntimeDefinitions();
    if ($includeFallback) {
      return $definitions;
    }
    unset($definitions[$this->getFallbackPluginId()]);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    // This can not be abstract, but this is the next best thing.
    throw new \BadMethodCallException(get_class() . '::getFallbackPluginId() not implemented.');
  }

  /**
   * Retrieves the runtime definitions.
   *
   * @return \Drupal\markdown\Annotation\InstallablePlugin[]
   *   The runtime definitions.
   *
   * @noinspection PhpDocMissingThrowsInspection
   */
  protected function getRuntimeDefinitions() {
    // Ensure the class has an array key set, defaulted to NULL.
    if (!array_key_exists(static::class, static::$runtimeDefinitions)) {
      static::$runtimeDefinitions[static::class] = NULL;
    }

    // Retrieve cached runtime definitions.
    static::$runtimeDefinitions[static::class] = $this->getCachedDefinitions(TRUE);

    // Build the runtime definitions.
    if (!isset(static::$runtimeDefinitions[static::class])) {
      // Retrieve normal definitions.
      static::$runtimeDefinitions[static::class] = parent::getDefinitions();

      // Validate runtime definition requirements.
      /** @var \Drupal\markdown\Annotation\InstallablePlugin $definition */
      foreach (static::$runtimeDefinitions[static::class] as $definition) {
        $definition->validate(TRUE);
      }

      // Alter runtime definitions.
      $this->alterDefinitions(static::$runtimeDefinitions[static::class], TRUE);

      // Normalize any callbacks provided.
      try {
        static::normalizeCallables(static::$runtimeDefinitions[static::class]);
      }
      catch (MarkdownUnexpectedValueException $exception) {
        $plugin_id = array_reverse($exception->getParents())[0];
        $annotation = array_reverse(explode('\\', $this->pluginDefinitionAnnotationName))[0];
        throw new InvalidPluginDefinitionException($plugin_id, sprintf('Invalid callback defined in @%s. %s.', $annotation, $exception->getMessage()), 0, isset($e) ? $e : NULL);
      }

      // Re-validate runtime definition requirements after alterations.
      /** @var \Drupal\markdown\Annotation\InstallablePlugin $definition */
      foreach (static::$runtimeDefinitions[static::class] as $plugin_id => $definition) {
        $definition->validate(TRUE);
      }

      // Sort the runtime definitions.
      $this->sortDefinitions(static::$runtimeDefinitions[static::class]);

      // Cache the runtime definitions.
      $this->setCachedDefinitions(static::$runtimeDefinitions[static::class], TRUE);
    }

    // Runtime definitions should always be the active definitions.
    $this->definitions = static::$runtimeDefinitions[static::class];

    return $this->definitions;
  }

  /**
   * {@inheritdoc}
   */
  protected function handlePluginNotFound($plugin_id, array $configuration) {
    $fallback_id = $this->getFallbackPluginId($plugin_id, $configuration);
    $configuration['original_plugin_id'] = $plugin_id;
    return $this->getFactory()->createInstance($fallback_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function installed(array $configuration = []) {
    return array_map(function (InstallablePlugin $definition) use ($configuration) {
      $id = $definition->getId();
      return $this->createInstance($id, isset($configuration[$id]) ? $configuration[$id] : $configuration);
    }, $this->installedDefinitions());
  }

  /**
   * {@inheritdoc}
   */
  public function installedDefinitions() {
    return array_filter($this->getDefinitions(FALSE), function ($definition) {
      return $definition->getId() !== $this->getFallbackPluginId() && $definition->isInstalled();
    });
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $pluginId) {
    if (!($definition instanceof InstallablePlugin)) {
      return;
    }

    // Normalize the class.
    $definition->setClass(static::normalizeClassName($definition->getClass()));

    // Convert legacy "installed" property to "libraries".
    // @todo Deprecated functionality, remove before 3.0.0.
    $this->convertInstalledToLibraries($definition);

    // When no libraries or requirements are specified, create a new library
    // from the definition itself and treat it as its own standalone library.
    if (!$definition->libraries && !$definition->requirements && !$definition->runtimeRequirements && !$definition->requirementViolations) {
      $definition->libraries[] = InstallableLibrary::create($definition);
    }

    // Process libraries.
    $preferred = FALSE;
    $preferredWeight = -1;
    $seenIds = [];
    foreach ($definition->libraries as $key => $library) {
      $id = $library->getId();
      if (!isset($seenIds[$id])) {
        $seenIds[$id] = $library;
      }
      else {
        unset($definition->libraries[$key]);
      }
      /* @noinspection PhpUnhandledExceptionInspection */
      $this->processLibraryDefinition($definition, $library, $preferred);
      $preferredWeight = min($preferredWeight, $library->weight);
    }

    // If no library was preferred, default to the first library defined.
    if (!$preferred && ($library = reset($definition->libraries))) {
      $library->preferred = TRUE;
      $library->weight = $preferredWeight;
    }

    // Sort the library definitions.
    $this->sortDefinitions($definition->libraries);

    // Merge in the installed or preferred library into the actual plugin.
    if ($library = $definition->getInstalledLibrary() ?: $definition->getPreferredLibrary()) {
      // Merge library into plugin definition, excluding certain properties.
      $definition->merge($library, ['ui', 'weight']);

      // Set default URL for plugin based on the installed/preferred library.
      if (!$definition->url && ($url = $library->getUrl())) {
        $definition->url = $url->toString();
      }
    }
  }

  protected function createObjectRequirement(InstallablePlugin $definition, InstallableLibrary $library) {
    return $library->createObjectRequirement($definition);
  }

  /**
   * Processes the library definition.
   *
   * @param \Drupal\markdown\Annotation\InstallablePlugin $definition
   *   The plugin definition.
   * @param \Drupal\markdown\Annotation\InstallableLibrary $library
   *   A library definition.
   * @param bool $preferred
   *   A flag indicating whether a library was explicitly set as "preferred",
   *   passed by reference.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function processLibraryDefinition(InstallablePlugin $definition, InstallableLibrary $library, &$preferred = FALSE) {
    if (!$preferred && $library->preferred) {
      $preferred = TRUE;
    }

    // Normalize the object.
    if ($library->object) {
      $library->object = static::normalizeClassName($library->object);

      // Prepend a new runtime requirement to ensure that the object exists
      // before any other requirements are executed. This helps to ensure that
      // if a requirement depends on the object existing, it doesn't fatal and
      // instead treated as "uninstalled".
      if ($requirement = $this->createObjectRequirement($definition, $library)) {
        array_unshift($library->requirements, $requirement);
      }
    }

    // Convert versionConstraint into a requirement.
    // @todo Deprecated property, remove in 3.0.0.
    if ($versionConstraint = $library->versionConstraint) {
      $requirement = new InstallableRequirement();
      $requirement->constraints['Version'] = $versionConstraint;
      $library->requirements[] = $requirement;
      unset($library->versionConstraint);
    }

    $versionDefinition = NULL;

    // If version is populated with a callback or constant, add a requirement
    // that it should exist. Then, if the requirement is met, it will be
    // populated below with the validated value.
    if (!empty($library->version) && is_string($library->version) && !Semver::isValid($library->version)) {
      $versionDefinition = static::normalizeClassName($library->version);
      unset($library->version);
    }

    // Process requirements.
    $versionRequirement = NULL;
    if ($library->requirements) {
      foreach ($library->requirements as $key => $requirement) {
        if ($requirement instanceof InstallableLibrary) {
          $requirement = $requirement->createObjectRequirement();
        }

        // Version constraints that have not explicitly specified a value
        // or callback should be validated against this library's installed
        // version which can only be determined later below; save it.
        if (!isset($requirement->value) && !isset($requirement->callback) && count($requirement->constraints) === 1 && key($requirement->constraints) === 'Version' && !empty($requirement->constraints['Version'])) {
          $versionRequirement = $requirement;
          unset($library->requirements[$key]);
          continue;
        }

        // Move parser and extension requirements to runtime.
        // Note: this helps to prevent recursion while building definitions.
        if (in_array($requirement->getType(), ['parser', 'extension'], TRUE)) {
          $library->runtimeRequirements[] = $requirement;
          unset($library->requirements[$key]);
          continue;
        }

        // Attempt to validate all requirements. Up until the point that any
        // errors or exceptions occur.
        Error::suppress(function () use ($requirement, $library) {
          foreach ($requirement->validate() as $violation) {
            $key = (string) $violation->getMessage();
            if (!isset($library->requirementViolations[$key])) {
              $library->requirementViolations[$key] = $violation->getMessage();
            }
          }
        });
      }
    }

    // Now that requirements have been met, actually extract the version
    // from the definition that was provided.
    if (isset($versionDefinition)) {
      if (!$versionRequirement) {
        $versionRequirement = new InstallableRequirement();
        $versionRequirement->constraints = ['Version' => ['name' => $definition->id()]];
      }
      if (defined($versionDefinition) && ($version = constant($versionDefinition))) {
        $versionRequirement->value = $version;
      }
      elseif (is_callable($versionDefinition) && ($version = call_user_func_array($versionDefinition, [$library, $definition]))) {
        $versionRequirement->value = $version;
      }
      elseif ($library->object && ($version = Composer::getVersionFromClass($library->object))) {
        $versionRequirement->value = $version;
      }

      /** @var \Symfony\Component\Validator\ConstraintViolationListInterface $violations */
      $violations = Error::suppress(function () use ($versionRequirement) {
        return $versionRequirement->validate();
      });

      // Now, validate the version.
      if ($violations && $violations->count()) {
        foreach ($violations as $violation) {
          $key = (string) $violation->getMessage();
          if (!isset($library->requirementViolations[$key])) {
            $library->requirementViolations[$key] = $violation->getMessage();
          }
        }
      }
      elseif ($violations !== NULL) {
        $library->version = $versionRequirement->value;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setCacheBackend(CacheBackendInterface $cache_backend, $cache_key, array $cache_tags = []) {
    $cache_tags[] = $cache_key;
    $cache_tags[] = "$cache_key:runtime";
    /** @var \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler */
    $themeHandler = \Drupal::service('theme_handler');
    foreach (array_keys($themeHandler->listInfo()) as $theme) {
      $cache_tags[] = "$cache_key:runtime:$theme";
    }
    parent::setCacheBackend($cache_backend, $cache_key, array_unique($cache_tags));
  }

  /**
   * Sets a cache of plugin definitions for the decorated discovery class.
   *
   * @param array $definitions
   *   List of definitions to store in cache.
   * @param bool $runtime
   *   Flag indicating whether this is setting runtime definitions.
   */
  protected function setCachedDefinitions($definitions, $runtime = FALSE) { // phpcs:ignore
    $cacheKey = $this->getCacheKey($runtime);
    $this->cacheSet($cacheKey, $definitions, Cache::PERMANENT, [$cacheKey]);
    if ($runtime) {
      static::$runtimeDefinitions[static::class] = $definitions;
    }
    else {
      $this->definitions = $definitions;
    }
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
   * @param array $properties
   *   Optional. The properties to sort by.
   */
  protected function sortDefinitions(array &$definitions, array $properties = ['weight', 'label']) {
    SortArray::multisortProperties($definitions, $properties);
  }

}
