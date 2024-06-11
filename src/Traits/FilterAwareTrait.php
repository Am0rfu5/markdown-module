<?php

namespace Drupal\markdown\Traits;

use Drupal\filter\Plugin\FilterInterface;

/**
 * Trait for implementing \Drupal\markdown\Util\FilterAwareInterface.
 */
trait FilterAwareTrait {

  /**
   * A Filter plugin.
   *
   * @var \Drupal\filter\Plugin\FilterInterface
   */
  protected $filter;

  /**
   * {@inheritdoc}
   */
  public function getFilter() {
    return $this->filter;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilter(FilterInterface $filter = NULL) {
    $this->filter = $filter;
    return $this;
  }

}
