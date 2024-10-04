<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Bridges\Tracy;

use Elasticsearch\Connection\Connection;
use Elasticsearch\Debug\DebugDataHolder;
use Elasticsearch\Mapping\Exceptions\MappingJsonCreateException;
use Elasticsearch\Mapping\MappingMetadataProvider;
use Elasticsearch\Mapping\Request\MetadataRequestFactory;
use ReflectionClass;
use ReflectionException;

final class QueryCollector
{
    private ?int $invalidEntityCount = null;
    private array $data = [];
    private const string COMPATIBLE_VERSION = '8.0.0';
    private const string NOT_COMPATIBLE_VERSION = '9.0.0';

    public function __construct(
        private readonly DebugDataHolder $debugDataHolder,
        private readonly MappingMetadataProvider $mappingMetadataProvider,
        private readonly Connection $connection,
        private readonly string $kibana,
    ) {
    }

    public function collect(): void
    {
        $this->data = [
            'queries'    => $this->debugDataHolder->getData(),
            'entities'   => $this->provideEntitiesMapping(),
            'kibana'     => $this->kibana,
            'info'       => $this->connection->getServerInfo(),
            'connection' => [
                'default' => 'elasticsearch.connection',
            ],
        ];
    }

    public function getInfo(): array
    {
        $this->data['info']['version']['build_snapshot'] = true;
        return $this->data['info'];
    }

    public function isCompatible(): bool
    {
        return
            version_compare($this->data['info']['version']['number'], self::COMPATIBLE_VERSION, '>=') &&
            version_compare($this->data['info']['version']['number'], self::NOT_COMPATIBLE_VERSION, '<');
    }

    /**
     * @return \Elasticsearch\Debug\Query[]
     */
    public function getQueries(): array
    {
        return $this->data['queries'] ?? [];
    }

    public function getQueryCount(): int
    {
        return count($this->data['queries']);
    }

    /**
     * @return array<string, array<string, array<bool|string|int>>>
     */
    public function getEntities(): array
    {
        return $this->data['entities'];
    }

    public function getTime(): float
    {
        $time = 0;
        foreach ($this->data['queries'] as $query) {
            $time += $query['executionMS'];
        }

        return $time;
    }

    public function getKibana(): string
    {
        return $this->data['kibana'];
    }

    /**
     * @return string[]
     */
    public function getConnection(): array
    {
        return $this->data['connection'];
    }

    public function getInvalidEntityCount(): int
    {
        return $this->invalidEntityCount ??= count($this->data['entities']['invalid']);
    }

    /**
     * @return array<string, array<string, array<bool|string|int>>>
     */
    private function provideEntitiesMapping(): array
    {
        $data = [
            'classes' => [],
        ];
        $data['invalid'] = [];
        $mappings = $this->mappingMetadataProvider->getMappingMetadata();

        foreach ($mappings->getMetadata() as $class => $index) {
            try {
                if (false === class_exists($class)) {
                    throw new ReflectionException(sprintf('Class "%s" not exists or cannot loadable.', $class));
                }
                $reflection = new ReflectionClass($class);
            } catch (ReflectionException $e) {
                $data['invalid'][$class] = [
                    'file'    => '',
                    'line'    => '',
                    'message' => $e->getMessage(),
                ];
                continue;
            }
            $metadataRequestFactory = new MetadataRequestFactory();
            try {
                $metadataRequest = $metadataRequestFactory->create($index);

                $data['classes'][$class] = [
                    'body' => $metadataRequest->getMappingJson(),
                    'file' => $reflection->getFileName(),
                    'line' => $reflection->getStartLine(),
                ];
            } catch (MappingJsonCreateException $e) {
                $data['invalid'][$class] = [
                    'file'    => $reflection->getFileName(),
                    'line'    => $reflection->getStartLine(),
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $data;
    }
}
