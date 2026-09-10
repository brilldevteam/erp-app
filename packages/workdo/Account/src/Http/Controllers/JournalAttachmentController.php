<?php
namespace Workdo\Account\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryAttachment;
use Workdo\Account\Services\JournalAttachmentService;
class JournalAttachmentController extends Controller
{
    private function checkAccess(JournalEntry $journalEntry, string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403);
        abort_unless((int) $journalEntry->created_by === (int) creatorId() && $journalEntry->entry_type === 'manual', 404);
    }
    public function store(Request $request, JournalEntry $journalEntry, JournalAttachmentService $service)
    {
        $this->checkAccess($journalEntry, 'create-journal-entries');
        $request->validate(array_merge($service::rules(), ['attachments' => 'required|array|min:1|max:5']));
        $paths = [];
        try {
            DB::transaction(function () use ($request, $journalEntry, $service, &$paths) {
                $locked = JournalEntry::whereKey($journalEntry->id)->lockForUpdate()->firstOrFail();
                $service->store($locked, $request->file('attachments', []), $paths);
            });
        } catch (\Throwable $e) { $service->cleanup($paths); throw $e; }
        return back()->with('success', __('Supporting documents saved.'));
    }
    public function download(Request $request, JournalEntry $journalEntry, JournalEntryAttachment $attachment)
    {
        $this->checkAccess($journalEntry, 'view-journal-entries');
        abort_unless((int) $attachment->journal_entry_id === (int) $journalEntry->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);
        return Storage::disk('local')->response($attachment->file_path, $attachment->file_name,
            ['Content-Type' => $attachment->file_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
            $request->boolean('preview') && in_array($attachment->file_type, ['application/pdf', 'image/jpeg', 'image/png'], true) ? 'inline' : 'attachment');
    }
    public function destroy(JournalEntry $journalEntry, JournalEntryAttachment $attachment, JournalAttachmentService $service)
    {
        $this->checkAccess($journalEntry, 'delete-journal-entries');
        abort_unless((int) $attachment->journal_entry_id === (int) $journalEntry->id, 404);
        DB::transaction(function () use ($attachment) {
            $attachment->update(['removed_by' => auth()->id()]);
            $attachment->delete();
        });
        $service->cleanup([$attachment->file_path]);
        return back()->with('success', __('Supporting document removed.'));
    }
}
