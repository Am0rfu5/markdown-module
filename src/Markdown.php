<?php

namespace Drupal\markdown;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\markdown\Config\ImmutableMarkdownConfig;
use Drupal\markdown\Exception\MarkdownFileNotExistsException;
use Drupal\markdown\Exception\MarkdownUrlNotExistsException;
use Drupal\markdown\PluginManager\ParserManagerInterface;
use Drupal\markdown\Render\ParsedMarkdownInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Markdown service.
 */
class Markdown implements MarkdownInterface {

  use StringTranslationTrait;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The File System service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The HTTP Client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * A logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The MarkdownParser Plugin Manager.
   *
   * @var \Drupal\markdown\PluginManager\ParserManagerInterface
   */
  protected $parserManager;

  /**
   * The global Markdown config settings.
   *
   * @var \Drupal\markdown\Config\ImmutableMarkdownConfig
   */
  protected $settings;

  /**
   * Markdown constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The File System service.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP Client service.
   * @param \Psr\Log\LoggerInterface
   *   A logger.
   * @param \Drupal\markdown\PluginManager\ParserManagerInterface $parserManager
   *   The Markdown Parser Plugin Manager service.
   * @param \Drupal\markdown\Config\ImmutableMarkdownConfig $settings
   *   The Markdown Settings.
   */
  public function __construct(CacheBackendInterface $cache, ConfigFactoryInterface $configFactory, FileSystemInterface $fileSystem, ClientInterface $httpClient, LoggerInterface $logger, ParserManagerInterface $parserManager, ImmutableMarkdownConfig $settings) {
    $this->cache = $cache;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->httpClient = $httpClient;
    $this->logger = $logger;
    $this->parserManager = $parserManager;
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container = NULL) {
    if (!isset($container)) {
      $container = \Drupal::getContainer();
    }
    return new static(
      $container->get('cache.markdown'),
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('http_client'),
      $container->get('logger.channel.markdown'),
      $container->get('plugin.manager.markdown.parser'),
      $container->get('markdown.settings')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function load($id) {
    if ($id && ($cache = $this->cache->get($id)) && $cache->data instanceof ParsedMarkdownInterface) {
      return $cache->data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadPath($path, $id = NULL, LanguageInterface $language = NULL) {
    $realpath = $this->fileSystem->realpath($path) ?: $path;
    if (!file_exists($realpath)) {
      throw new MarkdownFileNotExistsException($realpath);
    }

    if (!$id) {
      $id = $this->fileSystem->basename($realpath) . Crypt::hashBase64($realpath);
    }

    // Append the file modification time as a cache buster in case it changed.
    $id = "$id:" . filemtime($realpath);
    return $this->load($id) ?: $this->save($id, $this->parse(file_get_contents($realpath) ?: '', $language));
  }

  /**
   * {@inheritdoc}
   */
  public function loadUrl($url, $id = NULL, LanguageInterface $language = NULL) {
    if ($url instanceof Url) {
      $url = $url->setAbsolute()->toString();
    }
    else {
      $url = (string) $url;
    }

    if (!$id) {
      $id = $url;
    }

    if (!($parsed = $this->load($id))) {
      $response = $this->httpClient->get($url);
      if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
        throw new MarkdownUrlNotExistsException($url);
      }
      $parsed = $this->save($id, $this->parse($response->getBody()->getContents(), $language));
    }

    return $parsed;
  }

  /**
   * {@inheritdoc}
   */
  public function parse($markdown, LanguageInterface $language = NULL) {
    return $this->getParser()->parse($markdown, $language);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultParser(array $configuration = []) {
    $settings = $this->configFactory->getEditable('markdown.settings');
    if (!($defaultParser = $settings->get('default_parser'))) {
      $defaultParser = current(array_keys($this->parserManager->installed()));
      $this->logger->warning($this->t('No default markdown parser set, using first available installed parser "@default_parser".', [
        '@default_parser' => $defaultParser,
      ]));
    }
    return $this->parserManager->createInstance($defaultParser, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getParser($parserId = NULL, array $configuration = []) {
    if ($parserId === NULL) {
      return $this->getDefaultParser($configuration);
    }
    return $this->parserManager->createInstance($parserId, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function save($id, ParsedMarkdownInterface $parsed) {
    $this->cache->set($id, $parsed, $parsed->getExpire(), $parsed->getCacheTags());
    return $parsed;
  }

}
