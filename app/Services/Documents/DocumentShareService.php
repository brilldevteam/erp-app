<?php

namespace App\Services\Documents;

use App\Models\DocumentShareLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Str;

class DocumentShareService
{
    public function __construct(private readonly DocumentActivityService $activities)
    {
    }

    public function create(string $type, Model $document, int $tenantId, int $days = 30): array
    {
        DocumentShareLink::where('document_type', $type)
            ->where('document_id', $document->getKey())
            ->whereNotNull('expires_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token = Str::random(64);
        $link = DocumentShareLink::create([
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'token_hash' => hash('sha256', $token),
            'created_by' => $tenantId,
            'expires_at' => now()->addDays(max(1, min($days, 365))),
        ]);

        $this->activities->record($type, $document, 'share_link_created', [
            'expires_at' => $link->expires_at?->toIso8601String(),
        ]);

        return ['link' => $link, 'token' => $token, 'url' => $this->documentUrl($token)];
    }

    public function permanent(string $type, Model $document, int $tenantId): array
    {
        $link = DocumentShareLink::where('document_type', $type)
            ->where('document_id', $document->getKey())
            ->where('created_by', $tenantId)
            ->whereNull('expires_at')
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        if ($link) {
            try {
                $token = $link->encrypted_token;
            } catch (DecryptException) {
                $token = null;
            }

            if ($token) {
                return ['link' => $link, 'token' => $token, 'url' => $this->documentUrl($token)];
            }

            $link->update(['revoked_at' => now()]);
        }

        $token = Str::random(64);
        $link = DocumentShareLink::create([
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'token_hash' => hash('sha256', $token),
            'encrypted_token' => $token,
            'created_by' => $tenantId,
            'expires_at' => null,
        ]);

        $this->activities->record($type, $document, 'qr_link_created');

        return ['link' => $link, 'token' => $token, 'url' => $this->documentUrl($token)];
    }

    public function resolve(string $token): DocumentShareLink
    {
        $link = DocumentShareLink::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($link->isUsable(), 410, __('This document link has expired or was revoked.'));

        return $link;
    }

    public function recordView(DocumentShareLink $link, Model $document, Request $request): void
    {
        $now = now();
        $link->forceFill([
            'first_viewed_at' => $link->first_viewed_at ?: $now,
            'last_viewed_at' => $now,
            'view_count' => $link->view_count + 1,
            'last_ip' => $request->ip(),
            'last_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ])->save();

        $document->forceFill([
            'first_viewed_at' => $document->first_viewed_at ?: $now,
            'last_viewed_at' => $now,
        ])->save();

        $this->activities->record(
            $link->document_type,
            $document,
            'viewed',
            ['ip' => $request->ip()],
            'customer',
        );
    }

    public function revoke(string $type, Model $document): int
    {
        $count = DocumentShareLink::where('document_type', $type)
            ->where('document_id', $document->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($count) {
            $snapshot = $document->document_snapshot;
            if (is_array($snapshot) && array_key_exists('qr', $snapshot)) {
                unset($snapshot['qr']);
                $document->forceFill(['document_snapshot' => $snapshot])->save();
            }
            $this->activities->record($type, $document, 'share_link_revoked');
        }

        return $count;
    }

    private function documentUrl(string $token): string
    {
        $baseUrl = rtrim((string) config('documents.public_url', config('app.url')), '/');

        return $baseUrl . route('documents.public.show', $token, false);
    }
}
