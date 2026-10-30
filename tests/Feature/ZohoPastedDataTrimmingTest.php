<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Tests\TestCase;

class ZohoPastedDataTrimmingTest extends TestCase
{
    public function test_pasted_spreadsheet_data_keeps_a_trailing_empty_cell(): void
    {
        // Pasted rows end in a tab when their last cell is empty; trimming it would drop a column.
        $request = Request::create('/zoho-migrations/1/stages/categories_units/manual-prerequisite', 'POST', [
            'pasted_data' => "record_type\tname\tcolor\nunit\thours\t",
            'name' => '  trimmed  ',
        ]);

        app(TrimStrings::class)->handle($request, function (Request $request) {
            $this->assertSame("record_type\tname\tcolor\nunit\thours\t", $request->input('pasted_data'));
            $this->assertSame('trimmed', $request->input('name'));

            return response('ok');
        });
    }
}
