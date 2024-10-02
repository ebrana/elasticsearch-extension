<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Command;

use Elasticsearch\Mapping\MappingMetadataProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'elasticsearch:info-index',
    description: 'Information Elasticsearch index by mapping',
)]
class InformationIndexCommand extends Command
{
    public function __construct(
        private readonly MappingMetadataProvider $metadataProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command get information about elasticsearch indexes.


EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        foreach ($this->metadataProvider->getMappingMetadata()->getMetadata() as $index) {
            $rows = [
                ['<info>Class</>', '<info>Index name</>'],
                new TableSeparator(),
            ];
            $rows[] = [
                $index->getEntityClass(),
                $index->getName(),
            ];
            $rows[] = ['<info>Property name</>', '<info>Type</>'];
            $rows[] = new TableSeparator();
            foreach ($index->getProperties() as $property) {
                $fields = '';
                if ($property->getType() === 'nested') {
                    foreach ($property->getCollection()->get('properties') as $name => $field) {
                        if ($fields !== '') {
                            $fields .= ', ';
                        }
                        $fields .= $name . '[' . $field['type'] . ']';
                    }
                    $fields = '(' . $fields . ')';
                }
                $rows[] = [
                    $property->getName(),
                    $property->getType() . ' ' . $fields,
                ];
            }
            $io->table([], $rows);
        }

        return Command::SUCCESS;
    }
}
