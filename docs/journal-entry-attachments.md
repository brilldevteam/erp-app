# Journal Entry Supporting Documents

Supporting documents are available below Description on Create Journal Entry.
The View page also supports adding documents without changing posted figures.
There is no existing Edit Journal Entry page.

- PDF, JPG/JPEG, PNG, Word (DOC/DOCX), Excel (XLS/XLSX), and ZIP; up to five files per journal, 5 MB per file.
- Office files and ZIP are download-only. Archives are never extracted or executed.
- Create permission permits uploads; View permission permits previews/downloads;
  Delete permission permits removal. All endpoints enforce company ownership.
- Files use the private local disk, never the public storage link.
- Original names, MIME types, sizes, uploaders and timestamps are saved.
  Removed attachment metadata is retained with removed_by and deleted_at.
- Failed journal saves clean up their uploaded files. Deleting a journal also
  removes its attachment files. No posting or report calculations are changed.

## Deployment

Run `php artisan migrate` and rebuild frontend assets as normal.
Ensure the PHP worker can write to `storage/app/private`.
Set `upload_max_filesize = 5M` (or greater), `post_max_size = 32M` (or greater),
and `max_file_uploads = 5` (or greater). Match the proxy request-size limit to
at least 32 MB. Reload PHP after configuration changes.
Back up private attachments together with the database.

## Verification

`tests/Feature/JournalAttachmentsTest.php` uses in-memory SQLite and fake storage.
It checks file validation, private retrieval, company/permission isolation,
the total attachment limit, removal audit and failed-save cleanup.
