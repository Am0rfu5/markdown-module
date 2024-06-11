<?php

namespace Drupal\markdown\Exception;

/**
 * Base class for exceptions related to markdown operations.
 *
 * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
 *   Use \Drupal\markdown\Exception\MarkdownExceptionInterface instead.
 * @see https://www.drupal.org/project/markdown/issues/3142418
 */
abstract class MarkdownException extends \RuntimeException implements MarkdownExceptionInterface {
}
