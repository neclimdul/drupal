<?php

/**
 * @file
 * Contains \Drupal\Core\Command\DbDumpCommand.
 */

namespace Drupal\Core\Command;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides a command to import the current database from a script.
 *
 * This script runs on databases exported using using one of the database dump
 * commands and imports it into the current database connection.
 *
 * @see \Drupal\Core\Command\DbImportApplication
 */
class DbImportCommand extends Command {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection $connection
   */
  protected $connection;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Construct the database dump command.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to use.
   */
  function __construct(Connection $connection, ModuleHandlerInterface $module_handler) {
    $this->connection = $connection;
    $this->moduleHandler = $module_handler;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('import-database')
      ->setDescription('Import database from a generation script.')
      ->addArgument('script', InputOption::VALUE_REQUIRED, 'Import script');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    // This would save a lot of headaches but it is protected.
//    $this->connection->setPrefix('import_');

    $script = $input->getArgument('script');
    if (!is_file($script)) {
      $output->writeln('File must exist.');
      return;
    }

    $this->runScript($script);
    $output->writeln('Import completed successfully.');
  }

  /**
   * Run the database script.
   *
   * @param string $script
   *   The PHP script.
   * @return mixed
   */
  protected function runScript($script) {
    if (substr($script, -3) == '.gz') {
      $script = "compress.zlib://$script";
    }
    try {
      require $script;
    }
    catch (SchemaObjectExistsException $e) {
      throw new \RuntimeException('An existing Drupal installation exists at this location. Try removing all tables or changing the database prefix in your settings.php file.');
    }
  }

}
