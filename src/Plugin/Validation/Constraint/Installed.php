<?php

namespace Drupal\markdown\Plugin\Validation\Constraint;

/**
 * Checks whether a specific class, interface, trait, or function exists.
 *
 * @Constraint(
 *   id = "Installed",
 *   label = @Translation("Installed constraint", context = "Validation"),
 * )
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 * @internal
 */
class Installed extends Exists {

  public $message = 'Requires @name';

  public $name;

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return '\Drupal\markdown\Plugin\Validation\Constraint\ExistsValidator';
  }

}
