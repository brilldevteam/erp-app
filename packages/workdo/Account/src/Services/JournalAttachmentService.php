<?php
namespace Workdo\Account\Services;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Workdo\Account\Models\JournalEntry;
class JournalAttachmentService
{
    public static function rules(): array
    {
        return ['attachments' => 'nullable|array|max:5', 'attachments.*' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|extensions:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|max:5120'];
    }
    // Caller owns the transaction and cleans up the paths on rollback.
    public function store(JournalEntry $journal, array $files, array &$paths): void
    {
        if ($journal->attachments()->count() + count($files) > 5) {
            throw ValidationException::withMessages(['attachments' => __('A journal can have up to 5 supporting documents.')]);
        }
        foreach ($files as $file) {
            $path = $file->store('journal-entries/'.$journal->created_by.'/'.$journal->id, 'local');
            if (!$path) throw ValidationException::withMessages(['attachments' => __('The document could not be stored. Please try again.')]);
            $paths[] = $path;
            $journal->attachments()->create([
                'file_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                'file_path' => $path, 'file_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'uploaded_by' => auth()->id(),
            ]);
        }
    }
    public function cleanup(array $paths): void { if ($paths) Storage::disk('local')->delete($paths); }
}
