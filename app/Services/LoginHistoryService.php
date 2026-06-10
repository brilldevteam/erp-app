<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LoginHistoryService
{
    public function record(Request $request, User $user): void
    {
        $ip = $request->ip();
        $details = array_merge(
            $this->locationData($ip),
            parseBrowserData($request->userAgent()),
            [
                'status' => 'success',
                'referrer_host' => $request->headers->get('referer')
                    ? parse_url($request->headers->get('referer'), PHP_URL_HOST)
                    : null,
                'referrer_path' => $request->headers->get('referer')
                    ? parse_url($request->headers->get('referer'), PHP_URL_PATH)
                    : null,
            ]
        );

        LoginHistory::create([
            'user_id' => $user->id,
            'ip' => $ip,
            'date' => now()->toDateString(),
            'details' => $details,
            'type' => $user->type,
            'created_by' => in_array($user->type, ['superadmin', 'company'], true)
                ? $user->id
                : $user->created_by,
        ]);
    }

    private function locationData(string $ip): array
    {
        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'country' => $data['country'] ?? null,
                    'countryCode' => $data['countryCode'] ?? null,
                    'region' => $data['region'] ?? null,
                    'regionName' => $data['regionName'] ?? null,
                    'city' => $data['city'] ?? null,
                    'zip' => $data['zip'] ?? null,
                    'lat' => $data['lat'] ?? null,
                    'lon' => $data['lon'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                    'isp' => $data['isp'] ?? null,
                    'org' => $data['org'] ?? null,
                    'as' => $data['as'] ?? null,
                    'query' => $data['query'] ?? $ip,
                ];
            }
        } catch (\Throwable) {
        }

        return ['query' => $ip];
    }
}
