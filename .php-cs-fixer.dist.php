<?php

declare(strict_types=1);

/**
 * @var iterable<string> $finder
 */
$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

$customRules = [
    '@Symfony' => true,
    'array_syntax' => ['syntax' => 'short'],
    'concat_space' => ['spacing' => 'one'],
    'declare_strict_types' => true,
    'new_with_braces' => false,
    'phpdoc_summary' => false,
];

$psr12Rules = [
    '@PSR2' => true,
    'blank_line_after_opening_tag' => true,
    'compact_nullable_typehint' => true,
    'concat_space' => ['spacing' => 'one'],
    'declare_equal_normalize' => ['space' => 'none'],
    'type_declaration_spaces' => true,
    'new_with_braces' => true,
    'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
    'no_empty_statement' => true,
    'no_leading_import_slash' => true,
    'no_leading_namespace_whitespace' => true,
    'no_whitespace_in_blank_line' => true,
    'return_type_declaration' => ['space_before' => 'none'],
    'single_space_around_construct' => false,
    'single_trait_insert_per_statement' => true,
];

$config = new PhpCsFixer\Config();
$config->setRiskyAllowed(true)
    ->setRules($customRules + $psr12Rules)
    ->setFinder($finder);

return $config;
