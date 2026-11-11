<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/migrations', __DIR__.'/config'])->notName('reference.php')->append([__DIR__.'/public/index.php', __FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules(['@Symfony' => true, 'declare_strict_types' => true, 'array_syntax' => ['syntax' => 'short']])
    ->setFinder($finder);
