<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
    ]);

return (new PhpCsFixer\Config())
    ->setParallelConfig(
        new PhpCsFixer\Runner\Parallel\ParallelConfig(
            8,
        ),
    )
    ->setRules([
        '@PER-CS3.0' => true,
    ])
    ->setFinder($finder);
