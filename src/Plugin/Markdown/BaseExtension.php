<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Core\Config\Schema\Mapping;
use Drupal\markdown\PluginManager\ExtensionManager;
use Drupal\markdown\Traits\EnabledPluginTrait;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Base class for markdown extensions.
 *
 * @property \Drupal\markdown\Annotation\MarkdownExtension $pluginDefinition
 * @method \Drupal\markdown\Annotation\MarkdownExtension getPluginDefinition()
 */
abstract class BaseExtension extends InstallablePluginBase implements ExtensionInterface {

  use EnabledPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function enabledByDefault() {
    return FALSE;
  }

  /**
   * Validates extension settings.
   *
   * @param array $settings
   *   The extension settings to validate.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validation execution context.
   */
  public static function validateSettings(array $settings, ExecutionContextInterface $context) {
    try {
      $object = $context->getObject();
      $parent = $object instanceof Mapping ? $object->getParent() : NULL;
      $extensionId = $parent instanceof Mapping && ($id = $parent->get('id')) ? $id->getValue() : NULL;
      $extensionManager = ExtensionManager::create();

      if (!$extensionId || !$extensionManager->hasDefinition($extensionId)) {
        throw new \RuntimeException(sprintf('Unknown markdown extension: "%s"', $extensionId));
      }

      $extension = $extensionManager->createInstance($extensionId);

      // Immediately return if extension doesn't have any settings.
      if (!($extension instanceof SettingsInterface)) {
        return;
      }

      $defaultSettings = $extension::defaultSettings($extension->getPluginDefinition());
      $unknownSettings = array_keys(array_diff_key($settings, $defaultSettings));
      if ($unknownSettings) {
        throw new \RuntimeException(sprintf('Unknown extension settings: %s', implode(', ', $unknownSettings)));
      }
    }
    catch (\RuntimeException $exception) {
      $context->addViolation($exception->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();

    // Only provide settings if extension is enabled.
    if ($this instanceof SettingsInterface && !$this->isEnabled()) {
      $configuration['settings'] = [];
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function isBundled() {
    if (($parser = $this->getParser()) && $parser instanceof ExtensibleParserInterface) {
      return in_array($this->getPluginId(), $parser->getBundledExtensionIds(), TRUE);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requires() {
    $extensionRequirements = $this->pluginDefinition->getRequirementsByType('extension');
    return array_map(function ($requirement) {
      return $requirement->getTypeId();
    }, $extensionRequirements);
  }

  /**
   * {@inheritdoc}
   */
  public function requiredBy() {
    // @todo Figure out a better way to handle this.
    return isset($this->pluginDefinition['_requiredBy']) ? $this->pluginDefinition['_requiredBy'] : [];
  }

}
