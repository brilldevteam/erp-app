Pemission command : sudo chmod -R 777 storage/ bootstrap/cache/ public/ packages/workdo/ resources/lang/ .env

        ********  Permission add, then First build created , then create PR  ********																			
	                   Create PR with testing	

*** Any page list and grid view show important data in both views, but you can add some more data in the grid for card UI management ***

*** Titles *** 

   Ex. User    crud	

    Header : Users																							
	Page title : Manage Users																								
	Create : Create User																								
	Edit : Edit User																								
	View : User Detail	    																							
																									
*** Message format ***
																									
	The user has been created successfully.																								
	The user details are updated successfully.																								
	The user has been deleted.		
    The purchase invoice has been posted successfully.																						
																									
*** Tooltip format ***
																									
	Create																								
	Edit																								
	View																								
	Delete																								
																									
*** event ***
																									
	CreateTestItem																								
	UpdateTestItem																								
	DestroyTestItem		


*** event code like this *** 
    
    class CreateCustomer
    {
        use Dispatchable;

        public function __construct(
            public Request $request,
            public Customer $customer
        ) {}
    }																						
																									
																									
https://tinyurl.com/244sactw -- Create a seeder for the demo data and add the keyword "demo" before the seeder name and call it inside an if condition and pass $userId																									
																									
https://prnt.sc/JqH3xj-9YM65  -- Create form in button (Cancel & Create)																									
																									
https://prnt.sc/yvJGJPFRa-K0  -- Edit form in button ( Cancel & Update)																									
																									
https://prnt.sc/d-WtRv0kz8Mq -- textarea in rows={3}																									
																									
https://prnt.sc/kCVg1byDLt6g  -- status field background  class = rounded-full																									
																									
https://prnt.sc/h_C8gOxHtD_F  --  The last created record should be shown first.																									
																									
https://prnt.sc/Kz6O-9iPzdT5 -- The last button in Action Common is delete, the next one is edit, and the next one is view.																									
																									
https://prnt.sc/JriE9EZgGVUs -- Image field like this, Use MediaPicker																									
																									
https://prnt.sc/GJzMjpuqMB1u -- summernote like this, use RichTextEditor																									
																									
https://prnt.sc/lBGEeOc4H8cQ -- Multi select like this, use MultiSelectEnhanced																									
																									
https://tinyurl.com/27yf2llk -- Mobile number field like this,  use PhoneInputComponent																									
																									
https://prnt.sc/cDZSO5G1iOJO -- The header section in all menus is clickable																									
																									
https://prnt.sc/Q66famp2YKcD -- Where a button appears in the header, only the icon should appear, no text should be written. also add Tooltip in all button																									
																									
https://prnt.sc/03SBMU9agbWI  -- defult image																									

																									
Controller-> index function in this type condition  																									
																									
    ->where(function($q) {																									
    if(Auth::user()->can('manage-any-users')) {																									
            $q->where('created_by', creatorId());																									
        } elseif(Auth::user()->can('manage-own-users')) {																									
            $q->where('creator_id', Auth::id());																									
        } else {																									
            $q->whereRaw('1 = 0');																									
        }																									
    })																									
																									
																									
https://tinyurl.com/259jlnbv -- Create this type of event in which the first parameter is request and the second parameter is that variable.																									
																									
	ex. CreateTestItem::dispatch($request, $item);																								
																									
-- filter should work as permission (like role)																									
																									
-- Proper permission handling on controller side and react side.																									

-- If another user log in, add default permissions, also call provider in ( EventServiceProvider )
    																								
-- Use helper functions in price, date, time fields.																									
																									
-- All titles, messages, and labels should be language-based.																									
																									
-- If no permission for the action  buttons, then don't show the action column 							

-- Add a condition with hasTable in Migration Migration
																								
	ex. if (!Schema::hasTable('test_items'))																								

-- Refer to the warehouse, test and demo items modules, all components are used in them.
																									
-- module.json in version 5.0  																									
																									
-- Do not use protected $table  in model 																									

-- if user login is add so please check own condition set.

-- duplicate route check and remove extra route
																									
--  https://www.awesomescreenshot.com/image/56967349?key=fcc00683e3b7b56a8c3f64dfa91bb4ec  If you have created this type of function in the model, please remove it.																									
																									
--- add foreign key in table																									
																									
	$table->foreignId('creator_id')->nullable()->index();																								
	$table->foreignId('created_by')->nullable()->index();																								
																									
	$table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');																								
	$table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');	

*** bank account id field push***  


<!-- database -->
$table->foreignId('bank_account_id')->nullable();
$table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');


<!-- create -->

import { useFormFields } from '@/hooks/useFormFields';


// Bank Account Field
const bankAccountField = useFormFields('bankAccountField', data, setData, errors);

{bankAccountField.map((field) => (
    <div key={field.id}>{field.component}</div>
))}

<!-- update -->
import { useFormFields } from '@/hooks/useFormFields';


const { data, setData, put, processing, errors } = useForm<EditWarehouseFormData>(warehouse);

// Bank Account Field
const bankAccountField = useFormFields('bankAccountField', data, setData, errors, 'edit');


{bankAccountField.map((field) => (
    <div key={field.id}>{field.component}</div>
))}


<!-- Account add-on code -->

UpdateWarehouse::class => [
    BankAccountFieldUpdate::class,
],
