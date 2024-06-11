<?php

namespace Drupal\markdown\Util;

use Drupal\filter\Plugin\FilterInterface;

/**
 * Interface for allowing an object to be aware of a Filter instance.
 */
interface FilterAwareInterface {

  /**
   * Retrieves a Filter instance, if set.
   *
   * @return \Drupal\filter\Plugin\FilterInterface|null
   *   A Markdown Filter instance or NULL if not set.
   */
  public function getFilter();

  /**
   * Sets the Filter plugin.
   *
   * @param \Drupal\filter\Plugin\FilterInterface $filter
   *   A Filter instance.
   *
   * @return static
   */
  public function setFilter(FilterInterface $filter = NULL);

}
