<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Bridges\Tracy;

use Elasticsearch\Connection\Connection;
use Elasticsearch\Debug\DebugDataHolder;
use Elasticsearch\Mapping\MappingMetadataProvider;
use Tracy;

/**
 * Bar panel for Tracy 2.x
 *
 * @internal
 */
readonly class ElasticsearchPanel implements Tracy\IBarPanel
{
    public static function initialize(
        Connection $connection,
        DebugDataHolder $debugDataHolder,
        MappingMetadataProvider $mappingMetadataProvider,
        PlaygroundRequestHandler $playgroundRequestHandler,
        string $kibana,
    ): void
    {
        $bar ??= Tracy\Debugger::getBar();
        $queryCollector = new QueryCollector(
            $connection,
            $debugDataHolder,
            $mappingMetadataProvider,
            $playgroundRequestHandler,
            $kibana
        );
        $bar->addPanel(new self($queryCollector));
    }

    public function __construct(
        private QueryCollector $queryCollector,
    ) {
    }

    /**
     * @throws \Elastic\Elasticsearch\Exception\AuthenticationException
     * @throws \Throwable
     * @throws \Elastic\Elasticsearch\Exception\ClientResponseException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     */
    public function getTab(): string
    {
        $this->queryCollector->collect();
        return Tracy\Helpers::capture(function () {
            $collector = $this->queryCollector;
            require __DIR__ . '/templates/ElasticsearchPanel.tab.phtml';
        });
    }

    /**
     * @throws \Throwable
     */
    public function getPanel(): ?string
    {
        return Tracy\Helpers::capture(function () {
            $collector = $this->queryCollector;
            require __DIR__ . '/templates/ElasticsearchPanel.panel.phtml';
        });
    }
}
