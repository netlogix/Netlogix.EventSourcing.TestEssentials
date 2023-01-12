<?php

declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\Doctrine;

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Persistence\Doctrine\Service as DoctrineService;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Netlogix\EventSourcing\TestEssentials\Tests\Functional\FunctionalTestCase;

/**
 * @var $this FunctionalTestCase
 */
trait WithFullMigrationsTrait
{
    public function setUp(): void
    {
        $this->setupDatabase();
        parent::setUp();
    }

    public function setupDatabase()
    {
        $bootstrap = self::$bootstrap;
        assert($bootstrap instanceof Bootstrap);

        $objectManager = $bootstrap->getObjectManager();

        // Fetching the persistence manager locally is mandatory because "setupDatabase" should go before "setUp".
        $this->persistenceManager = $objectManager->get(PersistenceManagerInterface::class);
        if (!($this->persistenceManager instanceof TestingPersistenceManager)) {
            return;
        }

        self::$testablePersistenceEnabled = false;
        $this->persistenceManager->ensureEmptyDatabase();

        $doctrineService = $objectManager->get(DoctrineService::class);
        assert($doctrineService instanceof DoctrineService);

        $doctrineService->executeMigrations();
    }

    public function tearDown(): void
    {
        if (!($this->persistenceManager instanceof TestingPersistenceManager)) {
            parent::tearDown();
            return;
        }

        $bootstrap = self::$bootstrap;
        assert($bootstrap instanceof Bootstrap);

        self::$testablePersistenceEnabled = true;
        $this->persistenceManager->ensureEmptyDatabase();

        parent::tearDown();
    }
}
