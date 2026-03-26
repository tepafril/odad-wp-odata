<?php
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                     => true,
        'array_syntax'               => ['syntax' => 'short'],
        'no_unused_imports'          => true,
        'single_quote'               => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'no_whitespace_in_blank_line' => true,
    ])
    ->setFinder($finder);
