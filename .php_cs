<?php

return PhpCsFixer\Config::create()
    ->setUsingCache(false)
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'include' => true,
        'lowercase_cast' => true,
        'native_function_casing' => true,
        'no_empty_statement' => true,
        'no_spaces_around_offset' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'trailing_comma_in_multiline_array' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->in(['.'])
        ->exclude('vendor')
        ->exclude('public')
    );
