<?php

namespace Drupal\markdown\PluginManager;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\markdown\Annotation\MarkdownParser;
use Drupal\markdown\Plugin\Markdown\ExtensibleParserInterface;
use Drupal\markdown\Plugin\Markdown\ExtensionInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\Traits\EnableAwarePluginManagerTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Markdown Parser Plugin Manager.
 *
 * @method \Drupal\markdown\Plugin\Markdown\ParserInterface[] all(array $configuration = [], $includeFallback = FALSE) : array
 * @method \Drupal\markdown\Plugin\Markdown\ParserInterface[] enabled(array $configuration = []) : array
 * @method \Drupal\markdown\Annotation\MarkdownParser getDefinition($plugin_id, $exception_on_invalid = TRUE)
 * @method \Drupal\markdown\Annotation\MarkdownParser|void getDefinitionByClassName($className)
 * @method \Drupal\markdown\Annotation\MarkdownParser[] getDefinitions($includeFallback = TRUE)
 * @method \Drupal\markdown\Plugin\Markdown\ParserInterface[] installed(array $configuration = []) : array
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */
class ParserManager extends InstallablePluginManager implements ParserManagerInterface {

  use EnableAwarePluginManagerTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ConfigFactoryInterface $configFactory, LoggerInterface $logger, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Markdown', $namespaces, $configFactory, $logger, $module_handler, ParserInterface::class, MarkdownParser::class);
    $this->setCacheBackend($cache_backend, 'markdown_parser_info');
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
   *
   * @return \Drupal\markdown\Plugin\Markdown\ParserInterface
   *   A Parser instance.
   */
  public function createInstance($plugin_id, array $configuration = []) {
    /** @var \Drupal\markdown\Plugin\Markdown\ParserInterface $parser */
    $parser = parent::createInstance($plugin_id, $configuration);

    // If the parser is the fallback parser (missing), then just return it.
    if ($parser->getPluginId() === $this->getFallbackPluginId()) {
      return $parser;
    }

    // Add a default cache tag.
    $parser->addCacheTags(["markdown.parser.$plugin_id"]);

    return $parser;
  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultParser(array $configuration = []) {
    $settings = $this->configFactory->get('markdown.settings');
    if (!($defaultParser = $settings->get('default_parser'))) {
      $defaultParser = current(array_keys($this->installed()));
      $this->logger->warning($this->t('No default markdown parser set, using first available installed parser "@default_parser".', [
        '@default_parser' => $defaultParser,
      ]));
    }
    return $this->createInstance($defaultParser, $configuration);

  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id = NULL, array $configuration = []) {
    return '_missing_parser';
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    if (!($definition instanceof MarkdownParser) || !$definition->isInstalled() || !($class = $definition->getClass())) {
      return;
    }

    // Process extensible parser support.
    if (is_subclass_of($class, ExtensibleParserInterface::class)) {
      if (!$definition->extensionInterfaces) {
        throw new InvalidPluginDefinitionException($plugin_id, sprintf('Markdown parser "%s" implements %s but is missing "extensionInterfaces" in the definition.', $plugin_id, ExtensibleParserInterface::class));
      }
      foreach (array_map('\Drupal\markdown\PluginManager\InstallablePluginManager::normalizeClassName', $definition->extensionInterfaces) as $interface) {
        if ($interface === ExtensionInterface::class) {
          throw new InvalidPluginDefinitionException($plugin_id, sprintf('Markdown parser "%s" cannot specify %s as the extension interface. It must create its own unique interface that extend from it.', $plugin_id, ExtensionInterface::class));
        }
        if (!interface_exists($interface)) {
          throw new InvalidPluginDefinitionException($plugin_id, sprintf('Markdown parser "%s" indicates that it supports the extension interface "%s", but this interface does not exist.', $plugin_id, $interface));
        }
        if (!is_subclass_of($interface, ExtensionInterface::class)) {
          throw new InvalidPluginDefinitionException($plugin_id, sprintf('Markdown parser "%s" indicates that it supports the extension interface "%s", but this interface does not extend %s.', $plugin_id, $interface, ExtensionInterface::class));
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

}
