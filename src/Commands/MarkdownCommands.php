<?php

namespace Drupal\markdown\Commands;

use Drush\Drush;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\markdown\Util\Composer;
use Drupal\markdown\Util\Semver;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Markdown commands for Drush 9+.
 *
 * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
 *   No replacement.
 * @see https://www.drupal.org/project/markdown/issues/3103679
 */
class MarkdownCommands extends DrushCommands implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The supported/default packages that will be hashed.
   *
   * @var string[]
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3103679
   */
  protected static $versionHashPackages = [
    'cachethq/emoji',
    'erusev/parsedown',
    'erusev/parsedown-extra',
    'league/commonmark',
    'league/commonmark-ext-autolink',
    'league/commonmark-ext-external-link',
    'league/commonmark-ext-smartpunct',
    'league/commonmark-ext-strikethrough',
    'league/commonmark-ext-table',
    'league/commonmark-ext-task-list',
    // Not needed, all releases have a version that is accessible somewhere.
    //'michelf/php-markdown',
    'rezozero/commonmark-ext-footnotes',
    'webuni/commonmark-attributes-extension',
  ];

  /**
   * The version constraints for supported packages.
   *
   * @var string[]
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3103679
   */
  protected static $versionHashPackageConstraints = [
    'erusev/parsedown' => '<1.5.0',
    'erusev/parsedown-extra' => '<0.6.0',
    'league/commonmark' => '<0.17.1',
  ];

  /**
   * Flag indicating whether the shutdown function was registered.
   *
   * @var bool
   *   TRUE or FALSE
   */
  protected static $shutdownRegistered = FALSE;

  /**
   * A list of temporary directories that were created by this class.
   *
   * @var array
   */
  protected static $tempDirs = [];

  /**
   * The File System service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * MarkdownCommands constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The File System service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A Logger.
   */
  public function __construct(FileSystemInterface $fileSystem, LoggerInterface $logger) {
    parent::__construct();
    $this->fileSystem = $fileSystem;
    $this->logger = $logger;

    if (!static::$shutdownRegistered) {
      drupal_register_shutdown_function(function () {
        foreach (static::$tempDirs as $tempDir) {
          $this->fileSystem->deleteRecursive($tempDir);
        }
      });
      static::$shutdownRegistered = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container = NULL) {
    if (!$container) {
      $container = \Drupal::getContainer();
    }
    return new static(
      $container->get('file_system'),
      method_exists('\\Drush\\Drush', 'logger') ? Drush::logger() : $container->get('logger.channel.default')
    );
  }

  /**
   * Creates a new temporary directory.
   *
   * @return string|void
   *   The temporary directory path.
   */
  protected function createTempDir() {
    $tempDir = 'temporary://markdown_' . \Drupal::time()->getRequestTime() . Crypt::randomBytesBase64(10);
    if (!is_dir($tempDir) && $this->fileSystem->prepareDirectory($tempDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $tempDir = $this->fileSystem->realpath($tempDir);
      static::$tempDirs[] = $tempDir;
      return $tempDir;
    }
  }

  /**
   * Helper function for running a command on the system.
   *
   * @param string $command
   *   The command to run.
   * @param int $exitStatus
   *   (Read-Only) The exit status, passed by reference.
   *
   * @return string
   *   The raw output from the command.
   */
  protected function exec($command, &$exitStatus = NULL) {
    $cwd = getcwd();
    \exec("cd \"$cwd\" && $command", $output, $exitStatus);
    return implode("\n", $output);
  }

  /**
   * Helper function for pretty printing JSON.
   *
   * @param mixed $value
   *   The value to encode.
   *
   * @return string
   *   The pretty printed JSON.
   */
  protected function jsonEncodePrettyPrint($value) {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT) . "\n";
  }

  /**
   * Run the markdown:version-hash hook.
   *
   * @param string $package
   *   The composer package to generate version hashes for.
   *
   * @command markdown:version-hash
   * @option force Flag indicating whether to force generate even if it exits.
   *
   * @return false|void
   *   An error message or nothing if successful.
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   No replacement.
   * @see https://www.drupal.org/project/markdown/issues/3103679
   */
  public function versionHash($package = NULL, array $options = ['force' => FALSE]) {
    $force = !empty($options['force']);
    if (!($tempDir = $this->createTempDir())) {
      return drush_set_error('MARKDOWN_FILE_SYSTEM', $this->t('Unable to create temporary directory.'));
    }

    // Change CWD to the temp directory.
    chdir($tempDir);

    // Initialize a composer project (silence warning).
    $this->exec("composer -n init 2>&1", $exitStatus);
    if ($exitStatus) {
      return drush_set_error('MARKDOWN_COMPOSER_FAILURE', $this->t('Unable to initialize temporary Composer project. Make sure you have "composer" defined in your environment paths.'));
    }

    $versionHashFile = __DIR__ . '/../../' . Composer::VERSION_HASH_FILE_NAME;
    $versionHashJson = file_exists($versionHashFile) ? Json::decode(file_get_contents($versionHashFile)) : [];

    $packages = $package ? array_map('trim', explode(',', $package)) : static::$versionHashPackages;
    natsort($packages);

    // Validate provided packages are supported.
    foreach ($packages as $package) {
      if (!in_array($package, static::$versionHashPackages, TRUE)) {
        return drush_set_error('MARKDOWN_UNSUPPORTED_PACKAGE', $this->t('Package not supported: @package', ['@package' => $package]));
      }
    }

    // Iterate over packages.
    foreach ($packages as $package) {
      $constraints = isset(static::$versionHashPackageConstraints[$package]) ? static::$versionHashPackageConstraints[$package] : NULL;
      $variables = [
        '@constraints' => Markup::create($constraints),
        '@package' => $package,
      ];

      $label = $constraints ? '[@package:@constraints]' : '[@package]';

      $this->logger()->log('status', $this->t("$label Checking latest version information", $variables));

      $output = $this->exec("composer -n --no-ansi --format=json show $package -a 2>&1", $exitStatus);
      if ($exitStatus) {
        return drush_set_error('MARKDOWN_COMPOSER_FAILURE', $this->t('Unable to determine versions for package: @package', $variables));
      }
      $composerJson = Json::decode($output);
      if (!$composerJson) {
        return drush_set_error('MARKDOWN_COMPOSER_FAILURE', $this->t('Unable to JSON decode composer output. Ensure you have the latest version of Composer installed.'));
      }
      $composerJson += ['versions' => []];

      $versions = Semver::sort(array_filter($composerJson['versions'], function ($version) use ($package, $constraints) {
        return strpos($version, 'dev') === FALSE && (!isset($constraints) || Semver::satisfies($version, $constraints));
      }));

      $variables['@version_count'] = count($versions);
      $variables['@missing_version_count'] = count(array_filter($versions, function ($version) use ($versionHashJson, $package) {
        return !isset($versionHashJson[$package][$version]);
      }));

      $this->logger()->log('status', $this->t("$label @version_count versions found (@missing_version_count missing generated hashes)", $variables));

      foreach ($versions as $version) {
        $variables['@current_version'] = $version;

        if (!$force && isset($versionHashJson[$package][$version])) {
          $variables['@current_hash'] = $versionHashJson[$package][$version];
          $this->logger()->log('status', $this->t("[@package:@current_version] @current_hash", $variables));
          continue;
        }

        if (!($packageVersionTempDir = $this->createTempDir())) {
          return drush_set_error('MARKDOWN_FILE_SYSTEM', $this->t('Unable to create temporary directory.'));
        }

        // Change CWD to the temp directory.
        chdir($packageVersionTempDir);

        $output = $this->exec("composer init -n; composer -n --no-ansi require $package:$version --ignore-platform-reqs 2>&1", $exitStatus);
        if ($exitStatus) {
          return drush_set_error('MARKDOWN_COMPOSER_FAILURE', $output);
        }

        $packageVendorDir = "$packageVersionTempDir/vendor/$package";
        if (!is_dir($packageVendorDir)) {
          return drush_set_error('MARKDOWN_COMPOSER_FAILURE', $this->t('Unable to install @package:@current_version', $variables));
        }

        // Change CWD to the package vendor dir.
        chdir($packageVendorDir);

        if (!Composer::getJson($packageVendorDir, $name)) {
          return drush_set_error('MARKDOWN_COMPOSER_FAILURE', $this->t('Unable to read composer.json for @package:@current_version', $variables));
        }

        if (!($hash = Composer::generateHash($packageVendorDir))) {
          return drush_set_error('MARKDOWN_COMPOSER_FAILURE', $this->t('Unable to generate hash for @package:@current_version', $variables));
        }
        $versionHashJson[$package][$version] = $variables['@current_hash'] = $hash;

        // In the event that the actual name of the package is different from
        // the current one, save a copy of that previous package name as well.
        // This can occur for older versions where a namespace change occurred.
        if ($name !== $package) {
          $versionHashJson[$name][$version] = $hash;
        }

        // Save file after each version, just in case it gets interrupted.
        file_put_contents($versionHashFile, $this->jsonEncodePrettyPrint($versionHashJson));
        $this->logger()->log('status', $this->t("[@package:@current_version] @current_hash", $variables));
      }
    }

    // Finally, save the file one last time to ensure everything was written
    // and the entire JSON array is properly sorted.
    ksort($versionHashJson);
    foreach ($versionHashJson as $package => &$hashes) {
      Semver::ksort($hashes);
    }
    file_put_contents($versionHashFile, $this->jsonEncodePrettyPrint($versionHashJson));
  }

}
