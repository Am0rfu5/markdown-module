<?php

namespace Drupal\markdown\Util;

use Composer\Semver\Comparator;
use Composer\Semver\Semver as ComposerSemver;
use Composer\Semver\VersionParser;

/**
 * Extends the base Composer SemVer class with additional functionality.
 *
 * @internal
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
class Semver extends ComposerSemver {

  /**
   * A version parser.
   *
   * @var \Composer\Semver\VersionParser
   */
  protected static $versionParser;

  /**
   * An indexed array of stabilities, ordered from lowest to highest.
   *
   * @var array
   */
  protected static $stabilities = [
    'dev',
    'alpha',
    'beta',
    'RC',
    'stable',
  ];

  /**
   * Indicates whether a version is valid.
   *
   * @param string $version
   *   The version to test.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public static function isValid($version) {
    try {
      return !!static::versionParser()->normalize($version);
    }
    catch (\UnexpectedValueException $exception) {
      // Intentionally do nothing.
    }
    return FALSE;
  }

  /**
   * Sorts an array where the keys are versions.
   *
   * Note: this is basically just a strait copy of Semver's usort method.
   * However, since they "final" all their classes, it cannot be extended from.
   * Regardless, this assumes the version is the key.
   *
   * @param array $array
   *   The array to sort, passed by reference.
   * @param int $direction
   *   The sort direction.
   *
   * @see \Composer\Semver\Semver::usort()
   */
  public static function ksort(array &$array, $direction = Semver::SORT_ASC) {
    $versionParser = static::versionParser();

    $normalized = [];
    foreach ($array as $key => $value) {
      $normalized[] = [$versionParser->normalize($key), $key, $value];
    }

    usort($normalized, function (array $left, array $right) use ($direction) {
      if ($left[0] === $right[0]) {
        return 0;
      }

      if (Comparator::lessThan($left[0], $right[0])) {
        return -$direction;
      }

      return $direction;
    });

    // Recreate input array, using the original indexes which are now sorted.
    $sorted = [];
    foreach ($normalized as $item) {
      list($key, $value) = array_slice($item, 1);
      $sorted[$key] = $value;
    }

    $array = $sorted;
  }

  /**
   * Retrieves the latest version from an array of versions.
   *
   * @param array $versions
   *   The versions to search.
   * @param string $constraints
   *   The constraints for which each version must satisfy.
   *
   * @return string|void
   *   The latest version or NULL if one does not exist.
   */
  public static function latestVersion(array $versions, $constraints) {
    $versions = static::satisfiedBy($versions, $constraints);
    if ($versions && ($versions = static::sort($versions))) {
      return array_pop($versions);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function satisfiedBy(array $versions, $constraints) {
    if ($versions) {
      $versions = parent::satisfiedBy($versions, $constraints);

      // Detect constraint minimum stability.
      if (strpos($constraints, '@') !== FALSE) {
        $versions = static::satisfiedByStability($versions, VersionParser::parseStability($constraints));
      }
    }

    return $versions;
  }

  /**
   * Return all versions that satisfy a given minimum stability.
   *
   * @param array $versions
   *   An array of versions that must be satisfied by a given stability.
   * @param string $minimumStability
   *   The minimum stability the passed versions must satisfy. Can be one of:
   *   stable, RC, beta, alpha, or dev.
   *
   * @return array
   *   The versions that are satisfied by the given minimum stability.
   */
  protected static function satisfiedByStability(array $versions, $minimumStability = 'stable') {
    // Semver doesn't have a clean way to satisfy by stability.
    // https://github.com/composer/semver/issues/49#issuecomment-266287082
    $versionParser = static::versionParser();
    $minimumStability = array_search($minimumStability, static::$stabilities, TRUE) ?: 0;
    return array_filter($versions, function ($version) use ($versionParser, $minimumStability) {
      $normalized = $versionParser->normalize($version);
      $stability = array_search($versionParser::parseStability($normalized), static::$stabilities, TRUE) ?: 0;
      return $stability >= $minimumStability;
    });
  }

  /**
   * {@inheritdoc}
   */
  public static function satisfies($version, $constraints) {
    // Intercept parent method to detect constraint minimum stability.
    $constraintStability = TRUE;
    if (strpos($constraints, '@') !== FALSE) {
      $constraintStability = !!static::satisfiedByStability([$version], VersionParser::parseStability($constraints));
    }
    return $constraintStability && parent::satisfies($version, $constraints);
  }

  /**
   * Retrieves a version parser.
   *
   * @return \Composer\Semver\VersionParser
   *   A version parser.
   */
  public static function versionParser() {
    if (!static::$versionParser) {
      static::$versionParser = new VersionParser();
    }
    return static::$versionParser;
  }

}
