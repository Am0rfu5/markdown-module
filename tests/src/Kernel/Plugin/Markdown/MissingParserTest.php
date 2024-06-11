<?php

namespace Drupal\Tests\markdown\Kernel\Plugin\Markdown;

use Drupal\Tests\markdown\Kernel\MarkdownKernelTestBase;

/**
 * Tests things related to the missing markdown parser.
 *
 * @group markdown
 */
class MissingParserTest extends MarkdownKernelTestBase {

  /**
   * An instance of the missing markdown parser.
   *
   * @var \Drupal\markdown\Plugin\Markdown\MissingParser
   */
  protected $parser;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->parser = $this->container->get('plugin.manager.markdown.parser')
      ->createInstance('_missing_parser');
    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Tests rendering an installable library.
   */
  public function testRenderInstallableLibrary() {
    $build = [
      '#theme' => 'installable_library',
      '#plugin' => $this->parser,
      '#library' => FALSE,
    ];
    $this->assertStringContainsString('<strong>Library is missing</strong>', $this->renderer->renderRoot($build));
  }

}
