<?php

/**
 * The documentation is part of the product: these tests keep it honest.
 */
$root = dirname(__DIR__);

function rootPath(string $path): string
{
    return dirname(__DIR__).'/'.$path;
}

/** @return array<int, string> every Markdown file that ships documentation */
function markdownFiles(): array
{
    return array_merge(
        glob(rootPath('*.md')) ?: [],
        glob(rootPath('docs/*.md')) ?: [],
        glob(rootPath('.github/*.md')) ?: [],
    );
}

it('keeps composer.json, the support matrix, the README and CI in step', function () {
    $composer = json_decode((string) file_get_contents(rootPath('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $support = (string) file_get_contents(rootPath('docs/support.md'));

    expect(preg_match('/<!--\s*support:\s*filament=(\S+)\s+php=(\S+)\s*-->/', $support, $m))->toBe(1);
    [, $filament, $php] = $m;

    expect($composer['require']['filament/filament'])->toBe($filament)
        ->and($composer['require']['php'])->toBe($php);

    // the visible table says the same
    expect($support)->toContain('`'.$filament.'`')->and($support)->toContain('`'.$php.'`');

    // only Filament v5 is claimed, everywhere
    expect($composer['description'])->toContain('Filament v5')
        ->and($support)->toContain('supports Filament v5 only');

    // the minimum Filament version appears in the README and in the CI job that proves it
    $minimum = ltrim($filament, '^');
    expect((string) file_get_contents(rootPath('README.md')))->toContain($minimum)->toContain('docs/support.md');
    expect((string) file_get_contents(rootPath('.github/workflows/tests.yml')))->toContain("Lowest supported versions (Filament {$minimum})");
});

it('has a license file that matches composer.json', function () {
    $composer = json_decode((string) file_get_contents(rootPath('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['license'])->toBe('MIT')
        ->and((string) file_get_contents(rootPath('LICENSE')))->toContain('MIT License');
});

it('has working relative links and images in every Markdown file', function () {
    $broken = [];

    foreach (markdownFiles() as $file) {
        $text = (string) file_get_contents($file);
        // ignore fenced code blocks
        $text = (string) preg_replace('/```.*?```/s', '', $text);

        preg_match_all('/!?\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', $text, $matches);

        foreach ($matches[1] as $target) {
            if (preg_match('#^(https?:|mailto:|\#)#', $target)) {
                continue;
            }

            $path = explode('#', $target)[0];

            if ($path === '' || ! file_exists(dirname($file).'/'.$path)) {
                $broken[] = basename(dirname($file)).'/'.basename($file).' -> '.$target;
            }
        }
    }

    expect($broken)->toBe([]);
});

it('mentions every doc page in the docs index', function () {
    $index = (string) file_get_contents(rootPath('docs/README.md'));
    $missing = [];

    foreach (glob(rootPath('docs/*.md')) ?: [] as $file) {
        if (basename($file) !== 'README.md' && ! str_contains($index, '('.basename($file).')')) {
            $missing[] = basename($file);
        }
    }

    expect($missing)->toBe([]);
});

it('keeps the generated API reference up to date', function () {
    $generated = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(rootPath('bin/api-reference.php')));

    expect(trim((string) $generated))->toBe(trim((string) file_get_contents(rootPath('docs/api-reference.md'))));
})->group('docs');

it('documents every public NavItem and plugin option in the API reference', function () {
    $reference = (string) file_get_contents(rootPath('docs/api-reference.md'));

    foreach (['searchable', 'recent', 'pinnable', 'accordion', 'pollBadges', 'childDisplay', 'flatten'] as $method) {
        expect($reference)->toContain('`'.$method.'(');
    }

    foreach (['inline', 'drilldown', 'record', 'recordPages', 'limit', 'heading', 'divider', 'badge', 'activeWhen', 'activeOn'] as $method) {
        expect($reference)->toContain($method.'(');
    }
});

it('has no leftovers of removed examples or unfinished markers in the docs', function () {
    $offenders = [];

    foreach (markdownFiles() as $file) {
        $text = (string) file_get_contents($file);

        foreach (['an project', 'AgentResource', 'TODO', 'FIXME', 'lorem ipsum'] as $needle) {
            if (str_contains($text, $needle)) {
                $offenders[] = basename($file).': '.$needle;
            }
        }
    }

    expect($offenders)->toBe([]);
});
