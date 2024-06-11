<?php

namespace Drupal\markdown\Plugin\Validation\Constraint;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Drupal\Core\Render\Markup;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a field is unique for the given entity type.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 * @internal
 */
class VersionValidator extends ConstraintValidator {

  /**
   * Semver version parser.
   *
   * @var \Composer\Semver\VersionParser
   */
  private static $versionParser;

  /**
   * {@inheritdoc}
   */
  public function validate($version, Constraint $constraint) {
    /** @var \Drupal\markdown\Plugin\Validation\Constraint\Version $constraint */
    $semverConstraints = $constraint->value;

    $named = isset($constraint->name);
    $message = $named ? $constraint->namedMessage : $constraint->message;
    $params = [
      '@name' => $named ? Markup::create($constraint->name) : 'Unknown',
      '@constraints' => $semverConstraints ? Markup::create($semverConstraints) : '',
      '@version' => $version ? Markup::create($version) : '',
    ];
    $validated = FALSE;

    try {
      if (!empty($version)) {
        if (!empty($semverConstraints)) {
          $validated = Semver::satisfies($version, $semverConstraints);
        }
        else {
          if (!self::$versionParser) {
            self::$versionParser = new VersionParser();
          }
          $validated = !!self::$versionParser->normalize($version);
        }
      }
    }
    catch (\UnexpectedValueException $exception) {
      $message = $exception->getMessage();
    }

    if (!$validated) {
      // Passing an already translated message allows markup to be preserved
      // when it passes to the theme system.
      $this->context->addViolation(t($message, $params));
    }
  }

}
