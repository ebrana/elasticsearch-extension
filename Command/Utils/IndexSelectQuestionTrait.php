<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Command\Utils;

use Elasticsearch\Mapping\MappingMetadataProvider;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

trait IndexSelectQuestionTrait
{
    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function createIndexSelectQuestion(
        MappingMetadataProvider $metadataProvider,
        HelperInterface $helper,
        InputInterface $input,
        OutputInterface $output
    ): ?array
    {
        $question = new ChoiceQuestion(
            'please select the class for which the index should be created (all by default)',
            $metadataProvider->getMappingMetadata()->getMetadata()->getKeys(),
            implode(',', $metadataProvider->getMappingMetadata()->getMetadata()->getKeys()),
        );
        $question->setMultiselect(true);

        $classes = $helper->ask($input, $output, $question);
        if (!is_array($classes)) {
            return null;
        }

        return $classes;
    }
}
