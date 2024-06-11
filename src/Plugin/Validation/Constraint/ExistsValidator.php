<?php

namespace Drupal\markdown\Plugin\Validation\Constraint;

use Drupal\Core\Render\Markup;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a class, interface, trait, or function exists.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 * @internal
 */
class ExistsValidator extends ConstraintValidator {

  public $name;

  /**
   * {@inheritdoc}
   */
  public function validate($class, Constraint $constraint) {
    if (!is_string($class) || empty($class) || (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !function_exists($class) && !defined($class) && !is_callable($class))) {
      // Passing an already translated message allows markup to be preserved
      // when it passes to the theme system.
      $message = t($constraint->message, [
        '@name' => isset($constraint->name) ? Markup::create($constraint->name) : $class,
      ]);
      $this->context->addViolation($message);
    }
  }

}
