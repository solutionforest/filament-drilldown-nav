<?php

/**
 * Smoke test against a REAL Octane worker (one worker, so every request reuses the same process).
 *
 *   php bin/octane-smoke.php http://127.0.0.1:8766
 *
 * Signs in as different users, alternates between panels, renames a record between requests, and
 * checks that every response shows exactly one drill-down sidebar with the right content.
 */

use App\Models\Project;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8766', '/');
$failures = [];
$checks = 0;

$session = function (string $email) use ($base): Client {
    $client = new Client(['base_uri' => $base, 'cookies' => new CookieJar, 'http_errors' => false, 'allow_redirects' => true, 'timeout' => 30]);
    $response = $client->get('/_demo/login/'.$email);
    if ($response->getStatusCode() !== 200) {
        fwrite(STDERR, "Could not sign in as {$email} (HTTP {$response->getStatusCode()}). Is DEMO_AUTOLOGIN=true set for the server?\n");
        exit(2);
    }

    return $client;
};

/** @return array{status: int, navs: int, text: string} */
$visit = function (Client $client, string $path): array {
    $response = $client->get($path);
    $html = (string) $response->getBody();
    $navs = substr_count($html, 'class="fi-drilldown-nav"');
    preg_match('#class="fi-drilldown-nav"[^>]*>(.*?)</nav>#s', $html, $m);

    return [
        'status' => $response->getStatusCode(),
        'navs' => $navs,
        'text' => isset($m[1]) ? trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1]))) : '',
    ];
};

$expect = function (string $label, bool $condition, string $detail = '') use (&$failures, &$checks): void {
    $checks++;
    if (! $condition) {
        $failures[] = $label.($detail !== '' ? " ({$detail})" : '');
    }
};

$admin = $session('demo@example.com');
$member = $session('member@example.com');
$project = Project::query()->orderBy('id')->firstOrFail();
$original = $project->name;

try {
    for ($round = 1; $round <= 3; $round++) {
        $a = $visit($admin, '/admin');
        $expect("round {$round}: admin root renders one sidebar", $a['status'] === 200 && $a['navs'] === 1, "{$a['status']}/{$a['navs']}");
        $expect("round {$round}: admin sees Audit log and Clients", str_contains($a['text'], 'Audit log') && str_contains($a['text'], 'Clients'), $a['text']);

        $m = $visit($member, '/admin');
        $expect("round {$round}: member root renders one sidebar", $m['status'] === 200 && $m['navs'] === 1);
        $expect("round {$round}: member does NOT see admin-only items (no leak from the previous request)", ! str_contains($m['text'], 'Audit log') && ! str_contains($m['text'], 'Clients'), $m['text']);

        $p = $visit($admin, '/portal');
        $expect("round {$round}: portal shows its own tree", $p['navs'] === 1 && str_contains($p['text'], 'Support') && ! str_contains($p['text'], 'Clients'), $p['text']);

        $plain = $visit($admin, '/plain');
        $expect("round {$round}: panel without the plugin keeps Filament's sidebar", $plain['status'] === 200 && $plain['navs'] === 0, "{$plain['status']}/{$plain['navs']}");

        $name = $original." (round {$round})";
        $project->update(['name' => $name]);
        $r = $visit($admin, "/admin/projects/{$project->id}");
        $expect("round {$round}: a record renamed between requests shows its new name immediately", $r['navs'] === 1 && str_contains($r['text'], $name), $r['text']);
    }
} finally {
    $project->update(['name' => $original]);
}

if ($failures !== []) {
    fwrite(STDERR, "OCTANE SMOKE FAILED ({$checks} checks, ".count($failures)." failed):\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "OCTANE SMOKE OK: {$checks} checks passed against {$base}\n";
