<?php

namespace Workdo\VideoProduction\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Workdo\VideoProduction\Models\ProductionRecord;
use Workdo\VideoProduction\Models\ProductionSetting;
use Workdo\Taskly\Models\Project;

class ProductionRecordController extends Controller
{
    private const TYPES = ['shoot', 'deliverable'];

    public function store(Request $request, Project $project, string $type)
    {
        $this->authorizeProject($request, $project);
        abort_unless(in_array($type, self::TYPES, true), 404);
        $values = $this->validated($request, $project, $type);
        $values['data'] = $this->storeSupportingFiles($values['data']);
        $values['data'] = $this->syncLinkedShoot($project, $type, $values['data']);
        $values['data'] = $this->calculateFields($project, $type, $values['data']);

        ProductionRecord::create([
            'project_id' => $project->id,
            'type' => $type,
            'record_key' => $values['record_key'],
            'recorded_at' => $values['recorded_at'] ?: null,
            'status' => $values['status'] ?: null,
            'data' => $values['data'],
            'creator_id' => $request->user()->id,
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Production record created successfully.'));
    }

    public function update(Request $request, Project $project, string $type, ProductionRecord $record)
    {
        $this->authorizeProject($request, $project);
        abort_unless($record->created_by === creatorId() && $record->project_id === $project->id && $record->type === $type, 404);
        $values = $this->validated($request, $project, $type, $record->id);
        $values['data'] = $this->storeSupportingFiles($values['data']);
        $values['data'] = $this->syncLinkedShoot($project, $type, $values['data']);
        $values['data'] = $this->calculateFields($project, $type, $values['data']);
        $record->update(Arr::only($values, ['record_key', 'recorded_at', 'status', 'data']));

        return back()->with('success', __('Production record updated successfully.'));
    }

    public function destroy(Request $request, Project $project, string $type, ProductionRecord $record)
    {
        $this->authorizeProject($request, $project);
        abort_unless($record->created_by === creatorId() && $record->project_id === $project->id && $record->type === $type, 404);
        $record->delete();

        return back()->with('success', __('Production record deleted successfully.'));
    }

    private function validated(Request $request, Project $project, string $type, ?int $ignoreId = null): array
    {
        $companyId = creatorId();

        $rules = [
            'record_key' => ['required', 'string', 'max:100', Rule::unique('video_production_records')->where(fn ($query) => $query->where('created_by', $companyId)->where('project_id', $project->id)->where('type', $type))->ignore($ignoreId)],
            'recorded_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:60'],
            'data' => ['required', 'array'],
            'data.*' => ['nullable'],
            'data.supporting_files' => ['nullable', 'array', 'max:10'],
            'data.supporting_files.*.path' => ['required', 'string', 'max:500'],
            'data.supporting_files.*.name' => ['required', 'string', 'max:255'],
            'data.supporting_files.*.type' => ['nullable', 'string', 'max:100'],
            'data.supporting_files.*.size' => ['nullable', 'integer', 'min:0'],
            'data.new_supporting_files' => ['nullable', 'array', 'max:10'],
            'data.new_supporting_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:10240'],
            'data.evidence_links' => ['nullable', 'array', 'max:10'],
            'data.evidence_links.*' => ['nullable', 'url', 'max:2048'],
        ];

        foreach ([1, 2, 3] as $revision) {
            $rules["data.revision_{$revision}_files"] = ['nullable', 'array', 'max:10'];
            $rules["data.revision_{$revision}_files.*.path"] = ['required', 'string', 'max:500'];
            $rules["data.revision_{$revision}_files.*.name"] = ['required', 'string', 'max:255'];
            $rules["data.revision_{$revision}_files.*.type"] = ['nullable', 'string', 'max:100'];
            $rules["data.revision_{$revision}_files.*.size"] = ['nullable', 'integer', 'min:0'];
            $rules["data.new_revision_{$revision}_files"] = ['nullable', 'array', 'max:10'];
            $rules["data.new_revision_{$revision}_files.*"] = ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:10240'];
            $rules["data.revision_{$revision}_evidence_links"] = ['nullable', 'array', 'max:10'];
            $rules["data.revision_{$revision}_evidence_links.*"] = ['nullable', 'url', 'max:2048'];
        }

        return $request->validate($rules);
    }

    private function storeSupportingFiles(array $data): array
    {
        $fileGroups = ['supporting_files', 'revision_1_files', 'revision_2_files', 'revision_3_files'];
        foreach ($fileGroups as $key) {
            $newKey = 'new_'.$key;
            $newFiles = collect($data[$newKey] ?? [])->filter(fn ($file) => $file instanceof UploadedFile);
            unset($data[$newKey]);

            $storedFiles = collect($data[$key] ?? [])->values();
            if ($storedFiles->count() + $newFiles->count() > 10) {
                throw ValidationException::withMessages([
                    'data.'.$newKey => __('A maximum of 10 files is allowed for each evidence section.'),
                ]);
            }

            $newFiles->each(function (UploadedFile $file) use ($storedFiles, $key) {
                $storedFiles->push([
                    'path' => $file->store('video-production/'.creatorId().'/'.$key, 'public'),
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            });
            $data[$key] = $storedFiles->all();
        }

        foreach (['evidence_links', 'revision_1_evidence_links', 'revision_2_evidence_links', 'revision_3_evidence_links'] as $key) {
            $data[$key] = collect($data[$key] ?? [])->filter()->values()->all();
        }

        return $data;
    }

    private function calculateFields(Project $project, string $type, array $data): array
    {
        $settings = ProductionSetting::forProject(creatorId(), $project->id);

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
            $data['total_revisions'] = collect([1, 2, 3])->filter(fn ($number) => ! empty($data["revision_{$number}_requested_at"]))->count()
                + max(0, (int) ($data['additional_revision_count'] ?? 0));
            $data['revision_4_plus'] = $data['total_revisions'] > $settings->included_revisions ? 'Yes' : 'No';
            $data['total_brill_days'] = $this->sumWorkingDayPeriods([
                [$data['complete_inputs_received_at'] ?? null, $data['v1_delivered_at'] ?? null],
                [$data['revision_1_requested_at'] ?? null, $data['revision_1_delivered_at'] ?? null],
                [$data['revision_2_requested_at'] ?? null, $data['revision_2_delivered_at'] ?? null],
                [$data['revision_3_requested_at'] ?? null, $data['revision_3_delivered_at'] ?? null],
            ], $settings->working_days);
            $data['total_client_wait_days'] = $this->sumWorkingDayPeriods([
                [$data['v1_delivered_at'] ?? null, $data['client_v1_response_at'] ?? null],
                [$data['revision_1_delivered_at'] ?? null, $data['revision_2_requested_at'] ?? null],
                [$data['revision_2_delivered_at'] ?? null, $data['revision_3_requested_at'] ?? null],
                [$data['revision_3_delivered_at'] ?? null, $data['final_approval_date'] ?? null],
            ], $settings->working_days);
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

    private function syncLinkedShoot(Project $project, string $type, array $data): array
    {
        if ($type !== 'deliverable' || empty($data['shoot_id'])) {
            return $data;
        }

        $shoot = ProductionRecord::query()
            ->forCompany()
            ->where('project_id', $project->id)
            ->where('type', 'shoot')
            ->where('record_key', $data['shoot_id'])
            ->first();

        if (! $shoot) {
            throw ValidationException::withMessages([
                'data.shoot_id' => __('Select a valid Shooting Log record.'),
            ]);
        }

        $shootData = $shoot->data ?? [];
        $defaults = [
            'doctor_department' => $shootData['doctor_subject'] ?? null,
            'shoot_date' => $shootData['shoot_date'] ?? null,
            'script_received_at' => $shootData['script_received_at'] ?? null,
            'b_roll_defined' => $shootData['b_roll_requirements'] ?? null,
            'editing_reference' => $shootData['editing_references'] ?? null,
            'evidence_folder_link' => collect($shootData['evidence_links'] ?? [])->filter()->first()
                ?: ($shootData['evidence_folder_link'] ?? null),
        ];

        foreach ($defaults as $key => $value) {
            if (($data[$key] ?? null) === null || $data[$key] === '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(
            $request->user()->can('manage-video-production') && $project->created_by === creatorId(),
            403
        );
    }

    private function sumWorkingDayPeriods(array $periods, ?array $workingDays): int
    {
        return collect($periods)->sum(function (array $period) use ($workingDays) {
            [$start, $end] = $period;

            return $start && $end ? max(0, $this->workingDaysBetween($start, $end, $workingDays) ?? 0) : 0;
        });
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
