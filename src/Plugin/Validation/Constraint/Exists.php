<?php

namespace Drupal\markdown\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks whether a specific class, interface, trait, or function exists.
 *
 * @Constraint(
 *   id = "Exists",
 *   label = @Translation("Exists constraint", context = "Validation"),
 * )
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 * @internal
 */
class Exists extends Constraint {

  public $message = '@name does not exist.';

}
