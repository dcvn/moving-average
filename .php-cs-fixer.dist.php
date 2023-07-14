<?php

$finder = PhpCsFixer\Finder::create()
    ->notPath('vendor')
    ->notPath('tmp')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true
    ])
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/tmp/.php_cs.cache')
    ->setFinder($finder)
;
