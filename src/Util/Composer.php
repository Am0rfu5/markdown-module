<?php

namespace Drupal\markdown\Util;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\DiffArray;

/**
 * Helper class used to deal with composer.
 *
 * @internal
 * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
 *   Use \Composer\InstalledVersions instead.
 * @see https://www.drupal.org/project/markdown/issues/3200476
 */
class Composer {

  /**
   * The filename of the version hash JSON file.
   *
   * @var string
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  const VERSION_HASH_FILE_NAME = '.composer_version_hash.json';

  /**
   * Regular expresion used to ignore certain paths when hashing a directory.
   *
   * @var string
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  const VERSION_HASH_IGNORE_REGEX = '/^(?:\.|\.\.|\..+|Install.*|Make.*|License.*|.*test.*|psalm\.xml|php.*\.dist|php.*\.xml|.*\.neon)$/i';

  /**
   * An associative of hashes, keyed by versions, grouped by package name.
   *
   * @var array
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  protected static $versionHash;

  /**
   * Generates a base-64 encoded, SHA-256 hash from the contents of a directory.
   *
   * @param string $directory
   *   The directory to hash.
   *
   * @return string|false
   *   The hash for the provided $directory; FALSE on error.
   *
   * @see https://jonlabelle.com/snippets/view/php/generate-md5-hash-for-directory
   *   Original code, modified for use with Drupal Markdown.
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  public static function generateHash($directory) {
    if (!$directory || !($directory = realpath($directory)) || !is_dir($directory)) {
      return FALSE;
    }
    $hashes = [];
    $dir = dir($directory);
    while (FALSE !== ($file = $dir->read())) {
      if (preg_match(static::VERSION_HASH_IGNORE_REGEX, $file)) {
        continue;
      }
      if (is_dir("$directory/$file")) {
        $hashes[$file] = static::generateHash("$directory/$file");
      }
      else {
        $hashes[$file] = sha1_file("$directory/$file");
      }
    }
    $dir->close();
    return Crypt::hashBase64(serialize($hashes));
  }

  /**
   * Retrieves the installed Composer JSON for a specific package.
   *
   * @param string $name
   *   The package name for which version to check is installed.
   * @param array $comparisonJson
   *   Optional. The JSON array to look for if the specified name doesn't exist.
   *   This can happen if installing an older package version where it uses
   *   an older package name. Unless the user explicitly installed that older
   *   package name, Composer will default to the newer/current package name
   *   in its installed.json file.
   *
   * @return string|void
   *   The installed version or NULL if not installed.
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  public static function getInstalledJson($name, array $comparisonJson = []) {
    /** @var \Composer\Autoload\ClassLoader $autoloader */
    $autoloader = \Drupal::service('class_loader');
    if ($name && ($file = $autoloader->findFile('Composer\\Semver\\Semver'))) {
      if (($file = realpath(dirname($file) . "/../../installed.json")) && ($contents = file_get_contents($file)) && ($installedJson = Json::decode($contents))) {
        $packages = array_filter(isset($installedJson['packages']) ? $installedJson['packages'] : $installedJson, function ($package) use ($name) {
          return !empty($package['name']) && $package['name'] === $name;
        });
        if (!$packages && $comparisonJson) {
          $keysToCompare = [
            'description', 'homepage', 'require', 'require-dev', 'autoload',
          ];
          $packages = array_filter($installedJson, function ($package) use ($comparisonJson, $keysToCompare) {
            foreach ($keysToCompare as $key) {
              // Ignore key if not in the original JSON.
              if (!isset($comparisonJson[$key])) {
                continue;
              }
              // Package doesn't have the same key.
              if (!isset($package[$key])) {
                return FALSE;
              }
              // Check whether associative property arrays differ.
              if (is_array($package[$key])) {
                if (DiffArray::diffAssocRecursive($comparisonJson[$key], $package[$key])) {
                  return FALSE;
                }
              }
              elseif ($package[$key] !== $comparisonJson[$key]) {
                return FALSE;
              }
            }
            return TRUE;
          });
        }
        return current($packages) ?: NULL;
      }
    }
  }

  /**
   * Retrieves the installed version for a specific package.
   *
   * @param string $name
   *   The package name for which to retrieve the installed version.
   * @param array $comparisonJson
   *   Optional. The JSON array to look for if the specified name doesn't exist.
   *   This can happen if installing an older package version where it uses
   *   an older package name. Unless the user explicitly installed that older
   *   package name, Composer will default to the newer/current package name
   *   in its installed.json file.
   *
   * @return string|void
   *   The installed version, NULL if not installed.
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  public static function getInstalledVersion($name, array $comparisonJson = []) {
    if ($name && ($installedJson = static::getInstalledJson($name, $comparisonJson)) && !empty($installedJson['version'])) {
      return $installedJson['version'];
    }
  }

  /**
   * Decodes the composer.json file for a given directory and returns its data.
   *
   * @param string $directory
   *   The directory where the composer.json file lives.
   * @param string $name
   *   (Read-only) The package name, extracted from the JSON data.
   *
   * @return array|void
   *   The JSON data of composer.json for the given $directory.
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  public static function getJson($directory, &$name = NULL) {
    if (($file = realpath("$directory/composer.json")) && ($contents = file_get_contents($file)) && ($json = Json::decode($contents))) {
      if (!empty($json['name'])) {
        $name = $json['name'];
      }
      return $json;
    }
  }

  /**
   * Decodes the composer.json file from a given class and returns its data.
   *
   * @param string $className
   *   The name of a class included via Composer's autoloader. Note: this is
   *   primarily used to help reverse engineer where Composer's vendor
   *   directory is without having to actually know where it is.
   * @param string $name
   *   (Read-Only) The package name of composer.json, passed by reference.
   * @param string $file
   *   (Read-Only) The complete path of composer.json, passed by reference.
   *
   * @return array|void
   *   The JSON array of the composer.json file found; NULL otherwise.
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  public static function getJsonFromClass($className, &$name = NULL, &$file = NULL) {
    /** @var \Composer\Autoload\ClassLoader $autoloader */
    $autoloader = \Drupal::service('class_loader');
    if ($file = $autoloader->findFile(ltrim(str_replace('\\\\', '\\', $className), '\\'))) {
      $directory = realpath(dirname($file));
      do {
        $json = NULL;
        if ($directory && is_dir($directory)) {
          if ($json = static::getJson($directory, $name)) {
            $file = "$directory/composer.json";
            return $json;
          }
          $directory = dirname($directory);
        }
      } while ($directory);
    }
  }

  /**
   * Retrieves the pre-generated version hash.
   *
   * @param string $name
   *   Optional. A specific package of hashes to retrieve.
   * @param int $sort
   *   The sort direction to use when returning the hashes. By default, this
   *   uses a descending order to ensure that if there are multiple versions
   *   with the same hash, the latest version will be returned. While this is
   *   a wild assumption, it's also the best that can be done provided that
   *   there wasn't enough significant changes made to the codebase to generate
   *   a unique hash; thus, they're effectively running the latest version of
   *   that hash.
   *
   * @return array
   *   If $name was provided, an associative array of hashes are returned where
   *   the key is the version. If not provided, then the entire version hash
   *   array is returned where the keys are the package names and the values
   *   are an associative array as defined above.
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  public static function getVersionHash($name = NULL, $sort = Semver::SORT_DESC) {
    if (!static::$versionHash) {
      $cache = \Drupal::cache('markdown');
      $cid = 'composer.version:hash';
      if (($cached = $cache->get($cid)) && isset($cached->data)) {
        static::$versionHash = $cached->data;
      }
      else {
        $file = __DIR__ . '/../../' . Composer::VERSION_HASH_FILE_NAME;
        static::$versionHash = file_exists($file) ? Json::decode(file_get_contents($file)) : [];
        $cache->set($cid, static::$versionHash);
      }
    }

    if ($name) {
      $hashes = isset(static::$versionHash[$name]) ? static::$versionHash[$name] : [];
      Semver::ksort($hashes, $sort);
      return $hashes;
    }

    return static::$versionHash;
  }

  /**
   * Retrieves the version based on a provided class name.
   *
   * @param string|object $className
   *   The class name or an object where the class name can be extracted from.
   * @param mixed $default
   *   The default value to provide if no version could be determined.
   * @param bool $cache
   *   Flag indicating whether to use caching to prevent potential and numerous
   *   I/O operations when locating the composer.json file and generating a
   *   hash based on the composer package directory.
   *
   * @return string|mixed
   *   A known version or $default if one could not be determined.
   *
   * @internal
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:4.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3200476
   */
  public static function getVersionFromClass($className, $default = NULL, $cache = TRUE) {
    if (is_object($className)) {
      $className = get_class($className);
    }

    // Ensure it exists in the first place.
    if (!is_string($className) || empty($className) || (!class_exists($className) && !interface_exists($className) && !trait_exists($className))) {
      return $default;
    }

    // Retrieve any cached results.
    $cid = "composer.version:$className";
    if ($cache && ($cached = \Drupal::cache('markdown')->get($cid)) && isset($cached->data)) {
      $json = $cached->data['json'];
      $hash = $cached->data['hash'];
      $name = $cached->data['name'];
      $version = $cached->data['version'];
    }
    else {
      $json = Composer::getJsonFromClass($className, $name, $file);
      $version = Composer::getInstalledVersion($name, $json);
      $hash = !$version ? Composer::generateHash(dirname($file)) : FALSE;
      if ($cache) {
        \Drupal::cache('markdown')->set($cid, [
          'json' => $json,
          'hash' => $hash,
          'name' => $name,
          'version' => $version,
        ]);
      }
    }

    // If a known version was found, use that.
    if (!empty($version)) {
      return $version;
    }

    // Find a matching version for a specific package hash.
    if ($hash && ($versions = static::getVersionHash($name)) && ($version = array_search($hash, $versions, TRUE))) {
      return $version;
    }

    // If no specific version could be determined but a dev-master branch has
    // been specified, use that instead.
    if (!empty($json['extra']['branch-alias']['dev-master'])) {
      return $json['extra']['branch-alias']['dev-master'];
    }

    // Otherwise, return the default value provided.
    return $default;
  }

}
