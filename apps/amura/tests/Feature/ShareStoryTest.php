<?php

namespace Tests\Feature;

use App\Support\PrizeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\User;
use Tests\TestCase;

/** Share hadiah ke story Instagram/WhatsApp dan foto hadiah di Panel Game (Fai 2026-10-21). */
final class ShareStoryTest extends TestCase
{
    use RefreshDatabase;

    private string $defaultPhoto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultPhoto = public_path('img/hadiah/'.$this->brand().'/voucher-uji-foto.webp');
    }

    protected function tearDown(): void
    {
        @unlink($this->defaultPhoto);
        parent::tearDown();
    }

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function voucher(): PrizeCatalog
    {
        return PrizeCatalog::create(['brand' => $this->brand(), 'name' => 'Voucher Uji Foto', 'tier' => 'voucher', 'is_capped' => false, 'active' => true]);
    }

    private function admin(): User
    {
        return User::create(['name' => 'GA', 'email' => 'ga@x.id', 'password' => 'rahasia', 'role' => 'game_admin', 'brand_scope' => $this->brand()]);
    }

    public function test_papan_membawa_data_share_tanpa_data_pribadi(): void
    {
        $this->post('/play/start', ['awb' => 'JX777', 'phone' => '08123456789'])->assertRedirect();
        $session = GameSession::where('awb', 'JX777')->firstOrFail();

        $this->playAs($session)->get("/play/{$session->session_token}")->assertOk()
            ->assertSee('js/share-story.js', false)
            ->assertSee('id="shareSheet"', false)
            ->assertSee('cara-main?ref=story', false);
    }

    public function test_hasil_buka_kado_membawa_foto_hadiah(): void
    {
        $prize = $this->voucher();
        @mkdir(dirname($this->defaultPhoto), 0775, true);
        file_put_contents($this->defaultPhoto, 'foto');
        $this->assertSame(asset('img/hadiah/'.$this->brand().'/voucher-uji-foto.webp'), PrizeImage::url($prize));

        $this->post('/play/start', ['awb' => 'JX778', 'phone' => '08123456780'])->assertRedirect();
        $session = GameSession::where('awb', 'JX778')->firstOrFail();
        $session->prizes()->where('round', 1)->whereIn('slot', [1, 2, 3])->update(['tier' => 'voucher', 'prize_catalog_id' => null]);
        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();

        $revealed = $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk()->json('revealed');
        $this->assertSame(PrizeImage::url($prize), $revealed[0]['image']);
    }

    public function test_hadiah_tanpa_foto_memakai_ilustrasi(): void
    {
        @unlink($this->defaultPhoto);
        $this->assertNull(PrizeImage::url($this->voucher()));
        $this->assertNull(PrizeImage::url(null));
    }

    public function test_game_admin_mengganti_dan_menghapus_foto_story(): void
    {
        Storage::fake('public');
        $prize = $this->voucher();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/game/catalog/{$prize->id}", ['active' => 1, 'image' => UploadedFile::fake()->image('foto.png', 600, 600)])
            ->assertSessionHasNoErrors();
        $path = $prize->fresh()->image;
        Storage::disk('public')->assertExists($path);
        $this->assertSame(asset('storage/'.$path), PrizeImage::url($prize->fresh()));

        $this->actingAs($admin)->get('/admin/game')->assertOk()->assertSee('Pakai foto bawaan');

        $this->actingAs($admin)->post("/admin/game/catalog/{$prize->id}", ['active' => 1, 'remove_image' => 1])->assertSessionHasNoErrors();
        $this->assertNull($prize->fresh()->image);
        Storage::disk('public')->assertMissing($path);

        $this->actingAs($admin)->post("/admin/game/catalog/{$prize->id}", ['active' => 1, 'image' => UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('image');
    }
}
