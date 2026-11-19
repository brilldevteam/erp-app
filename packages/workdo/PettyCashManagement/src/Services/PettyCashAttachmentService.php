<?php

namespace Workdo\PettyCashManagement\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Workdo\PettyCashManagement\Models\PettyCashAttachment;

/** Supporting documents for petty cash requests and funds, kept on the private disk. */
class PettyCashAttachmentService
{
    public const MAX_FILES = 5;

    public static function rules(): array
    {
        return [
            'attachments'   => 'nullable|array|max:' . self::MAX_FILES,
            'attachments.*' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|extensions:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|max:5120',
        ];
    }

    public static function messages(): array
    {
        return [
            'attachments.max'        => __('You can attach up to :max supporting documents.', ['max' => self::MAX_FILES]),
            'attachments.*.mimes'    => __('Select PDF, JPG, PNG, Word, Excel or ZIP files up to 5 MB each.'),
            'attachments.*.extensions' => __('Select PDF, JPG, PNG, Word, Excel or ZIP files up to 5 MB each.'),
            'attachments.*.max'      => __('Select PDF, JPG, PNG, Word, Excel or ZIP files up to 5 MB each.'),
        ];
    }

    /** Caller owns the transaction and calls cleanup($paths) if it rolls back. */
    public function store(string $type, Model $record, array $files, array &$paths): void
    {
        $existing = PettyCashAttachment::where('attachable_type', $type)->where('attachable_id', $record->id)->count();
        if ($existing + count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages(['attachments' => __('You can attach up to :max supporting documents.', ['max' => self::MAX_FILES])]);
        }

        foreach ($files as $file) {
            $path = $file->store('petty-cash/' . $record->created_by . '/' . $type . '-' . $record->id, 'local');
            if (!$path) {
                throw ValidationException::withMessages(['attachments' => __('The document could not be stored. Please try again.')]);
            }
            $paths[] = $path;

            PettyCashAttachment::create([
                'attachable_type' => $type,
                'attachable_id'   => $record->id,
                'file_name'       => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                'file_path'       => $path,
                'file_type'       => $file->getMimeType(),
                'file_size'       => $file->getSize(),
                'uploaded_by'     => auth()->id(),
                'created_by'      => $record->created_by,
            ]);
        }
    }

    /** Remove every document of a record that is being deleted. */
    public function removeAll(string $type, Model $record): void
    {
        $attachments = PettyCashAttachment::where('attachable_type', $type)->where('attachable_id', $record->id)->get();
        foreach ($attachments as $attachment) {
            $attachment->update(['removed_by' => auth()->id()]);
            $attachment->delete();
        }
        $this->cleanup($attachments->pluck('file_path')->all());
    }

    public function cleanup(array $paths): void
    {
        if ($paths) {
            Storage::disk('local')->delete($paths);
        }
    }
}
