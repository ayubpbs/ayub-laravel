<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const PHONE = ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'];
    private const URL   = ['nullable', 'string', 'max:255', 'url', 'regex:#^https?://#i'];

    public function edit()
    {
        // what each field shows when nothing has been saved yet
        $fallback = [
            'shop_name'       => config('shop.name'),
            'tagline'         => config('shop.tagline'),
            'phone'           => config('shop.phone'),
            'address'         => config('shop.address'),
            'email'           => config('shop.email'),
            'invoice_note'    => config('shop.invoice_note'),
            'delivery_charge' => config('shop.delivery_charge'),
            'advance_amount'  => 0,
            'warranty_days'   => 0,
        ];

        $keys = array_merge(
            ['shop_name', 'tagline', 'phone', 'whatsapp', 'delivery_charge', 'advance_amount',
             'warranty_days', 'warranty_terms', 'invoice_note', 'invoice_terms',
             'address', 'email', 'business_hours', 'trade_license', 'bin_tin', 'about'],
            array_keys(Setting::socials())
        );

        $v = [];
        foreach ($keys as $k) {
            $v[$k] = old($k, Setting::get($k, $fallback[$k] ?? ''));
        }
        // on/off switches (default shown when never saved)
        $v['warranty_enabled']       = (bool) old('warranty_enabled', Setting::bool('warranty_enabled', false));
        $v['invoice_show_logo']      = (bool) old('invoice_show_logo', Setting::bool('invoice_show_logo', true));
        $v['invoice_show_phone']     = (bool) old('invoice_show_phone', Setting::bool('invoice_show_phone', true));
        $v['invoice_show_warranty']  = (bool) old('invoice_show_warranty', Setting::bool('invoice_show_warranty', true));

        return view('admin.settings.edit', [
            'v'       => $v,
            'logoUrl' => Setting::logoUrl(),
            'socials' => Setting::socials(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'shop_name'       => ['required', 'string', 'max:100'],
            'tagline'         => ['nullable', 'string', 'max:150'],
            'logo'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo'     => ['nullable', 'boolean'],
            'phone'           => self::PHONE,
            'whatsapp'        => self::PHONE,
            'delivery_charge' => ['required', 'integer', 'min:0', 'max:100000'],
            'advance_amount'  => ['required', 'integer', 'min:0', 'max:10000000'],
            'warranty_enabled' => ['nullable', 'boolean'],
            'warranty_days'   => ['nullable', 'integer', 'min:0', 'max:3650'],
            'warranty_terms'  => ['nullable', 'string', 'max:1000'],
            'invoice_note'    => ['nullable', 'string', 'max:300'],
            'invoice_terms'   => ['nullable', 'string', 'max:1000'],
            'invoice_show_logo'     => ['nullable', 'boolean'],
            'invoice_show_phone'    => ['nullable', 'boolean'],
            'invoice_show_warranty' => ['nullable', 'boolean'],
            'address'         => ['nullable', 'string', 'max:300'],
            'email'           => ['nullable', 'email', 'max:150'],
            'business_hours'  => ['nullable', 'string', 'max:150'],
            'trade_license'   => ['nullable', 'string', 'max:60'],
            'bin_tin'         => ['nullable', 'string', 'max:60'],
            'about'           => ['nullable', 'string', 'max:1000'],
        ];
        foreach (array_keys(Setting::socials()) as $k) {
            $rules[$k] = self::URL;
        }

        $data = $request->validate($rules, [
            'shop_name.required'   => 'Please enter the shop name.',
            'phone.regex'          => 'Phone number can only contain digits, + and -.',
            'whatsapp.regex'       => 'WhatsApp number can only contain digits, + and -.',
            'logo.image'           => 'The logo must be an image.',
            'logo.mimes'           => 'The logo must be a JPG, PNG or WebP file.',
            'logo.max'             => 'The logo must be 2 MB or smaller.',
            '*.url'                => 'Please enter a full link (starting with https://).',
            '*.regex'              => 'Please enter a full link starting with http:// or https://.',
        ]);

        // ---- logo ----
        $logo = Setting::get('logo');
        if ($request->hasFile('logo')) {
            $this->deleteLogoFile($logo);
            $file = $request->file('logo');
            $name = 'logo-' . time() . '.' . $file->extension();
            $file->move(public_path('uploads/settings'), $name);
            $logo = 'uploads/settings/' . $name;
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogoFile($logo);
            $logo = '';
        }

        $save = ['logo' => $logo];
        foreach ([
            'shop_name', 'tagline', 'phone', 'whatsapp', 'delivery_charge', 'advance_amount', 'warranty_days',
            'warranty_terms', 'invoice_note', 'invoice_terms', 'address', 'email', 'business_hours',
            'trade_license', 'bin_tin', 'about',
        ] as $k) {
            $save[$k] = isset($data[$k]) ? trim((string) $data[$k]) : '';
        }
        foreach (array_keys(Setting::socials()) as $k) {
            $save[$k] = isset($data[$k]) ? trim((string) $data[$k]) : '';
        }
        foreach (['warranty_enabled', 'invoice_show_logo', 'invoice_show_phone', 'invoice_show_warranty'] as $k) {
            $save[$k] = $request->boolean($k) ? 1 : 0;
        }

        Setting::setMany($save);

        return redirect()->route('admin.settings.edit')->with('success', 'Website settings saved.');
    }

    /** Only ever deletes files this page itself uploaded */
    private function deleteLogoFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/settings/') && basename($path) === substr($path, strlen('uploads/settings/'))) {
            @unlink(public_path($path));
        }
    }
}
