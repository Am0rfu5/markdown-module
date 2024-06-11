<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Traits\FormTrait;

/**
 * Mention extension.
 *
 * @MarkdownExtension(
 *   id = "commonmark-mention",
 *   label = @Translation("Mentions"),
 *   description = @Translation("Makes it easy to parse shortened mentions and references like @colinodell to a Twitter URL or #123 to a GitHub issue URL. You can create your own custom syntax by defining which symbol you want to use and how to generate the corresponding URL."),
 *   experimental = @Translation("This extension is currently in development. For more information and to help test it, please see the project issue: <a href=':url' target='_blank'>[#3160927] Add Mentions Extension</a>", arguments = {
 *     ":url" = "https://www.drupal.org/project/markdown/issues/3160927",
 *   }),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\Mention\MentionExtension",
 *       customLabel = "commonmark-mention",
 *       url = "https://commonmark.thephpleague.com/extensions/mentions/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.5 || ^2.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class MentionExtension extends BaseExtension implements PluginFormInterface {

  use FormTrait;

  /**
   * {@inheritdoc}
   */
  public function register($environment) {
    // Intentionally do nothing.
    // @see https://www.drupal.org/project/markdown/issues/3160927
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $parentForm = &$form_state->getParentForm();
    $parentForm['enabled']['#disabled'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Intentionally do nothing. This is just required to be implemented.
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Intentionally do nothing. This is just required to be implemented.
  }

}
