<?php

namespace Drupal\markdown\Annotation;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\BooleanData;
use Drupal\Core\TypedData\Plugin\DataType\FloatData;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\Validation\ExecutionContext;
use Drupal\Core\Validation\DrupalTranslator;
use Drupal\markdown\PluginManager\ExtensionManager;
use Drupal\markdown\PluginManager\ParserManager;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Markdown Requirement Annotation.
 *
 * @Annotation
 * @Target("ANNOTATION")
 *
 * @property \Drupal\markdown\Annotation\Identifier $id
 *   Optional. Note: if this contains a colon (:), it will be treated as a
 *   type based identifier, where everything prior to the colon is
 *   considered the type and everything following the colon is considered
 *   the identifier, relative in context with the type. Available types are:
 *   - parser:<parserPluginId>
 *   - extension<extensionPluginId>
 *   - filter:<filterPluginId>
 *   - service:<service.id>
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
class InstallableRequirement extends AnnotationObject {

  /**
   * An array of arguments to be passed to the callback.
   *
   * @var array
   */
  public $arguments = [];

  /**
   * A callback invoked to retrieve the value used to validate the requirement.
   *
   * Note: this is only used if there have been constraints set to validate.
   *
   * If a value is not supplied, then the requirement will simply check whether
   * the provider is installed.
   *
   * If the value is supplied and it isn't callable, it will check whether
   * the provider type is a filter, service, markdown-extension,
   * markdown-library, or markdown-parser and then see if it is a publicly
   * accessible method on the provider's object. Optionally, a method name
   * can be prefixed with a double-colon (::) to ensure no matching static
   * callable function with a similar name is used.
   *
   * @var string
   */
  public $callback;

  /**
   * An array of validation constraints used to validate the callable value.
   *
   * @var array
   *
   * @see \Drupal\Core\TypedData\TypedData::getConstraints().
   */
  public $constraints = [];

  /**
   * The name of the constraint, if any.
   *
   * Note: this will automatically be determined if using a typed identifier
   * and not already provided.
   *
   * @var string
   */
  public $name;

  /**
   * The value used for constraints.
   *
   * Note: If this value is explicitly provided, then any callback set will
   * be ignored.
   *
   * @var mixed
   */
  public $value;

  /**
   * Retrieves the object defined by id/type.
   *
   * @return mixed|void
   *   The object defined by id/type.
   *
   * @noinspection PhpDocMissingThrowsInspection
   */
  public function getObject() {
    if ($this->isTyped()) {
      $container = \Drupal::getContainer();
      list($type, $id) = $this->listTypeId();
      switch ($type) {
        case 'parser':
          if (($parserManager = ParserManager::create($container)) && $parserManager->hasDefinition($id)) {
            if (!isset($this->name)) {
              $definition = $parserManager->getDefinition($id);
              if ($library = $definition->getInstalledLibrary() ?: $definition->getPreferredLibrary()) {
                $this->name = $library->getLink();
              }
            }
            return $parserManager->createInstance($id);
          }
          break;

        case 'extension':
          if (($extensionManager = ExtensionManager::create($container)) && $extensionManager->hasDefinition($id)) {
            if (!isset($this->name)) {
              $definition = $extensionManager->getDefinition($id);
              if ($library = $definition->getInstalledLibrary() ?: $definition->getPreferredLibrary()) {
                $this->name = $library->getLink();
              }
            }
            return $extensionManager->createInstance($id);
          }
          break;

        case 'filter':
          /** @var \Drupal\filter\FilterPluginManager $filterManager */
          if (($filterManager = $container->get('plugin.manager.filter')) && $filterManager->hasDefinition($id)) {
            if (!isset($this->name)) {
              $this->name = t('Filter "@id"', ['@id' => $id]);
            }
            /* @noinspection PhpUnhandledExceptionInspection */
            return $filterManager->createInstance($id);
          }
          break;

        case 'service':
          if ($container->has($id)) {
            if (!isset($this->name)) {
              $this->name = t('Service "@id"', ['@id' => $id]);
            }
            return $container->get($id);
          }
          break;
      }
    }
  }

  /**
   * Retrieves the identifier type, if any.
   *
   * @return string|void
   *   The identifier type, if any.
   */
  public function getType() {
    if ($this->isTyped() && ($type = $this->listTypeId()[0])) {
      return $type;
    }
  }

  /**
   * Retrieves the typed identifier, if any.
   *
   * @return string|void
   *   The typed identifier, if any.
   */
  public function getTypeId() {
    if ($this->isTyped() && ($type = $this->listTypeId()[1])) {
      return $type;
    }
  }

  /**
   * Indicates whether the plugin has a typed identifier.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isTyped() {
    return $this->id && strpos($this->id, ':') !== FALSE;
  }

  /**
   * Retrieves the split typed identifier.
   *
   * @return string[]|void
   *   An indexed array with type values: type, id; intended to be
   *   used with list().
   */
  public function listTypeId() {
    if ($this->isTyped()) {
      return explode(':', $this->id, 2);
    }
  }

  /**
   * Validates the requirement.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   A list of constraint violations. If the list is empty, validation
   *   succeeded.
   */
  public function validate() {
    $object = $this->getObject();

    if (isset($this->name)) {
      foreach ($this->constraints as $name => &$constraint) {
        if ($name !== 'Version') {
          continue;
        }

        if (!is_array($constraint)) {
          $constraint = ['value' => $constraint];
        }

        if (!isset($constraint['name'])) {
          $constraint['name'] = $this->name;
        }
      }
    }

    if (!isset($this->value)) {
      if ($this->callback) {
        if ($object) {
          $callback = [$object, ltrim($this->callback, ':')];
          $this->value = call_user_func_array($callback, $this->arguments);
        }
        else {
          $this->value = call_user_func_array($this->callback, $this->arguments);
        }
        if (!$this->constraints) {
          $this->constraints = ['NotNull' => []];
        }
      }
      else {
        $this->value = $object ? [$this->getId()] : [];
        if (!$this->constraints) {
          $this->constraints = ['Count' => ['min' => 1, 'max' => 1]];
        }
      }
    }

    // If returned value is typed data, add the conditions to its definition.
    if (($value = $this->value) instanceof TypedDataInterface) {
      $typed = $this->value;
      $definition = $typed->getDataDefinition();
      foreach ($this->constraints as $name => $options) {
        $definition->addConstraint($name, $options);
      }
    }
    elseif (is_array($value) || $value instanceof \Traversable) {
      // Don't use config based types. Bug in earlier versions of core.
      // @see https://www.drupal.org/project/drupal/issues/1928868.
      $valueType = is_scalar(current($value)) ? gettype(current($value)) : 'string';
      $itemDefinition = \Drupal::typedDataManager()->createDataDefinition($valueType);
      $definition = new ListDataDefinition([], $itemDefinition);
      $definition->setConstraints($this->constraints);
      $typed = ItemList::createInstance($definition);
      $typed->setValue($value);
    }
    // Otherwise, create new typed data.
    else {
      $valueType = is_scalar($value) ? gettype($value) : 'string';
      $definition = DataDefinition::create($valueType)
        ->setConstraints($this->constraints);
      switch ($valueType) {
        case 'boolean':
          $typed = BooleanData::createInstance($definition);
          break;

        case 'float':
          $typed = FloatData::createInstance($definition);
          break;

        case 'integer':
          $typed = IntegerData::createInstance($definition);
          break;

        case 'string':
        default:
          $typed = StringData::createInstance($definition);
          $value = (string) $value;
          break;
      }
      $typed->setValue($value);
    }

    // Attempt to validate the requirement constraints.
    try {
      return $typed->validate();
    }
    // In the event that one of the constraints cannot be found, treat it as
    // if it's a failed requirement and pass the message as a violation.
    catch (PluginNotFoundException $exception) {
      // See if a global was set in markdown_requirements().
      // @todo This is currently only needed because its bundled with the
      //   markdown module; remove when moved to a standalone upstream project
      //   https://www.drupal.org/project/installable_plugins
      global $_markdown_requirements;

      $message = $exception->getMessage();
      $violationList = new ConstraintViolationList();

      // The exception to this exception is when it is attempting to find
      // constraints provided by this module, which may not yet be installed.
      // In this case, the constraints must be validated manually.
      // @see markdown_requirements()
      // @todo This is currently only needed because its bundled with the
      //   markdown module; remove when moved to a standalone upstream project
      //   https://www.drupal.org/project/installable_plugins
      $markdownConstraints = ['Installed', 'Exists', 'Version'];
      if ($_markdown_requirements === 'install' && preg_match('/(?:Plugin ID |The )\s*[\'"]([^\'"]+)[\'"]\s*(?:was not found|plugin does not exist)/i', $message, $matches) && in_array($matches[1], $markdownConstraints)) {
        $pluginId = $matches[1];
        if (($class = '\\Drupal\\markdown\\Plugin\\Validation\\Constraint\\' . $pluginId) && class_exists($class)) {
          $value = $typed->getValue();
          $context = new ExecutionContext($typed->getTypedDataManager()->getValidator(), $value, new DrupalTranslator());
          foreach ($this->constraints as $name => $options) {
            if ($name === $pluginId) {
              /** @var \Symfony\Component\Validator\Constraint $constraint */
              $constraint = new $class($options);
              if (($validatorClass = $constraint->validatedBy()) && class_exists($validatorClass)) {
                /** @var \Symfony\Component\Validator\ConstraintValidatorInterface $constraintValidator */
                $constraintValidator = new $validatorClass();
                $constraintValidator->initialize($context);
                $constraintValidator->validate($value, $constraint);
              }
            }
          }
          $violationList->addAll($context->getViolations());
          return $violationList;
        }
      }

      // Add the exception message to the violations list.
      $violationList->add(new ConstraintViolation($message, '', [], '', '', ''));

      return $violationList;
    }
  }

}
