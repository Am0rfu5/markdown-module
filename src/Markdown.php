<?php

namespace Drupal\markdown;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\markdown\Exception\MarkdownFileNotExistsException;
use Drupal\markdown\Exception\MarkdownUrlNotExistsException;
use Drupal\markdown\PluginManager\ParserManagerInterface;
use Drupal\markdown\Render\ParsedMarkdownInterface;
use GuzzleHttp\ClientInterface;
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
   * The MarkdownParser Plugin Manager.
   *
   * @var \Drupal\markdown\PluginManager\ParserManagerInterface
   */
  protected $parserManager;

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
   * @param \Drupal\markdown\PluginManager\ParserManagerInterface $parserManager
   *   The Markdown Parser Plugin Manager service.
   */
  public function __construct(CacheBackendInterface $cache, ConfigFactoryInterface $configFactory, FileSystemInterface $fileSystem, ClientInterface $httpClient, ParserManagerInterface $parserManager) {
    $this->cache = $cache;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->httpClient = $httpClient;
    $this->parserManager = $parserManager;
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
      $container->get('plugin.manager.markdown.parser')
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
  public function loadFile($path, $id = NULL, LanguageInterface $language = NULL) {
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
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   Use \Drupal\markdown\MarkdownInterface::loadFile instead.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  public function loadPath($path, $id = NULL, LanguageInterface $language = NULL) {
    return $this->loadFile($path, $id, $language);
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
  public function getParser($parserId = NULL, array $configuration = []) {
    if (empty($parserId)) {
      return $this->parserManager->getDefaultParser($configuration);
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
