<?php

namespace Drupal\markdown\Config;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Markdown Config.
 *
 * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
 *   Use \Drupal\markdown\Form\ParserConfigurationForm instead.
 * @see https://www.drupal.org/project/markdown/issues/3142418
 */
class MarkdownConfig extends Config implements ContainerInjectionInterface {

  /**
   * The prefix to prepend all keys with.
   *
   * Note: this is primarily for use when the config is wrapped inside
   * higher levels of config.
   *
   * @var string
   */
  protected $keyPrefix;

  /**
   * {@inheritdoc}
   */
  public function __construct($name, StorageInterface $storage, EventDispatcherInterface $event_dispatcher, TypedConfigManagerInterface $typed_config, array $data = NULL) {
    parent::__construct($name, $storage, $event_dispatcher, $typed_config);
    if (isset($data)) {
      $this->initWithData($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container = NULL, $name = NULL, array $data = NULL) {
    if (!$container) {
      $container = \Drupal::getContainer();
    }
    return new static(
      $name,
      $container->get('config.storage'),
      $container->get('event_dispatcher'),
      $container->get('config.typed'),
      $data
    );
  }

  /**
   * Creates a new instance using provided data or loading existing config data.
   *
   * @param string $name
   *   The config name where the data is stored.
   * @param array $data
   *   Optional. Initial data to use.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Optional. The service container this instance should use.
   *
   * @return static
   */
  public static function load($name, array $data = NULL, ContainerInterface $container = NULL) {
    if (!isset($data)) {
      $data = \Drupal::config($name)->getRawData();
    }
    return static::create($container, $name, $data);
  }

  /**
   * Retrieves the key prefix, if any.
   *
   * @return string|null
   *   The key prefix, if set.
   */
  public function getKeyPrefix() {
    return $this->keyPrefix;
  }

  /**
   * Prefixes a key, if a prefix is set.
   *
   * @param string $key
   *   The key to prefix.
   *
   * @return string
   *   The prefixed key.
   */
  protected function prefixKey($key) {
    if ($prefix = $this->getKeyPrefix()) {
      if (($pos = strpos($key, "$prefix.")) === 0) {
        $key = substr($key, strlen("$prefix."));
      }
      $key = "$prefix.$key";
    }
    return $key;
  }

  /**
   * Prefixes keys of an associative array of data.
   *
   * @param array $data
   *   The data to iterate over.
   *
   * @return array
   *   The data with prefixed keys.
   */
  protected function prefixKeys(array $data) {
    $prefixed = [];
    foreach ($data as $key => $value) {
      $prefixed[$this->prefixKey($key)] = $value;
    }
    return $prefixed;
  }

  /**
   * Sets the key prefix.
   *
   * @param string $keyPrefix
   *   The key prefix to set.
   *
   * @return static
   */
  public function setKeyPrefix($keyPrefix) {
    $this->keyPrefix = rtrim($keyPrefix, '.');
    return $this;
  }

}
