<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('video_production_records') || ! Schema::hasColumn('video_production_records', 'project_id')) {
            return;
        }

        $projects = DB::table('projects')->select(['id', 'name'])->get()->keyBy('id');

        foreach ($projects as $project) {
            $projectCode = substr(preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($project->name))), 0, 3) ?: 'PRO';
            $records = DB::table('video_production_records')
                ->where('project_id', $project->id)
                ->whereIn('type', ['shoot', 'deliverable'])
                ->orderBy('id')
                ->get(['id', 'type', 'record_key', 'recorded_at', 'data']);

            $usedKeys = [];
            $nextSequences = [];
            $renames = [];
            $shootKeyMap = [];

            foreach ($records as $record) {
                $label = $record->type === 'shoot' ? 'SHOOT' : 'DEL';
                preg_match('/-(\d{4})-(?:SHOOT|DEL)-(\d+)$/i', $record->record_key, $matches);
                $year = $matches[1] ?? ($record->recorded_at ? substr((string) $record->recorded_at, 0, 4) : now()->format('Y'));
                $sequence = isset($matches[2]) ? (int) $matches[2] : 0;
                $bucket = $record->type.'-'.$year;
                $nextSequences[$bucket] = max($nextSequences[$bucket] ?? 0, $sequence);

                do {
                    if ($sequence < 1 || isset($usedKeys[$record->type][$projectCode.'-'.$year.'-'.$label.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT)])) {
                        $sequence = ++$nextSequences[$bucket];
                    }
                    $newKey = $projectCode.'-'.$year.'-'.$label.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
                } while (isset($usedKeys[$record->type][$newKey]));

                $usedKeys[$record->type][$newKey] = true;
                $renames[$record->id] = $newKey;
                if ($record->type === 'shoot') {
                    $shootKeyMap[$record->record_key] = $newKey;
                }
            }

            DB::transaction(function () use ($records, $renames, $shootKeyMap) {
                foreach ($records as $record) {
                    DB::table('video_production_records')->where('id', $record->id)->update([
                        'record_key' => '__PROJECT_KEY_MIGRATION_'.$record->id,
                    ]);
                }

                foreach ($records as $record) {
                    $update = ['record_key' => $renames[$record->id]];
                    if ($record->type === 'deliverable') {
                        $data = json_decode($record->data ?: '{}', true) ?: [];
                        if (! empty($data['shoot_id']) && isset($shootKeyMap[$data['shoot_id']])) {
                            $data['shoot_id'] = $shootKeyMap[$data['shoot_id']];
                            $update['data'] = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }
                    }
                    DB::table('video_production_records')->where('id', $record->id)->update($update);
                }
            });
        }
    }

    public function down(): void
    {
        // Historical record identifiers cannot be restored reliably after new records are added.
    }
};
