<?php

namespace Workdo\Account\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PartyAttachmentService
{
    public static function rules(): array
    {
        return [
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|extensions:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|max:5120',
        ];
    }

    public function store(Model $party, array $files, array &$paths): void
    {
        if ($party->attachments()->count() + count($files) > 5) {
            throw ValidationException::withMessages(['attachments' => __('A customer or vendor can have up to 5 supporting documents.')]);
        }

        $folder = $party instanceof \Workdo\Account\Models\Customer ? 'customers' : 'vendors';
        foreach ($files as $file) {
            $path = $file->store("{$folder}/{$party->created_by}/{$party->id}", 'local');
            if (!$path) {
                throw ValidationException::withMessages(['attachments' => __('The document could not be stored. Please try again.')]);
            }
            $paths[] = $path;
            $party->attachments()->create([
                'file_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }

    public function cleanup(array $paths): void
    {
        if ($paths) Storage::disk('local')->delete($paths);
    }
}
