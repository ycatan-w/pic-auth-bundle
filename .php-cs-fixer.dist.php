<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude(['var', 'vendor']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Symfony / PSR-12
        '@Symfony'                     => true,
        'array_syntax'                 => ['syntax' => 'short'],
        'binary_operator_spaces'       => ['default' => 'align_single_space_minimal'],
        'blank_line_after_namespace'   => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement'  => ['statements' => ['return']],
        'cast_spaces'                  => true,
        'concat_space'                 => ['spacing' => 'one'],
        'no_trailing_whitespace'       => true,
        'no_unused_imports'            => true,
        'single_quote'                 => true,
        'declare_strict_types'         => true,

        // ---- PHPDoc rules ----
        'phpdoc_align'                        => false,
        'phpdoc_separation'                   => false,
        'phpdoc_trim'                         => false,
        'phpdoc_scalar'                       => true,
        'phpdoc_no_empty_return'              => false,
        'phpdoc_order'                        => true,
        'phpdoc_add_missing_param_annotation' => true,
        'no_superfluous_phpdoc_tags'          => false,

        // Other rules
        'simplified_if_return' => true,
        'void_return'          => true,
        'strict_comparison'    => true,
        'strict_param'         => true,
    ])
    ->setFinder($finder);
