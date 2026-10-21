<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LoyaltyNew\Core\Settings\InvalidSettingsFile;
use LoyaltyNew\Core\Settings\SettingsTransfer;

/**
 * Backup setting: export/import setting game per brand (hanya Super Admin, keputusan
 * Fai 2026-10-21). Import selalu lewat pratinjau dulu; saat diterapkan, setting lama
 * disimpan sebagai backup dan dicatat di setting_imports.
 */
final class SettingTransferController extends Controller
{
    private const SESSION_KEY = 'setting_import';

    /** Pratinjau kedaluwarsa setelah sekian detik, supaya tidak menerapkan pratinjau basi. */
    private const PREVIEW_TTL = 1800;

    public function index()
    {
        return view('admin.setting', [
            'imports' => DB::table('setting_imports')
                ->leftJoin('users', 'users.id', '=', 'setting_imports.user_id')
                ->where('setting_imports.brand', $this->brand())
                ->orderByDesc('setting_imports.id')
                ->limit(20)
                ->get(['setting_imports.*', 'users.name as user_name']),
        ]);
    }

    public function export()
    {
        $json = json_encode(SettingsTransfer::export($this->brand()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(fn () => print $json, $this->fileName('export'), ['Content-Type' => 'application/json']);
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:512']], [
            'file.required' => 'Pilih file backup setting (.json) dulu.',
            'file.max' => 'File terlalu besar. Maksimal 512 KB.',
        ]);

        $upload = $request->file('file');

        try {
            $decoded = json_decode((string) file_get_contents($upload->getRealPath()), true, 32, JSON_THROW_ON_ERROR);
            $result = SettingsTransfer::inspect($decoded, $this->brand());
        } catch (\JsonException) {
            return back()->withErrors(['file' => 'File tidak bisa dibaca sebagai JSON. Pakai file hasil tombol Export.']);
        } catch (InvalidSettingsFile $e) {
            return back()->with('import_errors', $e->errors)->withErrors(['file' => 'File ditolak. Tidak ada setting yang diubah.']);
        }

        $token = Str::random(40);
        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'user_id' => $request->user()->id,
            'at' => now()->getTimestamp(),
            'file_name' => Str::limit($upload->getClientOriginalName(), 120, ''),
            'data' => $result['data'],
            'changes' => $result['changes'],
        ]);

        return view('admin.setting-preview', [
            'token' => $token,
            'fileName' => $upload->getClientOriginalName(),
            'changes' => $result['changes'],
        ]);
    }

    public function apply(Request $request)
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        $token = (string) $request->input('token');

        if (! $pending || ! hash_equals($pending['token'], $token) || $pending['user_id'] !== $request->user()->id
            || now()->getTimestamp() - $pending['at'] > self::PREVIEW_TTL) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('admin.setting')->withErrors(['file' => 'Pratinjau sudah tidak berlaku. Unggah file lagi.']);
        }

        $brand = $this->brand();
        $backup = 'setting-backups/'.$brand.'/'.$this->fileName('sebelum-import');
        Storage::disk('local')->put($backup, json_encode(SettingsTransfer::export($brand), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            SettingsTransfer::apply($pending['data'], $brand);
        } catch (InvalidSettingsFile $e) {
            Storage::disk('local')->delete($backup);
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('admin.setting')->with('import_errors', $e->errors)
                ->withErrors(['file' => 'Setting berubah sejak pratinjau, import dibatalkan. Tidak ada yang diubah.']);
        }

        DB::table('setting_imports')->insert([
            'brand' => $brand,
            'user_id' => $request->user()->id,
            'file_name' => $pending['file_name'],
            'backup_path' => $backup,
            'change_count' => count($pending['changes']),
            'created_at' => now(),
        ]);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('admin.setting')->with('ok', 'Setting diimport ('.count($pending['changes']).' perubahan). Setting lama tersimpan sebagai backup.');
    }

    public function backup(int $id)
    {
        $row = DB::table('setting_imports')->where('brand', $this->brand())->where('id', $id)->first();
        abort_unless($row && Storage::disk('local')->exists($row->backup_path), 404);

        return Storage::disk('local')->download($row->backup_path, basename($row->backup_path), ['Content-Type' => 'application/json']);
    }

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function fileName(string $kind): string
    {
        return 'loyalty-setting-'.$this->brand().'-'.$kind.'-'.now()->format('Ymd-His').'.json';
    }
}
