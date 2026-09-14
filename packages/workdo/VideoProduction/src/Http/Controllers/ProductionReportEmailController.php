<?php

namespace Workdo\VideoProduction\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Workdo\Taskly\Models\Project;
use Workdo\VideoProduction\Http\Requests\SendProductionReportEmailRequest;
use Workdo\VideoProduction\Models\ProductionRecord;
use Workdo\VideoProduction\Models\ProductionSetting;

class ProductionReportEmailController extends Controller
{
    public function send(SendProductionReportEmailRequest $request, Project $project)
    {
        abort_unless($project->created_by === creatorId(), 404);

        $validated = $request->validated();
        $settings = ProductionSetting::forProject(creatorId(), $project->id);
        abort_unless($settings->report_email_enabled, 403, __('Report email delivery is disabled for this project.'));

        $record = ProductionRecord::query()
            ->where('id', $validated['record_id'])
            ->where('created_by', creatorId())
            ->where('project_id', $project->id)
            ->where('type', $validated['type'])
            ->firstOrFail();

        $companySettings = getCompanyAllSetting();
        if (empty($companySettings['email_fromAddress'])) {
            return response()->json([
                'message' => __('Email is not configured for this company. Configure the SMTP sender in system settings first.'),
            ], 422);
        }
        if (($companySettings['email_driver'] ?? 'smtp') === 'smtp' && empty($companySettings['email_host'])) {
            return response()->json([
                'message' => __('The company SMTP host is not configured. Complete the company email settings and try again.'),
            ], 422);
        }

        SetConfigEmail();

        $pdf = $request->file('report');
        $filename = $record->record_key.'.pdf';
        $safeMessage = nl2br(e($validated['message'] ?: __('Please find the production report attached.')));
        $html = '<div style="font-family:Arial,sans-serif;color:#172033;line-height:1.6">'
            .'<h2 style="margin-bottom:8px">'.e($project->name).'</h2>'
            .'<p style="color:#64748b;margin-top:0">'.e($record->record_key).'</p>'
            .'<div>'.$safeMessage.'</div>'
            .'<p style="margin-top:24px;color:#94a3b8;font-size:12px">'.e(__('Generated from Wazely ERP Production Management')).'</p>'
            .'</div>';

        try {
            Mail::html($html, function ($mail) use ($validated, $pdf, $filename) {
                $mail->to($validated['recipients'])
                    ->subject($validated['subject'])
                    ->attachData(file_get_contents($pdf->getRealPath()), $filename, ['mime' => 'application/pdf']);

                if (!empty($validated['cc'])) {
                    $mail->cc($validated['cc']);
                }
            });
        } catch (Throwable $exception) {
            Log::error('Production report email failed', [
                'project_id' => $project->id,
                'record_id' => $record->id,
                'sender_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $this->mailErrorMessage($exception)], 422);
        }

        Log::info('Production report email sent', [
            'project_id' => $project->id,
            'record_id' => $record->id,
            'sender_id' => Auth::id(),
            'recipients' => $validated['recipients'],
            'cc' => $validated['cc'] ?? [],
        ]);

        return response()->json(['message' => __('Report emailed successfully.')]);
    }

    private function mailErrorMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'authenticate') || str_contains($message, 'authentication')) {
            return __('SMTP authentication failed. Check the company email username, password, and app password.');
        }
        if (str_contains($message, 'connection') || str_contains($message, 'connect') || str_contains($message, 'timed out')) {
            return __('Could not connect to the SMTP server. Check the host, port, and encryption settings.');
        }

        return __('The report could not be emailed. Please check the company email settings and try again.');
    }
}
