<?php

declare(strict_types=1);

namespace Yiisoft\Queue\Nats\Functional;

use Basis\Nats\Client;
use PHPUnit\Framework\TestCase;


abstract class FunctionalTestCase extends TestCase
{
    protected function createClient(): Client
    {
        return new Client();
    }

    protected ?Client $client = null;

    protected function getClient(): Client
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    public function setUp(): void
    {
        $this->tearDown();
    }
    public function tearDown(): void
    {
        $api = $this->createClient()->getApi();

        $api->client->logger = null;

        foreach ($api->getStreamNames() as $name) {
            $api->getStream($name)->delete();
        }
    }

    public function getStreamNames(): array
    {
        $api = $this->createClient()->getApi();

        $api->client->logger = null;

        return $api->getStreamNames();
    }

}
