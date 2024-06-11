<?php

namespace Drupal\markdown\PluginManager;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\markdown\Annotation\InstallableLibrary;
use Drupal\markdown\Annotation\InstallablePlugin;
use Drupal\markdown\Annotation\MarkdownExtension;
use Drupal\markdown\Annotation\InstallableRequirement;
use Drupal\markdown\Plugin\Markdown\ExtensionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Markdown Extension Plugin Manager.
 *
 * @method \Drupal\markdown\Plugin\Markdown\ExtensionInterface[] all(array $configuration = [], $includeFallback = FALSE) : array
 * @method \Drupal\markdown\Plugin\Markdown\ExtensionInterface createInstance($plugin_id, array $configuration = [])
 * @method \Drupal\markdown\Annotation\MarkdownExtension getDefinition($plugin_id, $exception_on_invalid = TRUE)
 * @method \Drupal\markdown\Annotation\MarkdownExtension|void getDefinitionByClassName($className)
 * @method \Drupal\markdown\Annotation\MarkdownExtension[] getDefinitions($includeFallback = TRUE)
 * @method \Drupal\markdown\Plugin\Markdown\ExtensionInterface[] installed(array $configuration = []) : array
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */
class ExtensionManager extends InstallablePluginManager implements ExtensionManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ConfigFactoryInterface $configFactory, LoggerInterface $logger, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Markdown', $namespaces, $configFactory, $logger, $module_handler, ExtensionInterface::class, MarkdownExtension::class);
    $this->setCacheBackend($cache_backend, 'markdown_extension_info');
    $this->alterInfo($this->cacheKey);
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
      $container->get('module_handler')
    );
    $instance->setContainer($container);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions, $runtime = FALSE) {
    /** @var \Drupal\markdown\Annotation\MarkdownExtension[] $definitions */

    // Create dependency relationships between extensions.
    // Note: property is prefixed with an underscore to denote it as internal.
    // @see \Drupal\markdown\PluginManager\ExtensionCollection::__construct
    // @todo Figure out a better way to handle this.
    foreach ($definitions as $definition) {
      if (!isset($definition['_requiredBy'])) {
        $definition['_requiredBy'] = [];
      }
      $extensionRequirements = $definition->getRequirementsByType('extension');
      foreach ($extensionRequirements as $requirement) {
        $id = $requirement->getTypeId();

        // Check that the plugin exists.
        if (!isset($definitions[$id])) {
          throw new PluginNotFoundException($id);
        }

        // Extensions cannot require themselves.
        if ($id === $definition->id) {
          throw new InvalidPluginDefinitionException($definition->id, 'Extensions cannot require themselves.');
        }
        if (!isset($definitions[$id]['_requiredBy'])) {
          $definitions[$id]['_requiredBy'] = [];
        }
        if (!in_array($definition->id, $definitions[$id]['_requiredBy'])) {
          $definitions[$id]['_requiredBy'][] = $definition->id;
        }
      }
    }
    parent::alterDefinitions($definitions, $runtime);
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinition(InstallablePlugin $definition, $runtime = FALSE) {
    // Immediately return if not altering the runtime definition.
    if (!$runtime) {
      parent::alterDefinition($definition, $runtime);
      return;
    }

    parent::alterDefinition($definition, $runtime);
  }

  /**
   * {@inheritdoc}
   */
  protected function createObjectRequirement(InstallablePlugin $definition, InstallableLibrary $library) {
    $objectRequirement = parent::createObjectRequirement($definition, $library);
    $id = $objectRequirement->constraints['Installed']['name'];
    /** @var \Drupal\markdown\PluginManager\ParserManagerInterface $parserManager */
    $parserManager = \Drupal::service('plugin.manager.markdown.parser');
    $parser = $parserManager->getDefinitionByLibraryId($id);
    foreach ($library->requirements as $requirement) {
      if ($requirement->getId() === $id || ($parser && $requirement->getType() === 'parser' && $requirement->getTypeId() === $parser->getId())) {
        return NULL;
      }
    }
    return $objectRequirement;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id = NULL, array $configuration = []) {
    return '_missing_extension';
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $pluginId) {
    if (!($definition instanceof MarkdownExtension)) {
      return;
    }

    if ($requires = $definition->requires) {
      foreach ($requires as $key => $extensionId) {
        $requirement = new InstallableRequirement();
        $requirement->id = "extension:$extensionId";
        $definition->runtimeRequirements[] = $requirement;
      }
      unset($definition->requires);
    }

    parent::processDefinition($definition, $pluginId);
  }

}
