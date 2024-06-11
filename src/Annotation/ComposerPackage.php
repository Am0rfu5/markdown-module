<?php

namespace Drupal\markdown\Annotation;

use Composer\InstalledVersions;
use Doctrine\Common\Annotations\AnnotationException;
use Drupal\Core\Url;
use Drupal\markdown\Util\Composer;

/**
 * Annotation for providing an installable library via Composer.
 *
 * @Annotation
 * @Target("ANNOTATION")
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 * @see https://www.drupal.org/project/markdown/issues/3200476
 */
class ComposerPackage extends InstallableLibrary {

  /**
   * Detects the installed version of a Composer package.
   *
   * @return string|void
   *   The detected version of the Composer package or NULL if not enabled.
   */
  protected function detectVersion() {
    $id = $this->getId();

    // Composer 1 support.
    // @todo Remove in 4.0.0.
    // @see https://www.drupal.org/project/markdown/issues/3200476
    if (!class_exists('\Composer\InstalledVersions')) {
      return Composer::getInstalledVersion($id) ?: Composer::getVersionFromClass($this->object);
    }

    // Composer 2+ runtime installed versions support.
    // @see https://getcomposer.org/doc/07-runtime.md#knowing-the-version-of-package-x
    if (InstalledVersions::isInstalled($id)) {
      return InstalledVersions::getPrettyVersion($id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableVersions() {
    if (!isset($this->availableVersions)) {
      $this->availableVersions = [];
      $id = $this->getId();
      // To ensure we have the latest versions at all times, use the
      // https://repo.packagist.org/p/[vendor]/[package].json URL which are
      // static files and not cached.
      $json = $this->requestJson(sprintf('https://repo.packagist.org/p/%s.json', $id));
      if (!empty($json['packages'][$id])) {
        $this->availableVersions = array_keys($json['packages'][$id]);
      }
    }
    return $this->availableVersions;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstallCommand() {
    return 'composer require ' . $this->id;
  }

  /**
   * Retrieves the package name from the library identifier.
   *
   * @return string|void
   *   The package name.
   */
  public function getPackageName() {
    if (($parts = explode('/', $this->id, 2))) {
      return $parts[1];
    }
  }

  /**
   * Retrieves the vendor name from the library identifier.
   *
   * @return string|void
   *   The vendor name.
   */
  public function getVendorName() {
    if (($parts = explode('/', $this->id, 2))) {
      return $parts[0];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getVersionUrl($version = NULL, array $options = []) {
    if (!$version) {
      $version = $this->version;
    }
    if (!isset($this->versionUrls[$version])) {
      $this->versionUrls[$version] = FALSE;
      if ($this->isKnownVersion($version) && !$this->isDev($version) && ($json = $this->requestPackage())) {
        $repository = !empty($json['repository']) ? $json['repository'] : sprintf('https://packagist.org/packages/%s', $this->getId());
        if (!isset($json['versions'][$version])) {
          $version = "v$version";
          $this->versionUrls[$version] = FALSE;
        }
        if (isset($json['versions'][$version])) {
          if (!isset($options['attributes']['target'])) {
            $options['attributes']['target'] = '_blank';
          }
          switch (parse_url($repository, PHP_URL_HOST)) {
            case 'github.com':
              $uri = sprintf('%s/releases/%s', $repository, $version);
              break;

            case 'packagist.org':
              $uri = sprintf('%s#%s', $repository, $version);
              break;

            default:
              $uri = $repository;
          }
          $this->versionUrls[$version] = Url::fromUri($uri, $options);
        }
      }
    }
    return $this->versionUrls[$version];
  }

  /**
   * Retrieves the package JSON data.
   *
   * @return array
   *   The package JSON data.
   */
  protected function requestPackage() {
    // When requesting package information, use the normal API URL which
    // includes a lot more metadata about the package. This, unfortunately,
    // cached and only refreshed once every 12 hours.
    $json = $this->requestJson(sprintf('https://packagist.org/packages/%s.json', $this->getId()));
    return !empty($json['package']) ? $json['package'] : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateIdentifier(Identifier $id) {
    if (!$id->contains('/')) {
      throw AnnotationException::semanticalError('A ComposerPackage definition must contain a forward-slash (/) in its identifier so that it represents the correct {vendor}/{package} name.');
    }
  }

}
