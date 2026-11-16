<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The only text the plugin owns is "Back" and the landmark label; labels are the app's own. */
class TranslationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, string}> */
    public static function languages(): array
    {
        return [
            'English' => ['en', 'Back', 'Navigation'],
            '简体中文' => ['zh_CN', '返回', '导航'],
            '繁體中文' => ['zh_TW', '返回', '導覽'],
            '粵語 (HK)' => ['zh_HK', '返回', '導覽'],
            '日本語' => ['ja', '戻る', 'ナビゲーション'],
            '한국어' => ['ko', '뒤로', '탐색'],
            'Español' => ['es', 'Atrás', 'Navegación'],
        ];
    }

    #[DataProvider('languages')]
    public function test_back_link_and_landmark_are_translated(string $locale, string $back, string $landmark): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/admin/reports/usage?lang='.$locale);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('#class="fi-drilldown-back"[^>]*>.*?'.preg_quote($back, '#').'\s*</a>#su', $html);
        $this->assertStringContainsString('aria-label="'.$landmark.'"', $html);
    }

    public function test_every_shipped_language_has_the_same_keys(): void
    {
        $english = array_keys(require __DIR__.'/../../../resources/lang/en/messages.php');

        foreach (glob(__DIR__.'/../../../resources/lang/*/messages.php') ?: [] as $file) {
            $this->assertSame($english, array_keys(require $file), basename(dirname($file)));
        }
    }

    public function test_unknown_language_falls_back_to_english(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/admin/reports/usage?lang=xx');

        $this->assertStringContainsString('Back', (string) $response->getContent());
    }
}
