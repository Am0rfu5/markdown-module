<?php

namespace Drupal\markdown\ParamConverter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\ExtensionInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\PluginManager\AllowedHtmlManager;
use Drupal\markdown\PluginManager\ExtensionManagerInterface;
use Drupal\markdown\PluginManager\ParserManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Parameter convertor for Markdown plugins.
 */
class MarkdownParamConverter implements ParamConverterInterface {

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Markdown Allowed HTML Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\AllowedHtmlManager
   */
  protected $allowedHtmlManager;

  /**
   * The Markdown Extension Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\ExtensionManagerInterface
   */
  protected $extensionManager;

  /**
   * The Markdown Parser Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\ParserManagerInterface
   */
  protected $parserManager;

  /**
   * MarkdownParamConverter constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory service.
   * @param \Drupal\markdown\PluginManager\ParserManagerInterface $parserManager
   *   The Markdown Parser Plugin Manager service.
   * @param \Drupal\markdown\PluginManager\ExtensionManagerInterface $extensionManager
   *   The Markdown Extension Plugin Manager service.
   * @param \Drupal\markdown\PluginManager\AllowedHtmlManager $allowedHtmlManager
   *   The Markdown Allowed HTML Plugin Manager service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ParserManagerInterface $parserManager, ExtensionManagerInterface $extensionManager, AllowedHtmlManager $allowedHtmlManager) {
    $this->configFactory = $configFactory;
    $this->parserManager = $parserManager;
    $this->extensionManager = $extensionManager;
    $this->allowedHtmlManager = $allowedHtmlManager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $type = substr($definition['type'], 9);
    $configuration = $this->configFactory->get("markdown.$type.$value")->get();
    switch ($type) {
      case 'parser':
        return $value instanceof ParserInterface ? $value : $this->parserManager->createInstance((string) $value, $configuration);

      case 'extension':
        return $value instanceof ExtensionInterface ? $value : $this->extensionManager->createInstance((string) $value, $configuration);

      case 'allowed_html':
        return $value instanceof AllowedHtmlInterface ? $value : $this->allowedHtmlManager->createInstance((string) $value, $configuration);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return isset($definition['type']) && strpos($definition['type'], 'markdown:') !== FALSE;
  }

}
