<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ModuleBoundaryTest extends TestCase
{
    #[DataProvider('forbiddenDependencies')]
    public function test_module_does_not_import_forbidden_dependency(string $directory, string $forbiddenNamespace): void
    {
        $violations = [];
        $root = dirname(__DIR__, 2);
        foreach ($this->phpFiles($root.'/'.$directory) as $file) {
            if (str_contains(file_get_contents($file), "use {$forbiddenNamespace}")) {
                $violations[] = str_replace($root.'/', '', $file);
            }
        }

        $this->assertSame([], $violations, "Forbidden module dependency {$forbiddenNamespace} found in:\n".implode("\n", $violations));
    }

    public static function forbiddenDependencies(): array
    {
        return [
            'Sports must not depend on Profiles' => ['app/Domain/Sports', 'App\\Domain\\Profiles\\'],
            'Feed must not depend on Advertising' => ['app/Domain/Feed', 'App\\Domain\\Advertising\\'],
            'Feed controllers must not depend on Advertising' => ['app/Http/Controllers/Api/V1/Feed', 'App\\Domain\\Advertising\\'],
            'Web feed must not depend on Advertising' => ['app/Http/Controllers/Web', 'App\\Domain\\Advertising\\'],
            'Feed must not depend on concrete Media models' => ['app/Domain/Feed', 'App\\Domain\\Media\\Models\\'],
            'Messaging must not depend on concrete Media models' => ['app/Domain/Messaging', 'App\\Domain\\Media\\Models\\'],
            'Opportunities must not depend on Media' => ['app/Domain/Opportunities', 'App\\Domain\\Media\\'],
            'Opportunity controllers must not depend on Media' => ['app/Http/Controllers/Api/V1/Opportunities', 'App\\Domain\\Media\\'],
            'Feed must emit events instead of importing Notifications' => ['app/Domain/Feed', 'App\\Domain\\Notifications\\'],
            'Messaging must emit events instead of importing Notifications' => ['app/Domain/Messaging', 'App\\Domain\\Notifications\\'],
            'Moderation must emit events instead of importing Notifications' => ['app/Domain/Moderation', 'App\\Domain\\Notifications\\'],
        ];
    }

    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) return [];
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') $files[] = $file->getPathname();
        }
        return $files;
    }
}
