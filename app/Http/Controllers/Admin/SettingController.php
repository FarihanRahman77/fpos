<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SettingController extends Controller
{
    public function index()
    {
        $setting = Setting::first();

        if (!$setting) {
            $setting = new Setting();
        }

        return view('admin.setting.index', compact('setting'));
    }


    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_short_name' => 'nullable|string|max:255',
            'company_tagline' => 'nullable|string|max:255',

            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'logo_dark' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,ico|max:1024',
            'signature' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'watermark' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',

            'phone' => 'nullable|string|max:50',
            'phone_two' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'email_two' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',

            'trade_license' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:255',
            'bin' => 'nullable|string|max:255',
            'gst' => 'nullable|string|max:255',
            'tax_rate' => 'nullable|numeric|min:0|max:100',

            'currency' => 'nullable|string|max:20',
            'currency_symbol' => 'nullable|string|max:20',
            'currency_position' => 'nullable|in:before,after',

            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'invoice_note' => 'nullable|string',
            'terms_conditions' => 'nullable|string',

            'facebook' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'youtube' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',

            'timezone' => 'nullable|string|max:100',
            'date_format' => 'nullable|string|max:50',
            'time_format' => 'nullable|string|max:50'
        ]);


        $setting = Setting::first();

        if (!$setting) {
            $setting = new Setting();
        }


        /*
        |--------------------------------------------------------------------------
        | Company
        |--------------------------------------------------------------------------
        */

        $setting->company_name = $request->company_name;
        $setting->company_short_name = $request->company_short_name;
        $setting->company_tagline = $request->company_tagline;


        /*
        |--------------------------------------------------------------------------
        | Address
        |--------------------------------------------------------------------------
        */

        $setting->address = $request->address;
        $setting->city = $request->city;
        $setting->state = $request->state;
        $setting->country = $request->country;
        $setting->postal_code = $request->postal_code;


        /*
        |--------------------------------------------------------------------------
        | Contact
        |--------------------------------------------------------------------------
        */

        $setting->phone = $request->phone;
        $setting->phone_two = $request->phone_two;
        $setting->email = $request->email;
        $setting->email_two = $request->email_two;
        $setting->website = $request->website;


        /*
        |--------------------------------------------------------------------------
        | Tax / Business
        |--------------------------------------------------------------------------
        */

        $setting->trade_license = $request->trade_license;
        $setting->tin = $request->tin;
        $setting->bin = $request->bin;
        $setting->gst = $request->gst;
        $setting->tax_rate = $request->tax_rate ?? 0;


        /*
        |--------------------------------------------------------------------------
        | Currency
        |--------------------------------------------------------------------------
        */

        $setting->currency = $request->currency;
        $setting->currency_symbol = $request->currency_symbol;
        $setting->currency_position = $request->currency_position;


        /*
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        */

        $setting->header_text = $request->header_text;
        $setting->footer_text = $request->footer_text;
        $setting->invoice_note = $request->invoice_note;
        $setting->terms_conditions = $request->terms_conditions;


        /*
        |--------------------------------------------------------------------------
        | Social
        |--------------------------------------------------------------------------
        */

        $setting->facebook = $request->facebook;
        $setting->instagram = $request->instagram;
        $setting->youtube = $request->youtube;
        $setting->linkedin = $request->linkedin;


        /*
        |--------------------------------------------------------------------------
        | System
        |--------------------------------------------------------------------------
        */

        $setting->timezone = $request->timezone;
        $setting->date_format = $request->date_format;
        $setting->time_format = $request->time_format;
        $setting->status = 'Active';
        $setting->deleted = 'No';


        /*
        |--------------------------------------------------------------------------
        | Upload Directory
        |--------------------------------------------------------------------------
        */

        $uploadPath = public_path('uploads/settings');

        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }


        /*
        |--------------------------------------------------------------------------
        | Logo
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('logo')) {

            if ($setting->logo && File::exists(public_path($setting->logo))) {
                File::delete(public_path($setting->logo));
            }

            $file = $request->file('logo');

            $fileName = 'logo_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->logo = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Dark Logo
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('logo_dark')) {

            if ($setting->logo_dark && File::exists(public_path($setting->logo_dark))) {
                File::delete(public_path($setting->logo_dark));
            }

            $file = $request->file('logo_dark');

            $fileName = 'logo_dark_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->logo_dark = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Favicon
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('favicon')) {

            if ($setting->favicon && File::exists(public_path($setting->favicon))) {
                File::delete(public_path($setting->favicon));
            }

            $file = $request->file('favicon');

            $fileName = 'favicon_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->favicon = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Signature
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('signature')) {

            if ($setting->signature && File::exists(public_path($setting->signature))) {
                File::delete(public_path($setting->signature));
            }

            $file = $request->file('signature');

            $fileName = 'signature_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->signature = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Watermark
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('watermark')) {

            if ($setting->watermark && File::exists(public_path($setting->watermark))) {
                File::delete(public_path($setting->watermark));
            }

            $file = $request->file('watermark');

            $fileName = 'watermark_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->watermark = 'uploads/settings/' . $fileName;
        }


        $setting->save();


        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'General settings updated successfully.');
    }
}
