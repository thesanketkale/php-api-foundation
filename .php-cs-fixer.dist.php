<?php

declare(strict_types=1);

$dirs = array_values(array_filter([
    __DIR__ . '/src',
    __DIR__ . '/public',
    __DIR__ . '/config',
    __DIR__ . '/tests',
    __DIR__ . '/db',
], 'is_dir'));

$finder = PhpCsFixer\Finder::create()
    ->in($dirs)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new PhpCsFixer\Config();
return $config
    ->setRules([
        '@PSR12' => true,
        'strict_param' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setUnsupportedPhpVersionAllowed(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::sequential());
