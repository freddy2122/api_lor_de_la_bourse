<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    // GET /api/admin/settings/fees -> { key: 'commission_rate', value: '1.5' }
    public function getCommission()
    {
        $setting = Setting::find('commission_rate');
        return response()->json([
            'key' => 'commission_rate',
            'value' => $setting?->value ?? null,
        ]);
    }

    // PUT /api/admin/settings/fees { value: number|string }
    public function updateCommission(Request $request)
    {
        $data = $request->validate([
            'value' => ['required','numeric','min:0','max:100'],
        ]);

        $setting = Setting::updateOrCreate(
            ['key' => 'commission_rate'],
            ['value' => (string)$data['value']]
        );

        return response()->json([
            'message' => 'Taux de commission mis à jour.',
            'data' => [
                'key' => $setting->key,
                'value' => $setting->value,
            ],
        ]);
    }
}
