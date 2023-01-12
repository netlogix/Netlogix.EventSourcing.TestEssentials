<?php

declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\Doctrine;

use Doctrine\DBAL\Connection;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;

/**
 * PersistenceManager that should only be used in Functional Tests:
 * * Will not drop and recreate all tables in between tests
 * * Instead, will truncate all tables
 *
 * @Flow\Scope("singleton")
 */
class TestingPersistenceManager extends PersistenceManager
{
    protected static bool $createdSchema = false;

    /**
     * @var array<string>
     */
    protected array $setupDatabaseStatements = [];

    /**
     * The default doctrine PersistenceManager has a "injectSettings" method as well. Since this class is
     * not inside of the Neos.Flow package, the $settings injected here are empty. Therefore we have to
     * manually fetch the actual settings using the ConfigurationManager.
     *
     * @param array $settings
     * @return void
     * @throws \Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException
     */
    public function injectSettings(array $settings): void
    {
        $configurationManager = Bootstrap::$staticObjectManager->get(ConfigurationManager::class);
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Flow.persistence'
        );
        $setupDatabaseStatements = $settings['testingPersistenceManager']['setupDatabaseStatements'] ?? [];
        $this->setupDatabaseStatements = is_array($setupDatabaseStatements) ? array_values(
            $setupDatabaseStatements
        ) : [];
    }

    public function compile(): bool
    {
        if (!static::$createdSchema) {
            $result = parent::compile();

            if ($result) {
                $connection = $this->entityManager->getConnection();
                foreach ($this->setupDatabaseStatements as $setupDatabaseStatement) {
                    $connection->executeStatement($setupDatabaseStatement);
                }
            }

            self::$createdSchema = true;

            return $result;
        }

        return true;
    }

    public function tearDown(): void
    {
        if (!($this->settings['backendOptions']['driver'] !== null && $this->settings['backendOptions']['path'] !== null)) {
            $this->logger->notice('Doctrine 2 destroy skipped, driver and path backend options not set!');
            return;
        }

        $this->entityManager->clear();
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->getSchemaManager();
        $fullSchema = $schemaManager->createSchema();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fullSchema->getTableNames() as $tableName) {
            $connection->exec(
                $connection
                    ->getDatabasePlatform()
                    ->getTruncateTableSQL($tableName)
            );
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function ensureEmptyDatabase(): void
    {
        $this
            ->entityManager
            ->getConnection()
            ->transactional(function (Connection $connection) {
                $databaseName = $this->settings['backendOptions']['dbname'] ?? 'flow';
                $connection->executeStatement('DROP DATABASE IF EXISTS ' . $databaseName);
                $connection->executeStatement(
                    'CREATE DATABASE ' . $databaseName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
                $connection->executeStatement('USE ' . $databaseName);
            });
        self::$createdSchema = false;
    }
}
