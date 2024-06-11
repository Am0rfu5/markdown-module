<?php

namespace Drupal\markdown\Util;

use Drupal\filter\Entity\FilterFormat;

/**
 * Interface for allowing an object to be aware of a FilterFormat object.
 */
interface FilterFormatAwareInterface {

  /**
   * Retrieves a FilterFormat entity, if set.
   *
   * @return \Drupal\filter\Entity\FilterFormat|null
   *   A FilterFormat entity or NULL if not set.
   */
  public function getFilterFormat();

  /**
   * Sets the FilterFormat entity.
   *
   * @param \Drupal\filter\Entity\FilterFormat $format
   *   A FilterFormat entity.
   *
   * @return static
   */
  public function setFilterFormat(FilterFormat $format = NULL);

}
