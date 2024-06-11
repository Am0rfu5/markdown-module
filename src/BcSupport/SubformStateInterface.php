<?php

namespace Drupal\markdown\BcSupport;

if (!interface_exists('\Drupal\Core\Form\SubformStateInterface')) {
  /* @noinspection PhpIgnoredClassAliasDeclaration */
  class_alias('\Drupal\Core\Form\FormStateInterface', '\Drupal\Core\Form\SubformStateInterface');
}

use Drupal\Core\Form\SubformStateInterface as CoreSubformStateInterface;

/**
 * Stores information about the state of a subform.
 *
 * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
 *   Use \Drupal\Core\Form\SubformStateInterface instead.
 *
 * @see https://www.drupal.org/project/markdown/issues/3103679
 */
interface SubformStateInterface extends CoreSubformStateInterface {

  /**
   * Gets the complete form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The complete form state.
   */
  public function getCompleteFormState();

}
