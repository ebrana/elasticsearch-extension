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
        DebugDataHolder $debugDataHolder,
        MappingMetadataProvider $mappingMetadataProvider,
        Connection $connection,
        string $kibana,
    ): void
    {
        $bar ??= Tracy\Debugger::getBar();
        $bar->addPanel(new self(new QueryCollector($debugDataHolder, $mappingMetadataProvider, $connection, $kibana)));
    }

    public function __construct(
        private QueryCollector $queryCollector,
    ) {
    }

    public function getTab(): string
    {
        $this->queryCollector->collect();
        return Tracy\Helpers::capture(function () {
            $collector = $this->queryCollector;
            require __DIR__ . '/templates/ElasticsearchPanel.tab.phtml';
        });
    }

    public function getPanel(): ?string
    {
        return Tracy\Helpers::capture(function () {
            $collector = $this->queryCollector;
            require __DIR__ . '/templates/ElasticsearchPanel.panel.phtml';
        });
    }
}
