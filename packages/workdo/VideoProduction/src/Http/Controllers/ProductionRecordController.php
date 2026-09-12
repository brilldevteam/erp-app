<?php

namespace Workdo\VideoProduction\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Workdo\VideoProduction\Models\ProductionRecord;
use Workdo\VideoProduction\Models\ProductionSetting;

class ProductionRecordController extends Controller
{
    private const TYPES = ['shoot', 'deliverable', 'revision', 'time', 'evidence'];

    public function store(Request $request, string $type)
    {
        abort_unless($request->user()->can('manage-video-production') && in_array($type, self::TYPES, true), 403);
        $values = $this->validated($request, $type);
        $values['data'] = $this->storeProofImage($values['data'], $values['production_job_id']);
        $values['data'] = $this->calculateFields($type, $values['data']);

        ProductionRecord::create([
            'type' => $type,
            'production_job_id' => $values['production_job_id'],
            'record_key' => $values['record_key'],
            'recorded_at' => $values['recorded_at'] ?: null,
            'status' => $values['status'] ?: null,
            'data' => $values['data'],
            'creator_id' => $request->user()->id,
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Production record created successfully.'));
    }

    public function update(Request $request, string $type, ProductionRecord $record)
    {
        abort_unless($request->user()->can('manage-video-production') && $record->created_by === creatorId() && $record->type === $type, 403);
        $values = $this->validated($request, $type, $record->id);
        $values['data'] = $this->storeProofImage($values['data'], $values['production_job_id'], $record);
        $values['data'] = $this->calculateFields($type, $values['data']);
        $record->update(Arr::only($values, ['production_job_id', 'record_key', 'recorded_at', 'status', 'data']));

        return back()->with('success', __('Production record updated successfully.'));
    }

    public function destroy(Request $request, string $type, ProductionRecord $record)
    {
        abort_unless($request->user()->can('manage-video-production') && $record->created_by === creatorId() && $record->type === $type, 403);
        $record->delete();

        return back()->with('success', __('Production record deleted successfully.'));
    }

    private function validated(Request $request, string $type, ?int $ignoreId = null): array
    {
        $companyId = creatorId();

        return $request->validate([
            'production_job_id' => ['required', 'integer', Rule::exists('video_production_jobs', 'id')->where('created_by', $companyId)],
            'record_key' => ['required', 'string', 'max:100', Rule::unique('video_production_records')->where(fn ($query) => $query->where('created_by', $companyId)->where('production_job_id', $request->integer('production_job_id'))->where('type', $type))->ignore($ignoreId)],
            'recorded_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:60'],
            'data' => ['required', 'array'],
            'data.*' => ['nullable'],
            'data.proof_image' => [
                'nullable',
                Rule::when($request->hasFile('data.proof_image'), ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], ['string', 'max:500']),
            ],
        ]);
    }

    private function storeProofImage(array $data, int $jobId, ?ProductionRecord $record = null): array
    {
        $proofImage = $data['proof_image'] ?? null;
        unset($data['proof_image']);

        if (! $proofImage instanceof UploadedFile) {
            return $data;
        }

        $oldPath = data_get($record?->data, 'proof_image_path');
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $data['proof_image_path'] = $proofImage->store(
            'video-production/'.creatorId().'/'.$jobId.'/shoot-proofs',
            'public'
        );

        return $data;
    }

    private function calculateFields(string $type, array $data): array
    {
        $settings = ProductionSetting::forCompany(creatorId());

        if ($type === 'shoot') {
            $data['contract_hours'] = (float) $settings->included_hours_per_shoot;
            $data['total_hours'] = $this->hoursBetween($data['actual_start'] ?? null, $data['actual_end'] ?? null);
            $data['extra_hours'] = $data['total_hours'] === null ? null : max(0, $data['total_hours'] - $data['contract_hours']);
            $data['working_days_before'] = $this->workingDaysBetween($data['script_received_at'] ?? null, $data['shoot_date'] ?? null, $settings->working_days);
            $data['lead_requirement'] = in_array(($data['shoot_type'] ?? null), ['Unplanned', 'Urgent'], true)
                ? 'N/A - Unplanned'
                : (! ($data['script_received_at'] ?? null) ? 'No - Not Received' : (($data['working_days_before'] ?? -1) >= $settings->required_lead_days ? 'Yes' : 'No'));
        }

        if ($type === 'deliverable') {
            $data['working_days_before'] = $this->workingDaysBetween($data['script_received_at'] ?? null, $data['shoot_date'] ?? null, $settings->working_days);
            $data['script_lead_category'] = $this->scriptLeadCategory($data);
            $data['v1_turnaround_days'] = $this->workingDaysBetween($data['complete_inputs_received_at'] ?? null, $data['v1_delivered_at'] ?? null, $settings->working_days);
            $data['client_review_days'] = $this->workingDaysBetween($data['v1_delivered_at'] ?? null, $data['client_v1_response_at'] ?? null, $settings->working_days);
            $data['total_revisions'] = collect([1, 2, 3])->filter(fn ($number) => ! empty($data["revision_{$number}_requested_at"]))->count();
            $data['revision_4_plus'] = $data['total_revisions'] > $settings->included_revisions ? 'Yes' : 'No';
            $data['total_brill_days'] = $data['v1_turnaround_days'];
            $data['total_client_wait_days'] = $data['client_review_days'];
        }

        if ($type === 'revision') {
            $data['included_status'] = (int) ($data['revision_no'] ?? 0) <= $settings->included_revisions ? 'Yes' : 'No - Revision 4+';
            $data['brill_turnaround_days'] = $this->workingDaysBetween($data['brill_started_at'] ?? null, $data['delivered_at'] ?? null, $settings->working_days);
            $data['client_review_days'] = $this->workingDaysBetween($data['delivered_at'] ?? null, $data['client_next_response_at'] ?? null, $settings->working_days);
        }

        if ($type === 'time') {
            $data['total_hours'] = $this->hoursBetween($data['start_time'] ?? null, $data['end_time'] ?? null);
        }

        return $data;
    }

    private function hoursBetween(?string $start, ?string $end): ?float
    {
        if (! $start || ! $end) {
            return null;
        }
        $minutes = Carbon::parse($start)->diffInMinutes(Carbon::parse($end), false);
        if ($minutes < 0) {
            $minutes += 1440;
        }

        return round($minutes / 60, 2);
    }

    private function workingDaysBetween(?string $start, ?string $end, ?array $workingDays): ?int
    {
        if (! $start || ! $end) {
            return null;
        }
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        if ($to->lt($from)) {
            return -$this->workingDaysBetween($end, $start, $workingDays);
        }
        $allowed = array_map('strtolower', $workingDays ?: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
        $days = 0;
        for ($date = $from->copy()->addDay(); $date->lte($to); $date->addDay()) {
            if (in_array(strtolower($date->englishDayOfWeek), $allowed, true)) {
                $days++;
            }
        }

        return $days;
    }

    private function scriptLeadCategory(array $data): string
    {
        if (($data['content_type'] ?? null) === 'Static') {
            return 'N/A - Static';
        }
        if (empty($data['shoot_date'])) {
            return 'No shoot linked';
        }
        if (empty($data['script_received_at'])) {
            return 'Not received before shooting';
        }
        $days = $data['working_days_before'];
        if ($days < 0) {
            return 'After shooting';
        }
        if ($days === 0) {
            return 'Shooting day';
        }

        return $days > 5 ? 'More than 5 days' : $days.' days before';
    }
}
