<?php

namespace Drupal\markdown\Traits;

use Drupal\filter\Entity\FilterFormat;

/**
 * Trait for implementing \Drupal\markdown\Util\FilterFormatAwareInterface.
 */
trait FilterFormatAwareTrait {

  /**
   * A FilterFormat entity.
   *
   * @var \Drupal\filter\Entity\FilterFormat
   */
  protected $filterFormat;

  /**
   * {@inheritdoc}
   */
  public function getFilterFormat() {
    return $this->filterFormat;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilterFormat(FilterFormat $format = NULL) {
    $this->filterFormat = $format;
    return $this;
  }

}
