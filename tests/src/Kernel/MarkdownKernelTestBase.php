<?php

namespace Drupal\Tests\markdown\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Provides a base class for Markdown kernel tests.
 */
abstract class MarkdownKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'markdown',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'markdown']);
  }

}
