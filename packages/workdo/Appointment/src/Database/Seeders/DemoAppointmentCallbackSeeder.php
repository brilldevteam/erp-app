<?php

namespace Workdo\Appointment\Database\Seeders;

use Workdo\Appointment\Models\AppointmentCallback;
use Workdo\Appointment\Models\Schedule;
use Workdo\Appointment\Models\Appointment;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DemoAppointmentCallbackSeeder extends Seeder
{
    public function run($userId): void
    {
        if (AppointmentCallback::where('created_by', $userId)->exists()) {
            return;
        }

        if (!empty($userId))
        {
            // Get only completed schedules for callbacks
            $completedSchedules = Schedule::where('created_by', $userId)->where('status', 'complete')->orderBy('date', 'asc')->get();

            if ($completedSchedules->isEmpty()) {
                return;
            }

            // Callback reasons for different scenarios
            $callbackReasons = [
                'Need follow-up appointment to discuss additional project requirements and clarify technical specifications with stakeholders for better understanding and alignment.',
                'Client requested second meeting to review detailed proposal presentation and address specific concerns raised by management team during evaluation.',
                'Additional consultation session needed to demonstrate advanced product features and answer technical questions from development team members.',
                'Follow-up appointment required to finalize contract terms, discuss implementation timeline, and coordinate with decision makers for project approval.',
                'Client wants extended consultation to explore customization options, integration possibilities, and discuss specific business requirements for their organization.',
                'Second meeting needed to present revised proposal based on feedback received during initial consultation and address stakeholder concerns.',
                'Additional time required to discuss detailed budget allocation, payment terms, ongoing support requirements, and service level agreements.',
                'Follow-up appointment to address compliance concerns, security requirements, and regulatory standards raised by legal and IT teams.',
                'Client requested demonstration session with technical team to evaluate system compatibility, features, and integration capabilities with existing infrastructure.',
                'Need another meeting to discuss comprehensive training requirements, user onboarding process, and change management strategies for implementation.',
                'Follow-up consultation to review competitive analysis, market positioning, and demonstrate our solution advantages against alternative options.',
                'Additional session needed to involve procurement team, discuss vendor requirements, documentation needs, and compliance with organizational policies.',
                'Client wants detailed discussion about ongoing maintenance services, support levels, response times, and comprehensive service level agreements.',
                'Second appointment required to address integration challenges, provide technical solutions, and discuss implementation roadmap with engineering team.',
                'Follow-up meeting to discuss project phases, milestone deliverables, resource allocation, and timeline coordination for successful deployment and launch.',
                'Additional consultation needed to review specific use cases, workflow optimization opportunities, and business process improvements for efficiency.',
                'Client requested extended session to discuss scalability options, future expansion possibilities, and long-term growth strategy alignment.',
                'Follow-up appointment to address detailed questions from finance team regarding return on investment calculations and comprehensive cost-benefit analysis.',
                'Need second meeting to discuss data migration strategy, system backup procedures, disaster recovery planning, and business continuity measures.',
                'Additional session required to finalize user roles, permission structures, access control configurations, and security protocols for system implementation.',
                'Client wants follow-up to discuss reporting capabilities, analytics features, dashboard customization options, and business intelligence integration possibilities.',
                'Second appointment needed to address performance requirements, system capacity planning, optimization strategies, and infrastructure scaling considerations for deployment.',
                'Follow-up consultation to discuss mobile access capabilities, remote work functionality, cross-platform compatibility, and user experience optimization.',
                'Additional meeting required to review comprehensive documentation, user manuals, training materials, and knowledge transfer processes for team.',
            ];

            // Date ranges for callbacks (oldest to newest)
            $baseDates = [
                // Past 6 months to current (18 records) - oldest first
                Carbon::now()->subMonths(6)->setDay(8), Carbon::now()->subMonths(6)->setDay(15), Carbon::now()->subMonths(5)->setDay(12),
                Carbon::now()->subMonths(5)->setDay(20), Carbon::now()->subMonths(4)->setDay(5), Carbon::now()->subMonths(4)->setDay(18),
                Carbon::now()->subMonths(3)->setDay(10), Carbon::now()->subMonths(3)->setDay(22), Carbon::now()->subMonths(2)->setDay(8),
                Carbon::now()->subMonths(2)->setDay(18), Carbon::now()->subMonths(1)->setDay(6), Carbon::now()->subMonths(1)->setDay(20),
                Carbon::now()->subDays(28), Carbon::now()->subDays(18), Carbon::now()->subDays(10), Carbon::now()->subDays(6),
                Carbon::now()->subDays(3), Carbon::now()->subDays(1),
                // Future 2 months (10 records)
                Carbon::now()->addDays(2), Carbon::now()->addDays(8), Carbon::now()->addDays(15), Carbon::now()->addDays(22),
                Carbon::now()->addMonth()->setDay(5), Carbon::now()->addMonth()->setDay(12), Carbon::now()->addMonth()->setDay(20),
                Carbon::now()->addMonths(2)->setDay(8), Carbon::now()->addMonths(2)->setDay(18), Carbon::now()->addMonths(2)->setDay(25)
            ];

            $callbackData = [];
            $usedScheduleIds = [];

            // Skip 3 completed schedules (they won't have callbacks)
            $skipSchedules = $completedSchedules->random(min(3, $completedSchedules->count()))->pluck('id')->toArray();

            // Create callbacks for remaining completed schedules
            $availableSchedules = $completedSchedules->whereNotIn('id', $skipSchedules);

            foreach ($baseDates as $index => $baseDate) {
                if ($availableSchedules->isEmpty()) break;

                // Select schedule (allow 2-3 callbacks for same schedule)
                $reuseChance = 30; // 30% chance
                $randPercent = rand(1, 100); // Random number between 1 and 100

                if (!empty($usedScheduleIds) && $randPercent <= $reuseChance) {
                    // 30% chance to reuse existing schedule
                    $filtered = $availableSchedules->whereIn('id', $usedScheduleIds)->values()->all(); // convert to array
                    $schedule = $filtered[array_rand($filtered)];
                } else {
                    // Select new schedule
                    $allSchedules = $availableSchedules->values()->all();
                    $schedule = $allSchedules[array_rand($allSchedules)];
                    $usedScheduleIds[] = $schedule->id;
                }

                // Calculate callback date (should be after original appointment)
                $originalDate = Carbon::parse($schedule->date);
                $callbackDate = $baseDate->copy();

                // Ensure callback is after original appointment for past dates
                if ($callbackDate->lt($originalDate)) {
                    $daysToAdd = rand(1, 7); // Random number between 1 and 7
                    $callbackDate = $originalDate->copy()->addDays($daysToAdd);
                }

                // Generate time slots (usually different from original appointment)
                $originalStartTime = Carbon::parse($schedule->start_time);
                // Random number of hours between -2 and 4
                $hoursToAdd = rand(-2, 4);
                $callbackStartTime = $originalStartTime->copy()->addHours($hoursToAdd);


                // Ensure time is within business hours
                if ($callbackStartTime->hour < 9) {
                    $callbackStartTime->setTime(9, 0);
                } elseif ($callbackStartTime->hour > 17) {
                    $callbackStartTime->setTime(17, 0);
                }

                // Generate end time by adding 30 to 90 minutes
                $minutesToAdd = rand(30, 90);
                $callbackEndTime = $callbackStartTime->copy()->addMinutes($minutesToAdd);

                // Determine status based on date
                if ($callbackDate->lt(Carbon::now())) {
                    // For past dates, pick randomly from array (weighted)
                    $statuses = ['complete', 'complete', 'approved', 'reject'];
                    $status = $statuses[array_rand($statuses)];
                } elseif ($callbackDate->lte(Carbon::now()->addDays(7))) {
                    // For upcoming week, choose between approved or pending
                    $statuses = ['approved', 'pending'];
                    $status = $statuses[array_rand($statuses)];
                } else {
                    $status = 'pending';
                }

                $callbackData[] = [
                    'schedule_id' => $schedule->id,
                    'unique_code' => $schedule->unique_id,
                    'user_id' => $schedule->user_id,
                    'appointment_id' => $schedule->appointment_id,
                    'reason' => $callbackReasons[array_rand($callbackReasons)],
                    'date' => $callbackDate->format('Y-m-d'),
                    'start_time' => $callbackStartTime->format('H:i:s'),
                    'end_time' => $callbackEndTime->format('H:i:s'),
                    'status' => $status,
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ];
            }

            // Insert data in chronological order (oldest first)
            foreach ($callbackData as $data) {
                AppointmentCallback::create($data);
            }
        }
    }
}
