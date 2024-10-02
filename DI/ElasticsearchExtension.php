<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\DI;

use Contributte\Console\DI\ConsoleExtension;
use Ebrana\ElasticsearchExtension\Bridges\Tracy\ElasticsearchPanel;
use Ebrana\ElasticsearchExtension\Command\CreateIndexCommand;
use Ebrana\ElasticsearchExtension\Command\DeleteIndexCommand;
use Ebrana\ElasticsearchExtension\Command\InformationIndexCommand;
use Ebrana\ElasticsearchExtension\Services\PsrCacheDecorator;
use Elastic\Elasticsearch\ClientBuilder;
use Elasticsearch\Connection\Connection;
use Elasticsearch\Debug\DebugDataHolder;
use Elasticsearch\Indexing\Builders\DefaultDocumentBuilderFactory;
use Elasticsearch\Indexing\DocumentFactory;
use Elasticsearch\Mapping\Drivers\AnnotationDriver;
use Elasticsearch\Mapping\Drivers\JsonDriver;
use Elasticsearch\Mapping\MappingMetadataFactory;
use Elasticsearch\Mapping\MappingMetadataProvider;
use Elasticsearch\Mapping\Request\MetadataRequestFactory;
use Elasticsearch\Search\SearchBuilderFactory;
use Nette\Bridges\Psr\PsrCacheAdapter;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use RuntimeException;
use Tracy\Debugger;

class ElasticsearchExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'profiling'   => Expect::bool(false),
            'indexPrefix' => Expect::string(''),
            'kibana'      => Expect::string('http://localhost:5601'),
            'cache'       => Expect::string()->nullable(),
            'driver'      => Expect::structure([
                'type'        => Expect::anyOf('attributes')->default('attributes'),
                'keyResolver' => Expect::string(),
            ]),
            'hosts'       => Expect::array(['localhost:9200']),
            'mappings'    => Expect::listOf('string'),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $config = (array)$this->getConfig();

        $driverDefinition = match ($config['driver']->type) {
            'attributes' => AnnotationDriver::class,
            'json' => JsonDriver::class,
            default => throw new RuntimeException('ES driver not found.'),
        };

        $esDriver = $builder->addDefinition($this->prefix('elasticsearch.esDriver'))
            ->setType($driverDefinition)
            ->setFactory($driverDefinition);

        if ($config['driver']?->keyResolver) {
            $esDriver->addSetup('$service->setKeyResolver(?)', [$config['driver']['keyResolver']]);
        }

        $connectionFactory = $builder->addDefinition($this->prefix('elasticsearch.connection_factory'))
            ->setType(ClientBuilder::class)
            ->setFactory([ClientBuilder::class, 'create'])
            ->addSetup('$service->setHosts(?)', [$config['hosts']]);

        $connection = $builder->addDefinition($this->prefix('elasticsearch.connection'))
            ->setType(Connection::class)
            ->setFactory(Connection::class)
            ->setArguments([$connectionFactory, $config['indexPrefix']]);

        $cache = null;
        if ($config['cache']) {
            $storage = $builder->getDefinition($config['cache']);
            $psrCacheAdapter = $builder->addDefinition($this->prefix('elasticsearch.cache.adapter'))
                ->setType(PsrCacheAdapter::class)
                ->setFactory(PsrCacheAdapter::class)
                ->setArgument('storage', $storage);
            $cache = $builder->addDefinition($this->prefix('elasticsearch.cache'))
                ->setType(PsrCacheDecorator::class)
                ->setFactory(PsrCacheDecorator::class)
                ->setArgument('adapter', $psrCacheAdapter);
        }

        $mappingMetadataFactory = $builder->addDefinition($this->prefix('elasticsearch.mappingMetadataFactory'))
            ->setType(MappingMetadataFactory::class)
            ->setFactory(MappingMetadataFactory::class)
            ->setArguments([$esDriver, $config['mappings'], $cache]);

        $mappingMetadataProvider = $builder->addDefinition($this->prefix('elasticsearch.mappingMetadataProvider'))
            ->setType(MappingMetadataProvider::class)
            ->setFactory(MappingMetadataProvider::class)
            ->setArguments([$mappingMetadataFactory]);

        $metadataRequestFactory = $builder->addDefinition($this->prefix('elasticsearch.metadataRequestFactory'))
            ->setType(MetadataRequestFactory::class)
            ->setFactory(MetadataRequestFactory::class);

        $builder->addDefinition($this->prefix('elasticsearch.searchBuilderFactory'))
            ->setType(SearchBuilderFactory::class)
            ->setFactory(SearchBuilderFactory::class)
            ->setArguments([$mappingMetadataProvider, $config['indexPrefix']]);

        $documentFactory = $builder->addDefinition($this->prefix('elasticsearch.documentFactory'))
            ->setType(DocumentFactory::class)
            ->setFactory(DocumentFactory::class)
            ->setArguments([$mappingMetadataProvider, $config['indexPrefix']]);

        if (isset($config['profiling']) && $config['profiling'] && Debugger::isEnabled()) {
            $debugDataHolder = $builder->addDefinition($this->prefix('elasticsearch.debugDataHolder'))
                ->setType(DebugDataHolder::class)
                ->setFactory(DebugDataHolder::class);

            $connection->setType(\Elasticsearch\Debug\Connection::class)
                ->setFactory(\Elasticsearch\Debug\Connection::class)
                ->setArguments([$debugDataHolder, $connectionFactory, $config['indexPrefix']]);

            $connection->addSetup(
                [ElasticsearchPanel::class, 'initialize'],
                [$debugDataHolder, $mappingMetadataProvider, $connection, $config['kibana']],
            );
        }

        $defaultDocumentBuilder = $builder->addDefinition($this->prefix('elasticsearch.documentDefaultFactory'))
            ->setType(DefaultDocumentBuilderFactory::class)
            ->setFactory(DefaultDocumentBuilderFactory::class);

        $documentFactory->addSetup('$service->addBuilderFactory(?)', [$defaultDocumentBuilder]);

        if ($this->hasConsole()) {
            $builder->addDefinition($this->prefix('console.command.elasticsearch_create_index'))
                ->setType(CreateIndexCommand::class)
                ->setFactory(CreateIndexCommand::class)
                ->setArguments([$connection, $mappingMetadataProvider, $metadataRequestFactory]);
            $builder->addDefinition($this->prefix('console.command.elasticsearch_delete_index'))
                ->setType(DeleteIndexCommand::class)
                ->setFactory(DeleteIndexCommand::class)
                ->setArguments([$connection, $mappingMetadataProvider]);
            $builder->addDefinition($this->prefix('console.command.elasticsearch_information_index'))
                ->setType(InformationIndexCommand::class)
                ->setFactory(InformationIndexCommand::class)
                ->setArguments([$mappingMetadataProvider]);
        }
    }

    protected function hasConsole(): bool
    {
        return class_exists(ConsoleExtension::class);
    }
}
