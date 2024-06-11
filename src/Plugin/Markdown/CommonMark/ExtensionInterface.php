<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark;

use Drupal\markdown\Plugin\Markdown\ExtensionInterface as MarkdownExtensionInterface;

/**
 * Interface for CommonMark Extensions.
 */
interface ExtensionInterface extends MarkdownExtensionInterface {

  /**
   * Allows the extension to register itself with the CommonMark Environment.
   *
   * @param \League\CommonMark\Environment\ConfigurableEnvironmentInterface|\League\CommonMark\ConfigurableEnvironmentInterface|\League\CommonMark\Environment $environment
   *   The CommonMark environment. The exact object passed here has changed
   *   namespaces over various versions. It is unlikely to be incompatible,
   *   however, explicit typechecking via instanceof may be needed.
   */
  public function register($environment);

}
