<?php

declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\Tests\Functional;

use Netlogix\EventSourcing\TestEssentials\Cache\SetupCachesTrait;
use Netlogix\EventSourcing\TestEssentials\Common\FindInstancesOfTrait;

abstract class FunctionalTestCase extends \Neos\Flow\Tests\FunctionalTestCase
{
    use FindInstancesOfTrait;
    use SetupCachesTrait;

    protected static $testablePersistenceEnabled = true;

    public function setUp(): void
    {
        parent::setUp();

        $this->setupCaches();
    }
}
