<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\RewardCatalogItem;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\CatalogUnavailableException;
use LoyaltyNew\Core\Prizes\RewardCatalogService;

/** Katalog tukar poin per bulan dan aturan poin (desain 1s). */
final class RewardCatalogController extends Controller
{
    private const PERIOD = ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'];

    public function index(Request $request)
    {
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('period'))
            ? $request->query('period')
            : now()->format('Y-m');
        $brand = (string) config('app.brand');

        return view('admin.catalog', [
            'period' => $period,
            'previous' => RewardCatalogService::previousPeriod($period),
            'catalog' => RewardCatalog::where('period', $period)->first(),
            'active' => RewardCatalogService::active($brand),
            'settings' => LoyaltySettings::current(),
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate(['period' => self::PERIOD] + $this->itemRules());

        RewardCatalogService::forPeriod((string) config('app.brand'), $data['period'])->items()->create([
            'name' => $data['name'],
            'points_cost' => $data['points_cost'],
            'stock' => $data['stock'] ?? null,
            'sort' => $data['sort'] ?? 0,
            'active' => true,
        ]);

        return redirect()->route('admin.catalog', ['period' => $data['period']])->with('ok', 'Item ditambahkan.');
    }

    public function updateItem(Request $request, string $item)
    {
        $model = RewardCatalogItem::whereKey((int) $item)
            ->whereHas('catalog', fn ($q) => $q->where('brand', config('app.brand')))
            ->firstOrFail();
        $data = $request->validate($this->itemRules() + ['active' => ['nullable', 'boolean']]);

        $model->update([
            'name' => $data['name'],
            'points_cost' => $data['points_cost'],
            'stock' => $data['stock'] ?? null,
            'sort' => $data['sort'] ?? 0,
            'active' => (bool) ($data['active'] ?? false),
        ]);

        return back()->with('ok', 'Item diperbarui.');
    }

    public function copy(Request $request)
    {
        $data = $request->validate(['period' => self::PERIOD]);
        $from = RewardCatalogService::previousPeriod($data['period']);

        try {
            $count = RewardCatalogService::copyFrom((string) config('app.brand'), $from, $data['period']);
        } catch (CatalogUnavailableException $e) {
            return back()->withErrors(['catalog' => $e->getMessage()]);
        }

        return redirect()->route('admin.catalog', ['period' => $data['period']])->with('ok', "{$count} item disalin dari {$from}.");
    }

    public function publish(Request $request)
    {
        $data = $request->validate(['period' => self::PERIOD]);
        RewardCatalogService::publish((string) config('app.brand'), $data['period']);

        return redirect()->route('admin.catalog', ['period' => $data['period']])->with('ok', "Katalog {$data['period']} diterbitkan.");
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'per_gift' => ['required', 'integer', 'min:1', 'max:100000'],
            'min_redeem' => ['required', 'integer', 'min:1', 'max:1000000'],
            'validity_months' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        LoyaltySettings::store($data);

        return back()->with('ok', 'Pengaturan poin disimpan.');
    }

    private function itemRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'points_cost' => ['required', 'integer', 'min:1', 'max:10000000'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
