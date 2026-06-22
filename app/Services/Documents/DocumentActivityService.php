<?php

namespace App\Services\Documents;

use App\Models\DocumentActivity;
use Illuminate\Database\Eloquent\Model;

class DocumentActivityService
{
    public function record(
        string $type,
        Model $document,
        string $action,
        array $metadata = [],
        string $actorType = 'user',
        ?int $actorId = null,
        ?string $actorName = null,
    ): DocumentActivity {
        $user = auth()->user();

        return DocumentActivity::create([
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'action' => $action,
            'actor_type' => $actorType,
            'actor_id' => $actorId ?? $user?->id,
            'actor_name' => $actorName ?? $user?->name,
            'metadata' => $metadata ?: null,
        ]);
    }
}
