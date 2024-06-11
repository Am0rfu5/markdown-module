<?php

namespace Drupal\markdown\Annotation;

use Doctrine\Common\Annotations\AnnotationException;
use Drupal\Core\Url;

/**
 * PeclExtension Annotation.
 *
 * @Annotation
 * @Target("ANNOTATION")
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
class PeclExtension extends InstallableLibrary {

  /**
   * An associative array of package information, keyed by version.
   *
   * @var array[]
   */
  protected $packageInfo = [];

  /**
   * {@inheritdoc}
   */
  public function __construct($values = []) {
    parent::__construct($values);

    // Add the necessary PHP requirement.
    if (($info = $this->getPackageInfo()) && !empty($info['dependencies']['required']['php']['min'])) {
      $this->requirements[] = InstallableRequirement::create([
        'value' => PHP_VERSION,
        'constraints' => [
          'Version' => [
            'name' => 'PHP',
            'value' => '>=' . $info['dependencies']['required']['php']['min'],
          ],
        ],
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function detectVersion() {
    if (($name = $this->getName()) && extension_loaded($name) && ($version = phpversion($name))) {
      // Extract any compiled "library" version associated with the extension.
      // @todo Revisit this to see if there's a better way.
      ob_start();
      phpinfo(INFO_MODULES);
      $contents = ob_get_contents();
      ob_clean();
      preg_match('/(lib-?' . preg_quote($name, '/') . ').*?v?\s?(\d+\.\d+(?:\.\d+)?)/', $contents, $matches);
      if (!empty($matches[2]) && (!$version || $version !== $matches[2])) {
        $libName = rtrim($matches[1], '-');
        $libVersion = $matches[2];
        $versionExtra = "$version+$libName-$libVersion";
      }
      return isset($versionExtra) ? [$version, $versionExtra] : $version;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableVersions() {
    if (!isset($this->availableVersions)) {
      $this->availableVersions = [];
      // @see https://github.com/php/web-pecl/blob/e98cb34ebcb26b75b4001d1b3458afdad6ba6f83/src/Rest.php#L519-L520
      if (($name = $this->getName()) && ($data = $this->requestXml(sprintf('https://pecl.php.net/rest/r/%s/allreleases.xml', $name), TRUE)) && !empty($data['r'])) {
        $this->availableVersions = array_column($data['r'], 'v');
      }
    }
    return $this->availableVersions;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstallCommand() {
    return 'pecl install ' . $this->getName();
  }

  /**
   * Retrieves the name of a PHP extension.
   *
   * The name is extracted from a plugin identifier that starts with "ext-".
   *
   * @return string|null
   *   The PHP extension name, if it exists.
   */
  public function getName() {
    return $this->id->removeLeft('ext-');
  }

  /**
   * Retrieves the package information for the PECL package.
   *
   * @param string|null $version
   *   A specific version of package information to retrieve. If not specified,
   *   it will default to the currently installed version or the latest version
   *   available if not installed.
   *
   * @return array
   *   An associative array of PECL package information.
   */
  public function getPackageInfo($version = NULL) {
    // Attempt to use installed version if none was explicitly specified.
    if (!$version && $this->version) {
      $version = $this->version;
    }
    // Attempt to use latest version if none was explicitly specified.
    elseif (!$version && ($latestVersion = $this->getLatestVersion())) {
      $version = $latestVersion;
    }

    // Immediately return if version couldn't be determined.
    if (!$version) {
      return [];
    }

    if (!isset($this->packageInfo[$version])) {
      $this->packageInfo[$version] = [];
      // @see https://github.com/php/web-pecl/blob/e98cb34ebcb26b75b4001d1b3458afdad6ba6f83/src/Rest.php#L519-L520
      if (($name = $this->getName()) && ($data = $this->requestXml(sprintf('https://pecl.php.net/rest/r/%s/package.%s.xml', $name, $version), TRUE))) {
        $this->packageInfo[$version] = $data;
      }
    }
    return $this->packageInfo[$version];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(array $options = []) {
    if (!$this->url && ($name = $this->getName())) {
      $this->url = sprintf('https://pecl.php.net/package/%s', $name);
    }
    return parent::getUrl($options);
  }

  /**
   * {@inheritdoc}
   */
  public function getVersionUrl($version = NULL, array $options = []) {
    if (!isset($version)) {
      $version = $this->version ?: '';
    }
    if (!isset($this->versionUrls[$version])) {
      $this->versionUrls[$version] = FALSE;
      if ($this->isKnownVersion($version) && !$this->isDev($version) && ($name = $this->getName())) {
        if (!isset($options['attributes']['target'])) {
          $options['attributes']['target'] = '_blank';
        }
        $this->versionUrls[$version] = Url::fromUri(sprintf('https://pecl.php.net/package/%s/%s', $name, $version), $options);
      }
    }
    return $this->versionUrls[$version];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateIdentifier(Identifier $id) {
    if (!$id->startsWith('ext-')) {
      throw AnnotationException::semanticalError('A PeclExtension definition must prefix its identifier with "ext-".');
    }
  }

}
