<?php

namespace Drupal\markdown\Annotation;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Error;
use Drupal\markdown\Traits\HttpClientTrait;
use Drupal\markdown\Util\Semver;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class InstallableLibrary extends AnnotationObject {

  use HttpClientTrait;
  use InstallablePluginTrait;

  /**
   * Optional. A customized human-readable label for the library.
   *
   * Note: this may be necessary if there is already a plugin or library using
   * the same name and it needs to differentiate itself further. An example of
   * this is checking a object requirement that is bundled as an extension
   * inside the main library.
   *
   * @var string|null
   */
  public $customLabel;

  /**
   * The version with extra metadata.
   *
   * @var string
   */
  public $versionExtra;

  /**
   * All available versions, regardless of stability.
   *
   * @var string[]
   */
  protected $availableVersions;

  /**
   * The latest version, based on currently available versions and stability.
   *
   * @var string[]
   */
  protected $latestVersion = [];

  /**
   * An array of newer versions, based on currently set version and stability.
   *
   * @var array
   */
  protected $newerVersions = [];

  /**
   * A specific version URL, if known.
   *
   * @var \Drupal\Core\Url[]
   */
  protected $versionUrls = [];

  /**
   * The last exception thrown when attempting to initiate a request.
   *
   * @var \GuzzleHttp\Exception\GuzzleException
   */
  protected $requestException;

  /**
   * {@inheritdoc}
   */
  public function __construct($values = []) {
    parent::__construct($values);

    // Detect version of PHP extension.
    if (!isset($this->version)) {
      $version = $this->detectVersion();
      if (is_array($version) && count($version) === 2) {
        list($raw, $extra) = $version;
        $this->version = $raw;
        $this->versionExtra = $extra;
      }
      else {
        $this->version = $version;
      }
    }
  }

  public function createObjectRequirement(InstallablePlugin $definition = NULL) {
    if ($this->object) {
      $name = $this->getLink($this->customLabel) ?: $this->getId();
      if (!$name && $definition) {
        $name = $definition->getLink($this->customLabel) ?: $definition->getId();
      }
      return InstallableRequirement::create([
        'value' => $this->object,
        'constraints' => ['Installed' => ['name' => $name]],
      ]);
    }
  }

  /**
   * Detects the version of the library installed.
   *
   * @return string|string[]|void
   *   The detected version, if any. If an array is returned, the first item
   *   should be the raw version provided by the library, the second item
   *   may contain additional meta information appended to the raw version
   *   to denote a more detailed version (i.e. bundled with another library).
   */
  protected function detectVersion() {
  }

  /**
   * Retrieves the available versions of the library.
   *
   * Note: this will likely make an HTTP request.
   *
   * @return string[]
   *   The available versions.
   */
  public function getAvailableVersions() {
    return [];
  }

  /**
   * Retrieves the CLI command used to install the library, if any.
   *
   * @return string|void
   *   A install command to be used to install the library, if any.
   */
  public function getInstallCommand() {
  }

  /**
   * Retrieves the latest version based on available versions.
   *
   * Note: this will likely make an HTTP request.
   *
   * @param string $minimumStability
   *   Optional. The minimum stability to determine which latest version to
   *   retrieve.
   *
   * @return string|void
   *   The latest version, if successful.
   */
  public function getLatestVersion($minimumStability = 'stable') {
    if (!isset($this->latestVersion[$minimumStability])) {
      $this->latestVersion[$minimumStability] = Semver::latestVersion($this->getNewerVersions($minimumStability), "@$minimumStability");
    }
    return $this->latestVersion[$minimumStability];
  }

  /**
   * Retrieves the newer versions of the library.
   *
   * Note: this will likely make an HTTP request.
   *
   * @param string $minimumStability
   *   Optional. The minimum stability to determine which latest version to
   *   retrieve.
   *
   * @return string[]
   *   The newer versions.
   */
  public function getNewerVersions($minimumStability = 'stable') {
    $version = ($this->version ?: '*') . "@$minimumStability";
    if (!isset($this->newerVersions[$version])) {
      $availableVersions = $this->getAvailableVersions();
      $this->newerVersions[$version] = Semver::sort($this->version ? Semver::satisfiedBy($availableVersions, ">$version") : $availableVersions);
    }
    return $this->newerVersions[$version];
  }

  /**
   * Retrieves the current status of the library.
   *
   * @param bool $long
   *   Flag indicating whether to use longer explanations as indicated by
   *   the individual property values.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The human readable status.
   */
  public function getStatus($long = FALSE) {
    // Immediately return if there are requirement violations (not installed).
    if ($this->requirementViolations) {
      return t('Not Installed');
    }

    // Determine the library status.
    if ($this->hasRequestFailure()) {
      if ($long) {
        return t('Request Failure. @explanation', [
          '@explanation' => $this->requestException->getMessage(),
        ]);
      }
      return t('Request Failure');
    }

    if ($this->getNewerVersions()) {
      if ($this->deprecated) {
        return t('Deprecated');
      }
      if ($this->preferred) {
        return t('Update Available');
      }
      return t('Upgrade Available');
    }

    if ($this->isKnownVersion()) {
      if ($this->deprecated && !$this->preferred) {
        if ($long && $this->deprecated !== TRUE) {
          return t('Upgrade Available. @explanation', [
            '@explanation' => $this->deprecated,
          ]);
        }
        return t('Upgrade Available');
      }

      if ($this->deprecated && $this->preferred) {
        if ($long && $this->deprecated !== TRUE) {
          return t('Deprecated. @explanation', [
            '@explanation' => $this->deprecated,
          ]);
        }
        return t('Deprecated');
      }

      if ($this->experimental) {
        if ($long && $this->experimental !== TRUE) {
          return t('Experimental. @explanation', [
            '@explanation' => $this->experimental,
          ]);
        }
        return t('Experimental');
      }

      if ($this->isPrerelease()) {
        return t('Prerelease');
      }

      if ($this->isDev()) {
        return t('Development Release');
      }

      return t('Up to date');
    }

    return t('Unknown');
  }

  /**
   * Retrieves the version as a link to a specific release.
   *
   * @param string $version
   *   A specific version to retrieve a URL for. If not specified, it will
   *   default to the currently installed version.
   * @param string|\Drupal\Component\Render\MarkupInterface $label
   *   The label to use for the link. If not specified, it will default to
   *   the versionExtra or version value.
   * @param array $options
   *   Optional. Options to pass to the creation of the URL object.
   *
   * @return \Drupal\Core\GeneratedLink|void
   *   The link to the version.
   */
  public function getVersionLink($version = NULL, $label = NULL, array $options = []) {
    if (!$version) {
      $version = $this->version;
      if (!isset($label)) {
        $label = $this->versionExtra ?: $this->version;
      }
    }
    if ($version && ($url = $this->getVersionUrl($version, $options))) {
      if (!isset($label)) {
        $label = $version;
      }
      return Link::fromTextAndUrl($label, $url)->toString();
    }
  }

  /**
   * Retrieves the version as a URL.
   *
   * @param string $version
   *   A specific version to retrieve a URL for. If not specified, it will
   *   default to the currently installed version.
   * @param array $options
   *   Optional. Options to pass to the creation of the URL object.
   *
   * @return \Drupal\Core\Url|false
   *   A specific version URL, if set; FALSE otherwise.
   */
  public function getVersionUrl($version = NULL, array $options = []) {
    return FALSE;
  }

  /**
   * Indicates whether there is an issue performing requests for the library.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function hasRequestFailure() {
    return !!$this->requestException;
  }

  /**
   * Indicates whether this is a known version.
   *
   * @param string $version
   *   A specific version to test. If not specified, it will default to the
   *   currently installed version, if any.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isKnownVersion($version = NULL) {
    if (!isset($version)) {
      $version = $this->version;
    }
    return $version && in_array($version, $this->getAvailableVersions(), TRUE);
  }

  /**
   * Indicates whether this is an alpha version.
   *
   * @param string $version
   *   A specific version to test. If not specified, it will default to the
   *   currently installed version, if any.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isAlpha($version = NULL) {
    if (!isset($version)) {
      $version = $this->version;
    }
    return $version && Semver::satisfies($version, '@alpha') && !Semver::satisfies($version, '@beta') && !Semver::satisfies($version, '@RC') && !Semver::satisfies($version, '@stable');
  }

  /**
   * Indicates whether this is an alpha version.
   *
   * @param string $version
   *   A specific version to test. If not specified, it will default to the
   *   currently installed version, if any.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isDev($version = NULL) {
    if (!isset($version)) {
      $version = $this->version;
    }
    return $version && Semver::satisfies($version, '@dev') && !Semver::satisfies($version, '@alpha') && !Semver::satisfies($version, '@beta') && !Semver::satisfies($version, '@RC') && !Semver::satisfies($version, '@stable');
  }

  /**
   * Indicates whether this is a beta version.
   *
   * @param string $version
   *   A specific version to test. If not specified, it will default to the
   *   currently installed version, if any.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isBeta($version = NULL) {
    if (!isset($version)) {
      $version = $this->version;
    }
    return $version && !Semver::satisfies($version, '@alpha') && Semver::satisfies($version, '@beta') && !Semver::satisfies($version, '@RC') && !Semver::satisfies($version, '@stable');
  }

  /**
   * Indicates whether this is a release candidate version.
   *
   * @param string $version
   *   A specific version to test. If not specified, it will default to the
   *   currently installed version, if any.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isRc($version = NULL) {
    if (!isset($version)) {
      $version = $this->version;
    }
    return $version && !Semver::satisfies($version, '@alpha') && !Semver::satisfies($version, '@beta') && Semver::satisfies($version, '@RC') && !Semver::satisfies($version, '@stable');
  }

  /**
   * Indicates whether this is any prerelease version.
   *
   * @param string $version
   *   A specific version to test. If not specified, it will default to the
   *   currently installed version, if any.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isPrerelease($version = NULL) {
    if (!isset($version)) {
      $version = $this->version;
    }
    return $version && (Semver::satisfies($version, '@alpha') || Semver::satisfies($version, '@beta') || Semver::satisfies($version, '@RC')) && !Semver::satisfies($version, '@stable');
  }

  /**
   * Indicates whether this is a stable version.
   *
   * @param string $version
   *   A specific version to test. If not specified, it will default to the
   *   currently installed version, if any.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isStable($version = NULL) {
    if (!isset($version)) {
      $version = $this->version;
    }
    return $version && Semver::satisfies($version, '@stable');
  }

  /**
   * Requests a URL.
   *
   * @param string $url
   *   The URL being requested.
   *
   * @return \Drupal\Core\Cache\CacheableResponse
   *   A cacheable response.
   */
  protected function request($url) {
    $this->requestException = NULL;

    // Clean the URL.
    $extension = ltrim(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION), '.');
    $cleanUrl = Html::cleanCssIdentifier(preg_replace('/' . preg_quote($extension, '/') . '$/', '', $url)) . ($extension ? ".$extension" : '');

    $cid = 'installable_library:' . $this->id . ':' . $cleanUrl;
    $cache = \Drupal::cache('markdown');

    // If there is a valid 24hr cached response in the database, use it.
    if (($cached = $cache->get($cid)) && isset($cached->data)) {
      return $cached->data;
    }

    // Prepare the request.
    $content = NULL;
    $options = [];
    $directory = 'public://installable_plugins/library';
    \Drupal::service('file_system')->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    // If there's a cached file of the request, attempt to use it if its
    // modified time is still valid and acknowledged by the responding server.
    if (file_exists("$directory/$cleanUrl")) {
      $content = file_get_contents("$directory/$cleanUrl");
      $options['headers']['If-Modified-Since'] = date('r', filemtime("$directory/$cleanUrl"));
    }

    // Make the request.
    try {
      $response = static::httpClient()->get($url, $options);
      $statusCode = $response->getStatusCode();

      // New content.
      if ($statusCode >= 200 && $statusCode < 300) {
        $content = $response->getBody()->getContents();
        file_put_contents("$directory/$cleanUrl", $content);

      }
      // If anything 400 or over is returned, throw an exception so its logged.
      elseif ($statusCode >= 400) {
        $request = new Request('GET', $url, isset($options['headers']) ? $options['headers'] : []);
        throw new RequestException($response->getBody()->getContents(), $request, $response);
      }

      // Create a cacheable response.
      $cacheableResponse = new CacheableResponse($content, $statusCode, $response->getHeaders());

      // Cache response in the database. The TTL value defaults to one day,
      // but allow it to be overrideable via settings.
      $ttl = Settings::get('installable_library_request_ttl', 86400);
      $cache->set($cid, $cacheableResponse, \Drupal::time()->getRequestTime() + $ttl);
    }
    catch (GuzzleException $exception) {
      \Drupal::logger('markdown')->warning('%type: @message in %function (line %line of %file).<pre><code>@backtrace_string</code></pre>', Error::decodeException($exception));
      $this->requestException = $exception;
      $cacheableResponse = new CacheableResponse($exception->getMessage(), 500);
    }

    return $cacheableResponse;
  }

  /**
   * Retrieves JSON from a URL.
   *
   * @param string $url
   *   The URL where to retrieve JSON from.
   *
   * @return array|void
   *   An array of JSON if successful, NULL otherwise.
   */
  protected function requestJson($url) {
    $response = $this->request($url);
    $statusCode = $response->getStatusCode();
    if ($statusCode >= 200 && $statusCode < 400 && ($contents = $response->getContent()) && ($json = Json::decode($contents))) {
      return $json;
    }
  }

  /**
   * Retrieves XML from a URL.
   *
   * @param string $url
   *   The URL where to retrieve JSON from.
   * @param bool $array
   *   Flag indicating whether to return an array or a DOM object.
   *
   * @return \DOMDocument|array|void
   *   A DOMDocument or array (if $array is specified) of XML if successful,
   *   NULL otherwise.
   *
   * @noinspection PhpComposerExtensionStubsInspection
   * @sse https://git.drupalcode.org/project/drupal/-/blob/5c4e2e76a1c7972c4b03496ab51846dad63aa762/core/modules/system/system.install#L197
   */
  protected function requestXml($url, $array = FALSE) {
    $response = $this->request($url);
    $statusCode = $response->getStatusCode();
    if ($statusCode >= 200 && $statusCode < 400 && ($contents = $response->getContent())) {
      if ($array) {
        return (array) json_decode(json_encode(simplexml_load_string($contents)), TRUE);
      }
      elseif (($dom = new \DOMDocument()) && $dom->loadXML($contents)) {
        return $dom;
      }
    }
  }

}
