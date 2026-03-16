<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Bridges\Tracy;

use Elasticsearch\Connection\Connection;
use Elasticsearch\Mapping\Index;
use Elasticsearch\Mapping\MappingMetadataProvider;
use Elasticsearch\Tools\PhpQueryBuilder;
use InvalidArgumentException;
use stdClass;

final readonly class PlaygroundService
{
    private const array ALLOWED_TOP_LEVEL_KEYS = [
        'query',
        'aggs',
        'sort',
        'from',
        'size',
        '_source',
        'collapse',
        'search_after',
        'track_total_hits',
        'min_score',
        'post_filter',
    ];

    private const array FORBIDDEN_KEYS = [
        'script',
        'script_fields',
        'runtime_mappings',
        'stored_script',
        'stored_script_id',
        'update',
        'delete',
        'create',
        'index',
        'bulk',
        'pit',
        'scroll',
    ];

    public function __construct(
        private Connection $connection,
        private MappingMetadataProvider $mappingMetadataProvider,
        private PhpQueryBuilder $phpQueryBuilder,
    ) {
    }

    /**
     * @return array{php:string,normalizedQuery:string}
     * @throws \JsonException
     */
    public function generatePhp(string $query): array
    {
        $parsed = $this->parse($query);

        return [
            'php' => $this->phpQueryBuilder->fromArray($parsed['payload']),
            'normalizedQuery' => $parsed['normalizedQuery'],
        ];
    }

    /**
     * @return array{php:string,normalizedQuery:string,result:string}
     * @throws \JsonException
     * @throws \Elastic\Elasticsearch\Exception\AuthenticationException
     */
    public function execute(string $query, ?string $index = null, string $operation = 'search'): array
    {
        $parsed = $this->parse($query);
        $operation = $this->normalizeOperation($operation);
        $request = [
            'index' => $this->normalizeIndex($index),
            'body' => $parsed['rawQuery'],
        ];

        $response = $operation === 'count'
            ? $this->connection->getClient()->count($request)
            : $this->connection->getClient()->search($request);

        return [
            'php' => $this->phpQueryBuilder->fromArray($parsed['payload']),
            'normalizedQuery' => $parsed['normalizedQuery'],
            'result' => $this->encode($response->asArray()),
        ];
    }

    /**
     * @return string[]
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getAllowedIndices(): array
    {
        $indices = [];
        $prefix = $this->connection->getIndexPrefix();

        foreach ($this->mappingMetadataProvider->getMappingMetadata()->getMetadata() as $index) {
            if (!$index instanceof Index) {
                continue;
            }

            $name = $index->getName();
            if ($name === null || $name === '') {
                continue;
            }

            $indices[] = $prefix . $name;
        }

        $indices = array_values(array_unique($indices));
        sort($indices);

        return $indices;
    }

    /**
     * @return array{payload: array<string, mixed>, rawQuery: string, normalizedQuery: string}
     * @throws \JsonException
     */
    private function parse(string $query): array
    {
        $decoded = json_decode($query, false, 512, JSON_THROW_ON_ERROR);
        if (!$decoded instanceof stdClass) {
            throw new InvalidArgumentException('Playground expects a JSON object.');
        }

        $payloadNode = isset($decoded->body) && $decoded->body instanceof stdClass ? $decoded->body : $decoded;
        $payload = $this->toArray($payloadNode);
        if ($payload === []) {
            throw new InvalidArgumentException('Query body cannot be empty.');
        }

        foreach (array_keys($payload) as $key) {
            if (!in_array($key, self::ALLOWED_TOP_LEVEL_KEYS, true)) {
                throw new InvalidArgumentException(sprintf('Top-level key "%s" is not allowed in read-only playground.', $key));
            }
        }

        $this->assertForbiddenKeys($payloadNode);

        return [
            'payload' => $payload,
            'rawQuery' => $this->encodeMixed($payloadNode),
            'normalizedQuery' => $this->encodeMixed($payloadNode),
        ];
    }

    private function assertForbiddenKeys(mixed $payload): void
    {
        if ($payload instanceof stdClass) {
            $payload = get_object_vars($payload);
        }

        if (!is_array($payload)) {
            return;
        }

        foreach ($payload as $key => $value) {
            if (in_array((string) $key, self::FORBIDDEN_KEYS, true)) {
                throw new InvalidArgumentException(sprintf('Key "%s" is not allowed in read-only playground.', $key));
            }

            $this->assertForbiddenKeys($value);
        }
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function normalizeIndex(?string $index): string
    {
        $index = $index !== null ? trim($index) : null;
        if ($index === null || $index === '') {
            throw new InvalidArgumentException('Index is required.');
        }

        if (preg_match('/^[a-zA-Z0-9*._,-]+$/', $index) !== 1) {
            throw new InvalidArgumentException('Index contains unsupported characters.');
        }

        if (!in_array($index, $this->getAllowedIndices(), true)) {
            throw new InvalidArgumentException('Selected index is not allowed.');
        }

        return $index;
    }

    private function normalizeOperation(string $operation): string
    {
        if (!in_array($operation, ['search', 'count'], true)) {
            throw new InvalidArgumentException('Unsupported playground operation.');
        }

        return $operation;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws \JsonException
     */
    private function encode(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function encodeMixed(mixed $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     * @throws \JsonException
     */
    private function toArray(stdClass $payload): array
    {
        /** @var array<string, mixed> $array */
        $array = json_decode((string) json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return $array;
    }
}
