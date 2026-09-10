<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app=require dirname(__DIR__, 2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\DoubleEntry\Services\ReportService;
function verify($condition,$label){if(!$condition)throw new RuntimeException($label);echo "PASS: $label\n";}
config(['database.default'=>'report_test','database.connections.report_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);DB::purge('report_test');
Schema::create('chart_of_accounts',function($t){$t->id();$t->string('account_code');$t->string('account_name');$t->string('normal_balance');$t->decimal('opening_balance',15,2);$t->integer('created_by');});
Schema::create('journal_entries',function($t){$t->id();$t->date('journal_date');$t->string('journal_number');$t->string('reference_type');$t->integer('reference_id')->nullable();$t->string('status');$t->integer('created_by');});
Schema::create('journal_entry_items',function($t){$t->id();$t->integer('account_id');$t->integer('journal_entry_id');$t->string('description');$t->decimal('debit_amount',15,2);$t->decimal('credit_amount',15,2);});
Schema::create('opening_balances',function($t){$t->id();$t->integer('account_id');$t->integer('created_by');$t->date('effective_date');$t->decimal('opening_balance',15,2);$t->string('balance_type');});
Schema::create('settings',function($t){$t->id();$t->string('key');$t->string('value');$t->integer('created_by');});
DB::table('settings')->insert([['key'=>'company_name','value'=>'Test Company','created_by'=>1],['key'=>'defaultCurrency','value'=>'QAR','created_by'=>1]]);
$user=new class extends App\Models\User {public bool $allowed=true;public function can($abilities,$arguments=[]){return $this->allowed;}};$user->id=1;$user->type='company';auth()->setUser($user);
foreach([[1,'debit',100,1],[2,'credit',200,1],[3,'debit',0,2],[4,'debit',0,1]] as [$id,$normal,$balance,$tenant])DB::table('chart_of_accounts')->insert(['id'=>$id,'account_code'=>(string)$id,'account_name'=>'Test '.$id,'normal_balance'=>$normal,'opening_balance'=>$balance,'created_by'=>$tenant]);
$insert=function($date,$debit,$credit,$status='posted',$tenant=1,$account=1){$id=DB::table('journal_entries')->insertGetId(['journal_date'=>$date,'journal_number'=>'JE-'.uniqid(),'reference_type'=>'manual','status'=>$status,'created_by'=>$tenant]);DB::table('journal_entry_items')->insert(['journal_entry_id'=>$id,'account_id'=>$account,'description'=>'Test','debit_amount'=>$debit,'credit_amount'=>$credit]);};
$insert('2026-08-31',20,0);$insert('2026-09-01',10,0);$insert('2026-09-30',0,5);$insert('2026-10-01',900,0);$insert('2026-09-02',900,0,'draft');$insert('2026-09-02',900,0,'posted',2);
for($i=0;$i<11;$i++)$insert('2026-09-15',1,0);
$filters=['account_id'=>1,'from_date'=>'2026-09-01','to_date'=>'2026-09-30'];$service=new ReportService();$data=$service->getGeneralLedger($filters);
verify((float)$data['opening_balance']===120.0,'Master opening plus prior posted activity');
verify(count($data['transactions'])===13 && (float)$data['closing_balance']===136.0,'Full date range, all pages, drafts and other company excluded');
verify($data['transactions']->first()['journal_number']!=='','Journal numbers included');
$credit=$service->getGeneralLedger(array_replace($filters,['account_id'=>2]));verify((float)$credit['closing_balance']===-200.0,'Credit-normal opening and empty period');
$empty=$service->getGeneralLedger(array_replace($filters,['account_id'=>4]));verify((float)$empty['closing_balance']===0.0 && count($empty['transactions'])===0,'Zero-activity account');
DB::table('opening_balances')->insert(['account_id'=>1,'created_by'=>1,'effective_date'=>'2026-09-01','opening_balance'=>500,'balance_type'=>'debit']);
$data=$service->getGeneralLedger($filters);verify((float)$data['opening_balance']===500.0 && (float)$data['closing_balance']===516.0,'Dated opening replaces master and prior activity');
$controller=new Workdo\Account\Http\Controllers\AccountTransactionExportController();$exporter=new Workdo\DoubleEntry\Services\AccountingReportExcelExportService();
$request=Illuminate\Http\Request::create('/','GET',$filters+['format'=>'excel']);
$response=$controller($request,ChartOfAccount::find(1),$service,$exporter);$path=$response->getFile()->getPathname();
$sheet=PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();$rows=$sheet->toArray();
verify(in_array('Test Company',array_column($rows,1),true),'Company metadata in Excel');
verify($rows[count($rows)-1][7]==='Dr' && (float)$rows[count($rows)-1][6]===516.0,'Excel closing and Dr/Cr match ledger');unlink($path);
foreach([[3,true,404],[1,false,403]] as [$id,$allow,$status]){$user->allowed=$allow;try{$controller($request,ChartOfAccount::find($id),$service,$exporter);throw new RuntimeException('Access allowed');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){verify($e->getStatusCode()===$status,'Access denied '.$status);}}
$user->allowed=true;
try{$controller(Illuminate\Http\Request::create('/','GET',['from_date'=>'2026-09-30','to_date'=>'2026-09-01','format'=>'excel']),ChartOfAccount::find(1),$service,$exporter);throw new RuntimeException('Bad dates accepted');}catch(Illuminate\Validation\ValidationException $e){verify(true,'Reverse date range rejected');}
