<?php

namespace Drupal\markdown\Plugin\Filter;

use Drupal\filter\Plugin\FilterInterface;
use Drupal\markdown\Util\FilterFormatAwareInterface;

/**
 * Interface for the markdown filter.
 */
interface FilterMarkdownInterface extends FilterInterface, FilterFormatAwareInterface {

  /**
   * Retrieves the MarkdownParser plugin for this filter.
   *
   * @return \Drupal\markdown\Plugin\Markdown\ParserInterface
   *   The MarkdownParser plugin.
   */
  public function getParser();

  /**
   * Indicates whether the filter is enabled or not.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isEnabled();

}
