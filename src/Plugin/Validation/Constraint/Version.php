<?php

namespace Drupal\markdown\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks whether a specific version is satisfied by Semver constraints.
 *
 * @Constraint(
 *   id = "Version",
 *   label = @Translation("Version constraint", context = "Validation"),
 * )
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 * @internal
 */
class Version extends Constraint {

  public $message = 'Version "@version" does not satisfy the following semantic version constraints "@constraints".';

  public $namedMessage = 'Requires @name:@constraints';

  public $name;

  public $value;

}
