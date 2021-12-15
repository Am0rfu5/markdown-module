<?php

namespace Drupal\markdown;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\markdown\Render\ParsedMarkdownInterface;

/**
 * Interface MarkdownInterface.
 */
interface MarkdownInterface extends ContainerInjectionInterface {

  /**
   * The base URL for Markdown documentation.
   *
   * @var string
   */
  const DOCUMENTATION_URL = 'https://www.drupal.org/docs/contributed-modules/markdown';

  /**
   * Loads a cached ParsedMarkdown object.
   *
   * @param string $id
   *   A unique identifier that will be used to cache the parsed markdown.
   */
  public function load($id);

  /**
   * Loads a cached ParsedMarkdown object based on system file.
   *
   * @param string $filename
   *   The local file system path of a markdown file to parse if the cached
   *   ParsedMarkdown object doesn't yet exist. Once parsed, its identifier
   *   will be set to the provided $id and then cached.
   * @param string $id
   *   Optional. A unique identifier for caching the parsed markdown. If not
   *   set, one will be generated automatically based on the provided $filename.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Optional. The language of the markdown that is being parsed.
   *
   * @return \Drupal\markdown\Render\ParsedMarkdownInterface
   *   A ParsedMarkdown object.
   *
   * @throws \Drupal\markdown\Exception\MarkdownFileNotExistsException
   */
  public function loadFile($filename, $id = NULL, LanguageInterface $language = NULL);

  /**
   * Loads a cached ParsedMarkdown object based on a file system path.
   *
   * @param string $path
   *   The local file system path of a markdown file to parse if the cached
   *   ParsedMarkdown object doesn't yet exist. Once parsed, its identifier
   *   will be set to the provided $id and then cached.
   * @param string $id
   *   Optional. A unique identifier for caching the parsed markdown. If not
   *   set, one will be generated automatically based on the provided $path.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Optional. The language of the markdown that is being parsed.
   *
   * @return \Drupal\markdown\Render\ParsedMarkdownInterface
   *   A ParsedMarkdown object.
   *
   * @throws \Drupal\markdown\Exception\MarkdownFileNotExistsException
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   Use \Drupal\markdown\MarkdownInterface::loadFile instead.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  public function loadPath($path, $id = NULL, LanguageInterface $language = NULL);

  /**
   * Loads a cached ParsedMarkdown object based on a URL.
   *
   * @param string $url
   *   The external URL of a markdown file to parse if the cached
   *   ParsedMarkdown object doesn't yet exist. Once parsed, its identifier
   *   will be set to the provided $id and then cached.
   * @param string $id
   *   Optional. A unique identifier for caching the parsed markdown. If not
   *   set, one will be generated automatically based on the provided $url.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Optional. The language of the markdown that is being parsed.
   *
   * @return \Drupal\markdown\Render\ParsedMarkdownInterface
   *   A ParsedMarkdown object.
   *
   * @throws \Drupal\markdown\Exception\MarkdownUrlNotExistsException
   */
  public function loadUrl($url, $id = NULL, LanguageInterface $language = NULL);

  /**
   * Parses markdown into HTML.
   *
   * @param string $markdown
   *   The markdown string to parse.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Optional. The language of the markdown that is being parsed.
   *
   * @return \Drupal\markdown\Render\ParsedMarkdownInterface
   *   A ParsedMarkdown object.
   */
  public function parse($markdown, LanguageInterface $language = NULL);

  /**
   * Retrieves a MarkdownParser plugin.
   *
   * @param string $parserId
   *   Optional. The plugin identifier of a specific MarkdownParser to retrieve.
   *   If not provided, the default site-wide parser will be used.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\markdown\Plugin\Markdown\ParserInterface
   *   A MarkdownParser plugin.
   */
  public function getParser($parserId = NULL, array $configuration = []);

  /**
   * Saves a parsed markdown object.
   *
   * @param string $id
   *   The identifier to use when saving the parsed markdown object.
   * @param \Drupal\markdown\Render\ParsedMarkdownInterface $parsed
   *   The parsed markdown object to save.
   *
   * @return \Drupal\markdown\Render\ParsedMarkdownInterface
   *   The passed parsed markdown.
   */
  public function save($id, ParsedMarkdownInterface $parsed);

}
