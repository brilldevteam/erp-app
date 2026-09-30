<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Passwords for the accounts created during installation. They come from ADMIN_DEFAULT_PASSWORD
 * or are generated randomly, and are handed to the installer once instead of using a known default.
 */
class InitialAccountCredentials
{
    private static array $issued = [];

    public static function password(): string
    {
        return config('app.admin_default_password') ?: Str::password(16, symbols: false);
    }

    public static function issue(string $email, string $password): void
    {
        self::$issued[$email] = $password;
    }

    public static function issued(): array
    {
        return self::$issued;
    }

    /** Stores the credentials issued in this run so the installer can show them after seeding. */
    public static function persist(): void
    {
        if (self::$issued) {
            File::ensureDirectoryExists(dirname(self::path()));
            File::put(self::path(), json_encode(self::$issued));
        }
    }

    /** Returns the stored credentials once and deletes them. */
    public static function pull(): array
    {
        if (!File::exists(self::path())) {
            return [];
        }

        $credentials = json_decode(File::get(self::path()), true) ?: [];
        File::delete(self::path());

        return $credentials;
    }

    private static function path(): string
    {
        return storage_path('app/private/initial-credentials.json');
    }
}
