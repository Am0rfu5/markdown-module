<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Language\LanguageInterface;
use Drupal\markdown\Plugin\Markdown\Extension\CommonMarkRendererInterface;
use League\CommonMark\Block\Parser\BlockParserInterface;
use League\CommonMark\Block\Renderer\BlockRendererInterface;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\DocumentProcessorInterface;
use League\CommonMark\Environment;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Inline\Parser\InlineParserInterface;
use League\CommonMark\Inline\Processor\InlineProcessorInterface;
use League\CommonMark\Inline\Renderer\InlineRendererInterface;

/**
 * Class CommonMark.
 *
 * @MarkdownParser(
 *   id = "commonmark",
 *   label = @Translation("CommonMark"),
 *   checkClass = "League\CommonMark\CommonMarkConverter",
 * )
 */
class CommonMark extends BaseMarkdownParser {

  /**
   * CommonMark converters, keyed by format filter identifiers.
   *
   * @var \League\CommonMark\Converter[]
   */
  protected static $converters;

  /**
   * A CommonMark environment, keyed by format filter identifiers.
   *
   * @var \League\CommonMark\Environment[]
   */
  protected static $environments;

  /**
   * Retrieves a CommonMark converter, creating it if necessary.
   *
   * @return \League\CommonMark\Converter
   *   A CommonMark converter.
   */
  protected function getConverter() {
    if (!isset(static::$converters[$this->filterId])) {
      $environment = $this->getEnvironment();
      static::$converters[$this->filterId] = new CommonMarkConverter($this->settings, $environment);
    }
    return static::$converters[$this->filterId];
  }

  /**
   * Retrieves a CommonMark environment, creating it if necessary.
   *
   * @return \League\CommonMark\Environment
   *   The CommonMark environment.
   */
  protected function getEnvironment() {
    if (!isset(static::$environments[$this->filterId])) {
      $environment = Environment::createCommonMarkEnvironment();
      $extensions = $this->getExtensions($this->filter);
      foreach ($extensions as $extension) {
        if ($settings = $extension->getSettings()) {
          $environment->setConfig(NestedArray::mergeDeep($environment->getConfig(), $settings));
        }

        if ($extension instanceof ExtensionInterface) {
          $environment->addExtension($extension);
        }

        if ($extension instanceof DocumentProcessorInterface) {
          $environment->addDocumentProcessor($extension);
        }

        if ($extension instanceof InlineProcessorInterface) {
          $environment->addInlineProcessor($extension);
        }

        // Add Block extensions.
        if ($extension instanceof BlockParserInterface || ($extension instanceof BlockRendererInterface && $extension instanceof CommonMarkRendererInterface)) {
          if ($extension instanceof BlockParserInterface) {
            $environment->addBlockParser($extension);
          }
          if ($extension instanceof BlockRendererInterface) {
            $environment->addBlockRenderer($extension->rendererClass(), $extension);
          }
          continue;
        }

        // Add Inline extensions.
        if ($extension instanceof InlineParserInterface || ($extension instanceof InlineRendererInterface && $extension instanceof CommonMarkRendererInterface)) {
          if ($extension instanceof InlineParserInterface) {
            $environment->addInlineParser($extension);
          }
          if ($extension instanceof InlineRendererInterface) {
            $environment->addInlineRenderer($extension->rendererClass(), $extension);
          }
          continue;
        }
      }

      static::$environments[$this->filterId] = $environment;
    }
    return static::$environments[$this->filterId];
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion() {
    return CommonMarkConverter::VERSION;
  }

  /**
   * {@inheritdoc}
   */
  public function parse($markdown, LanguageInterface $language = NULL) {
    return trim(Xss::filterAdmin($this->getConverter()->convertToHtml($markdown)));
  }

}
