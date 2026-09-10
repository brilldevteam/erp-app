<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Workdo\Account\Http\Controllers\JournalAttachmentController;
use Workdo\Account\Http\Controllers\JournalEntryController;
use Workdo\Account\Http\Requests\StoreJournalEntryRequest;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryAttachment;
use Workdo\Account\Services\JournalAttachmentService;

class JournalAttachmentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'attachment_test', 'database.connections.attachment_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        DB::purge('attachment_test');
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); });
        DB::table('users')->insert([['id'=>1,'name'=>'Test'],['id'=>2,'name'=>'Other']]);
        Schema::create('chart_of_accounts', function (Blueprint $t) { $t->id(); });
        DB::table('chart_of_accounts')->insert([['id'=>1],['id'=>2]]);
        (require base_path('packages/workdo/Account/src/Database/Migrations/2025_09_25_100916_create_journal_entries_table.php'))->up();
        (require base_path('packages/workdo/Account/src/Database/Migrations/2025_09_25_100917_create_journal_entry_items_table.php'))->up();
        (require base_path('database/migrations/2026_09_08_000001_create_journal_entry_attachments_table.php'))->up();
        Storage::fake('local');
        $this->loginWithPermission(true);
    }

    private function loginWithPermission(bool $allowed): void
    {
        $user=Mockery::mock(User::class)->makePartial();
        $user->id=1; $user->type='company';
        $user->shouldReceive('can')->andReturn($allowed);
        $this->actingAs($user);
    }

    private function journal(int $company=1): JournalEntry
    {
        return JournalEntry::create(['journal_date'=>'2026-09-08','entry_type'=>'manual','reference_type'=>'Test','description'=>'Test','total_debit'=>10,'total_credit'=>10,'status'=>'posted','creator_id'=>$company,'created_by'=>$company]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    }

    public function test_validation_rejects_invalid_extension_size_and_count(): void
    {
        $rules=JournalAttachmentService::rules();
        $this->assertTrue(Validator::make(['attachments'=>[$this->pdf()]],$rules)->passes());
        $this->assertTrue(Validator::make(['attachments'=>[UploadedFile::fake()->create('bad.exe',1,'application/octet-stream')]],$rules)->fails());
        $this->assertTrue(Validator::make(['attachments'=>[UploadedFile::fake()->create('large.pdf',5121,'application/pdf')]],$rules)->fails());
        $this->assertTrue(Validator::make(['attachments'=>array_fill(0,6,$this->pdf())],$rules)->fails());
        $this->assertTrue(Validator::make([],$rules)->passes());
    }

    public function test_private_save_download_preview_and_removal_keep_audit_and_figures(): void
    {
        $journal=$this->journal();$service=new JournalAttachmentService();$paths=[];
        $service->store($journal,[$this->pdf(),$this->pdf()],$paths);
        $this->assertCount(2,$journal->attachments);
        $attachment=$journal->attachments->first();
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertArrayNotHasKey('file_path',$attachment->toArray());
        $controller=new JournalAttachmentController();
        $response=$controller->download(Request::create('/'),$journal,$attachment);
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $preview=$controller->download(Request::create('/?preview=1'),$journal,$attachment);
        $this->assertStringContainsString('inline;', $preview->headers->get('Content-Disposition'));
        $controller->destroy($journal,$attachment,$service);
        Storage::disk('local')->assertMissing($attachment->file_path);
        $this->assertSame(1,(int)JournalEntryAttachment::withTrashed()->find($attachment->id)->removed_by);
        $this->assertTrue(JournalEntryAttachment::withTrashed()->find($attachment->id)->trashed());
        $this->assertEquals(10,$journal->fresh()->total_debit);
        $this->assertEquals(10,$journal->fresh()->total_credit);
    }

    public function test_cross_company_wrong_journal_and_missing_permission_are_denied(): void
    {
        $journal=$this->journal();$other=$this->journal(2);$paths=[];
        (new JournalAttachmentService())->store($journal,[$this->pdf()],$paths);
        $attachment=$journal->attachments()->first();$controller=new JournalAttachmentController();
        foreach([[$other,true,404],[$this->journal(),true,404],[$journal,false,403]] as [$target,$allowed,$status]) {
            $this->loginWithPermission($allowed);
            try { $controller->download(Request::create('/'),$target,$attachment); $this->fail('Access allowed'); }
            catch(HttpException $e){ $this->assertSame($status,$e->getStatusCode()); }
        }
    }

    public function test_existing_attachments_count_towards_limit(): void
    {
        $journal=$this->journal();$service=new JournalAttachmentService();$paths=[];
        $service->store($journal,array_fill(0,5,$this->pdf()),$paths);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->store($journal,[$this->pdf()],$paths);
    }

    public function test_failed_journal_save_rolls_back_rows_and_uploaded_files(): void
    {
        $request=Mockery::mock(StoreJournalEntryRequest::class)->makePartial();
        $request->shouldReceive('validated')->andReturn(['journal_date'=>'2026-09-08','reference_type'=>'Test','description'=>'Test','items'=>[
            ['account_id'=>1,'description'=>'Debit','debit_amount'=>10,'credit_amount'=>0],
            ['account_id'=>2,'description'=>'Credit','debit_amount'=>0,'credit_amount'=>10],
        ]]);
        $request->shouldReceive('file')->with('attachments',[])->andReturn([$this->pdf()]);
        JournalEntryAttachment::creating(function () { throw new \RuntimeException('Simulated DB failure'); });
        try { (new JournalEntryController())->store($request); $this->fail('Save should fail'); }
        catch(\RuntimeException $e){ $this->assertSame('Simulated DB failure',$e->getMessage()); }
        finally { JournalEntryAttachment::flushEventListeners(); }
        $this->assertSame(0,JournalEntry::count());
        $this->assertSame(0,DB::table('journal_entry_items')->count());
        $this->assertSame([],Storage::disk('local')->allFiles());
    }
}
