<?php

namespace Drupal\markdown\Exception;

/**
 * Exception thrown when an installable library is missing its version.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
class MissingVersionException extends \RuntimeException implements InstallablePluginExceptionInterface {
}
