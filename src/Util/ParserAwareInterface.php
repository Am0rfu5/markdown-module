<?php

namespace Drupal\markdown\Util;

use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Interface for allowing an object to be aware of a Markdown Parser instance.
 */
interface ParserAwareInterface {

  /**
   * Retrieves a Filter instance, if set.
   *
   * @return \Drupal\markdown\Plugin\Markdown\ParserInterface|null
   *   A Markdown Parser instance or NULL if not set.
   */
  public function getParser();

  /**
   * Sets the Filter plugin.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   A Markdown Parser instance.
   *
   * @return static
   */
  public function setParser(ParserInterface $parser = NULL);

}
