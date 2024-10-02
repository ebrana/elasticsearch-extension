<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Command\Utils;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

trait BooleanOptionResolverTrait
{
    private function resolveBoolOption(InputInterface $input, string $name): bool
    {
        $value = $input->getOption($name);
        if (!in_array($value, ['0', '1', 'true', 'false', false, true], true)) {
            throw new InvalidArgumentException(sprintf('Parameter %s has wrong value. Please enter 0 or 1.', $name));
        }

        return (bool)$value;
    }
}
