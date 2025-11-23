<?php

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DVDoug\Behat\CodeCoverage\Extension as CodeCoverageExtension;

return (new Config())
    ->withProfile((new Profile('default'))
        ->withExtension(new Extension(CodeCoverageExtension::class, [
            'cache' => 'build/php-code-coverage-cache',
            'filter' => [
                'include' => [
                    'directories' => [
                        'src' => null,
                    ],
                ],
            ],
            'reports' => [
                'clover' => [
                    'target' => 'build/coverage-behat/clover.xml',
                ],
                'html' => [
                    'target' => 'build/coverage-behat',
                ],
                'text' => [
                    'showColors' => true,
                    'showUncoveredFiles' => true,
                ],
            ],
        ]))
        ->withSuite((new Suite('packer'))
            ->withContexts('PackerContext')
            ->withPaths('%paths.base%/features/common'))
        ->withSuite((new Suite('infallible_packer'))
            ->withContexts('InfalliblePackerContext')
            ->withPaths(
                '%paths.base%/features/common',
                '%paths.base%/features/infallible'
            )));
