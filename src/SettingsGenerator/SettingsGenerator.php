<?php

namespace RoyGoldman\DrupalEnvSettings\SettingsGenerator;

use PhpParser\Comment;
use PhpParser\Error as ParserError;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\ParserFactory;
use RoyGoldman\DrupalEnvSettings\PrettyPrinter;

/**
 * Defines SettingsGenerator Helper.
 */
class SettingsGenerator implements SettingsGeneratorInterface {

  /**
   * Config file path.
   *
   * The config file is expected to be located outside the Drupal web root.
   */
  const CONFIG_FILE_NAME = '../config.php';

  /**
   * Doc comment for generated code header.
   */
  const DOC_COMMENT_GENERATED = <<<'DOC_COMMENT_GENERATED'

/**
 * Content after this line was autogenerated by roygoldman/drupal-env-settings.
 */

DOC_COMMENT_GENERATED;

  /**
   * Doc comment for config file include header.
   */
  const DOC_COMMENT_INCLUDE = <<<'DOC_COMMENT_INCLUDE'
/**
 * Load dynamic configration from file outside of Drupal root.
 */
DOC_COMMENT_INCLUDE;

  /**
   * Doc comment for environmental variable header.
   */
  const DOC_COMMENT_ENVIRONMENT = <<<'DOC_COMMENT_ENVIRONMENT'

/**
 * Load settings from the environment.
 */
DOC_COMMENT_ENVIRONMENT;

  /**
   * {@inheritdoc}
   */
  public function generate($output_path, array $env_settings, $template_source = '') {

    // Parse the existing template file.
    $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    try {
      $code = $parser->parse($template_source);
    }
    catch (ParserError $error) {
      echo "Parse error: {$error->getMessage()}\n";
      return;
    }

    // Add dynamic config loader for environemental variable config.
    $this->injectConfigLoader($code);

    // Generate code for environmental variable config.
    $this->generateVariableCode($code, $env_settings);

    // Ensure existance of output directory.
    if (!file_exists($output_path)) {
      mkdir(dirname($output_path), 0774, TRUE);
    }

    // Write setting file to filesystem.
    $prettyPrinter = new PrettyPrinter();
    $generated_source = $prettyPrinter->prettyPrintFile($code);
    file_put_contents($output_path, $generated_source);
  }

  /**
   * {@inheritdoc}
   */
  public function injectConfigLoader(array &$code) {
    $code[] = new If_(
      new FuncCall(
        new Name('file_exists'),
        [
          new Arg(
            new String_(static::CONFIG_FILE_NAME)
          ),
        ]
      ),
      [
        'stmts' => [
          new Expression(
            new Include_(
              new String_(static::CONFIG_FILE_NAME),
              Include_::TYPE_INCLUDE_ONCE
            )
          ),
        ],
      ],
      [
        'comments' => [
          new Comment(static::DOC_COMMENT_GENERATED),
          new Comment(static::DOC_COMMENT_INCLUDE),
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generateVariableCode(array &$code, array $env_settings) {
    $first = TRUE;
    foreach ($env_settings as $name => $value) {
      $variable = new Variable($name);
      if (is_array($value)) {
        // For nested arrays, with one value, use dim lookup format.
        while (count($value) == 1) {
          $value_keys = array_keys($value);
          $key = array_shift($value_keys);
          $variable = new ArrayDimFetch(
            $variable,
            $this->getAsScaler($key)
          );
          $value = $value[$key];
        }

        if (!is_array($value)) {
          // Non-array values are expected to be a environmental variable names.
          $assigned_value = $this->buildGetEnvNode($value);
        }
        else {
          // An array at this point is multivalued.
          // Each value needs to be expanded into possibly nested variables.
          $assigned_value = $this->buildArrayItems($value);
        }
      }
      else {
        // Non-array values are expected to be a environmental variable names.
        $assigned_value = $this->buildGetEnvNode($value);
      }

      // Build line of configuration and add it to the file.
      $code[] = new Expression(
        new Assign(
          $variable,
          $assigned_value
        )
      );
      if ($first) {
        $first = FALSE;
        $last = array_pop($code);
        $last->setAttribute('comments', [new Comment(static::DOC_COMMENT_ENVIRONMENT)]);
        $code[] = $last;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildGetEnvNode($var_name) {
    return (new FuncCall(
      new Name('getenv'),
      [
        new Arg(
          new String_($var_name)
        ),
      ]
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getAsScaler($value) {
    switch (gettype($value)) {
      case 'float':
        return new DNumber($value);

      case 'boolean':
      case 'integer':
        return new LNumber($value);

      case 'string':
        return new String_($value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildArrayItems(array $values) {
    $array_values = [];
    foreach ($values as $key => $value) {
      if (is_array($value)) {
        $item = $this->buildArrayItems($value);
      }
      else {
        $item = $this->buildGetEnvNode($value);
      }
      $array_values[] = new ArrayItem($item, $this->getAsScaler($key));
    }
    return new Array_($array_values);
  }

}
