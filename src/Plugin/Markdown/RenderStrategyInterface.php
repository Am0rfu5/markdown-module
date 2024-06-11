<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\markdown\MarkdownInterface;

/**
 * Interface for supporting markdown render strategies.
 */
interface RenderStrategyInterface {

  /**
   * Strategy used to filter the output of parsed markdown.
   *
   * @var string
   */
  const FILTER_OUTPUT = 'filter_output';

  /**
   * Strategy used to escape HTML input prior to parsing markdown.
   *
   * @var string
   */
  const ESCAPE_INPUT = 'escape_input';

  /**
   * The documentation URL for further explaining render strategies.
   *
   * @var string
   */
  const DOCUMENTATION_URL = MarkdownInterface::DOCUMENTATION_URL . '/parsers/render-strategy';

  /**
   * The URL for explaining Markdown and XSS; render strategies.
   *
   * @var string
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0. Use
   *   \Drupal\markdown\Plugin\Markdown\RenderStrategyInterface::DOCUMENTATION_URL
   *   instead with a #xss fragment appended.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  const MARKDOWN_XSS_URL = self::DOCUMENTATION_URL . '#xss';

  /**
   * No render strategy.
   *
   * @var string
   */
  const NONE = 'none';

  /**
   * Strategy used to remove HTML input prior to parsing markdown.
   *
   * @var string
   */
  const STRIP_INPUT = 'strip_input';

  /**
   * Retrieves the custom (user provided) allowed HTML.
   *
   * @return string
   *   The user provided (custom) allowed HTML.
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   Use RenderStrategyInterface::getCustomAllowedHtml instead.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  public function getAllowedHtml();

  /**
   * Retrieves the allowed HTML plugins relevant to the object.
   *
   * @return string[]
   *   An indexed array of allowed HTML plugins identifiers.
   */
  public function getAllowedHtmlPlugins();

  /**
   * Retrieves the custom (user provided) allowed HTML.
   *
   * @return string
   *   The user provided (custom) allowed HTML.
   */
  public function getCustomAllowedHtml();

  /**
   * Retrieves the render strategy to use.
   *
   * @return string
   *   The render strategy.
   */
  public function getRenderStrategy();

}
