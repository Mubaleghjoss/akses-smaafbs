<?php

namespace App\Http\Controllers;

use App\Contracts\SiteSettingsAccessor;
use App\Filament\Resources\SarprasBospInventoryResource;
use App\Models\SarprasBospInventory;
use Illuminate\Contracts\View\View;

class SarprasBospInventoryPublicController extends Controller
{
    public function __invoke(SarprasBospInventory $sarprasBospInventory): View
    {
        return view('sarpras.bosp-inventory-show', [
            'record' => $sarprasBospInventory,
            'schoolName' => app(SiteSettingsAccessor::class)->siteName(),
            'bulanOptions' => SarprasBospInventoryResource::bulanOptions(),
            'meta' => [
                'title' => 'Data Sarpras BOSP - '.$sarprasBospInventory->nama_barang,
                'description' => 'Informasi inventaris sarpras BOSP hasil scan QR code.',
                'theme_color' => '#0f172a',
                'canonical_url' => url()->current(),
                'manifest_url' => route('manifest.webmanifest'),
                'favicon_url' => asset('favicon.ico'),
                'apple_touch_icon' => null,
                'og_type' => 'website',
                'og_site_name' => app(SiteSettingsAccessor::class)->siteName(),
                'og_title' => 'Data Sarpras BOSP - '.$sarprasBospInventory->nama_barang,
                'og_description' => 'Informasi inventaris sarpras BOSP hasil scan QR code.',
                'og_url' => url()->current(),
                'og_image' => asset('favicon.ico'),
                'twitter_card' => 'summary',
                'twitter_title' => 'Data Sarpras BOSP - '.$sarprasBospInventory->nama_barang,
                'twitter_description' => 'Informasi inventaris sarpras BOSP hasil scan QR code.',
                'twitter_image' => null,
            ],
        ]);
    }
}
