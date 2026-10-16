<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use LoyaltyNew\Core\Content\SiteContent;

/** Teks halaman resi dan pop up promo pelanggan (Game Admin, Super Admin). */
final class DisplayController extends Controller
{
    public function index()
    {
        $content = SiteContent::current();

        return view('admin.display', [
            'content' => $content,
            'pages' => SiteContent::PAGES,
            'imageUrl' => $content['popup']['image'] ? asset('storage/'.$content['popup']['image']) : null,
        ]);
    }

    public function updateTexts(Request $request)
    {
        $data = $request->validate([
            'play_title' => ['nullable', 'string', 'max:80'],
            'play_lead' => ['nullable', 'string', 'max:160'],
        ], [
            'play_title.max' => 'Judul maksimal 80 karakter.',
            'play_lead.max' => 'Keterangan maksimal 160 karakter.',
        ]);

        SiteContent::storeTexts($data['play_title'] ?? null, $data['play_lead'] ?? null);

        return back()->with('ok', 'Teks halaman resi disimpan.');
    }

    public function updatePopup(Request $request)
    {
        $current = SiteContent::current()['popup'];
        $active = $request->boolean('active');

        $data = $request->validate([
            'image' => [Rule::requiredIf($active && ! $current['image']), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'alt' => [Rule::requiredIf($active), 'nullable', 'string', 'max:120'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'pages' => [Rule::requiredIf($active), 'array'],
            'pages.*' => [Rule::in(array_keys(SiteContent::PAGES))],
        ], [
            'image.required' => 'Pilih gambar pop up dulu.',
            'image.image' => 'File harus gambar JPG, PNG, atau WebP.',
            'image.mimes' => 'File harus gambar JPG, PNG, atau WebP.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
            'alt.required' => 'Isi teks gambar, misalnya isi promonya.',
            'link.url' => 'Link harus lengkap, diawali https://',
            'pages.required' => 'Pilih minimal satu halaman tempat pop up muncul.',
        ]);

        $image = $current['image'];
        if ($request->hasFile('image')) {
            $image = $request->file('image')->store('popups/'.config('app.brand'), 'public');
            if ($current['image']) {
                Storage::disk('public')->delete($current['image']);
            }
        }

        SiteContent::storePopup([
            'active' => $active,
            'image' => $image,
            'alt' => $data['alt'] ?? '',
            'link' => $data['link'] ?? null,
            'pages' => $data['pages'] ?? [],
        ]);

        return back()->with('ok', $active ? 'Pop up disimpan dan aktif.' : 'Pop up disimpan, tidak ditampilkan ke pelanggan.');
    }
}
