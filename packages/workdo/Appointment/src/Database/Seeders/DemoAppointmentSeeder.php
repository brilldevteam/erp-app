<?php

namespace Workdo\Appointment\Database\Seeders;

use Workdo\Appointment\Models\Appointment;
use Illuminate\Database\Seeder;
use Workdo\Appointment\Models\Question;

class DemoAppointmentSeeder extends Seeder
{
    public function run($userId): void
    {
        if (!empty($userId))
        {
            if (Appointment::where('created_by', $userId)->exists()) {
                return;
            }

            $questionIds = Question::where('created_by', $userId)
                ->where('required_answer', true)
                ->pluck('id')
                ->toArray();

            if (empty($questionIds)) {
                return;
            }

            $appointments = [
                // Paid Services (0)
                ['name' => 'Premium Business Strategy Consultation', 'type' => '0', 'days' => ['monday', 'wednesday', 'friday'], 'duration' => 90, 'phone' => false],
                ['name' => 'Executive Leadership Coaching', 'type' => '0', 'days' => ['tuesday', 'thursday'], 'duration' => 60, 'phone' => true],
                ['name' => 'Technical Architecture Review', 'type' => '0', 'days' => ['monday', 'tuesday', 'wednesday'], 'duration' => 120, 'phone' => false],
                ['name' => 'Market Research & Analysis', 'type' => '0', 'days' => ['wednesday', 'thursday','saturday'], 'duration' => 75, 'phone' => false],
                ['name' => 'Financial Planning Session', 'type' => '0', 'days' => ['tuesday', 'friday'], 'duration' => 90, 'phone' => true],
                ['name' => 'Digital Transformation Workshop', 'type' => '0', 'days' => ['thursday'], 'duration' => 180, 'phone' => false],
                ['name' => 'Risk Assessment & Mitigation', 'type' => '0', 'days' => ['monday', 'wednesday'], 'duration' => 105, 'phone' => false],
                ['name' => 'Performance Optimization Audit', 'type' => '0', 'days' => ['tuesday', 'thursday','saturday'], 'duration' => 120, 'phone' => false],
                ['name' => 'Compliance & Regulatory Review', 'type' => '0', 'days' => ['wednesday', 'friday'], 'duration' => 90, 'phone' => false],
                ['name' => 'Investment Advisory Session', 'type' => '0', 'days' => ['monday', 'friday'], 'duration' => 75, 'phone' => true],
                ['name' => 'Crisis Management Planning', 'type' => '0', 'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday','saturday'], 'duration' => 60, 'phone' => true],
                ['name' => 'Vendor Evaluation & Selection', 'type' => '0', 'days' => ['tuesday', 'wednesday'], 'duration' => 90, 'phone' => false],
                ['name' => 'Team Building & Development', 'type' => '0', 'days' => ['wednesday', 'thursday'], 'duration' => 150, 'phone' => false],
                ['name' => 'Security Assessment & Planning', 'type' => '0', 'days' => ['monday', 'thursday','saturday'], 'duration' => 120, 'phone' => false],
                ['name' => 'Process Optimization Consultation', 'type' => '0', 'days' => ['tuesday', 'friday'], 'duration' => 105, 'phone' => false],

                // Free Services (1)
                ['name' => 'Initial Business Assessment', 'type' => '1', 'days' => ['monday', 'wednesday', 'friday'], 'duration' => 30, 'phone' => true],
                ['name' => 'Product Demo & Overview', 'type' => '1', 'days' => ['tuesday', 'thursday'], 'duration' => 45, 'phone' => false],
                ['name' => 'Discovery Call & Needs Analysis', 'type' => '1', 'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 'duration' => 30, 'phone' => true],
                ['name' => 'Free Consultation Call', 'type' => '1', 'days' => ['monday', 'wednesday','saturday'], 'duration' => 20, 'phone' => true],
                ['name' => 'Solution Overview Session', 'type' => '1', 'days' => ['tuesday', 'thursday'], 'duration' => 45, 'phone' => false],
                ['name' => 'Partnership Discussion', 'type' => '1', 'days' => ['wednesday', 'friday'], 'duration' => 60, 'phone' => true],
                ['name' => 'Quick Support Session', 'type' => '1', 'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday','saturday'], 'duration' => 15, 'phone' => true],
                ['name' => 'Onboarding Orientation', 'type' => '1', 'days' => ['tuesday', 'wednesday', 'thursday'], 'duration' => 60, 'phone' => false],
                ['name' => 'Follow-up Check-in', 'type' => '1', 'days' => ['thursday', 'friday'], 'duration' => 30, 'phone' => true],
                ['name' => 'Information Session', 'type' => '1', 'days' => ['wednesday'], 'duration' => 45, 'phone' => false],
                ['name' => 'Q&A Session', 'type' => '1', 'days' => ['monday', 'friday','saturday'], 'duration' => 30, 'phone' => true],
                ['name' => 'Introduction Meeting', 'type' => '1', 'days' => ['tuesday', 'wednesday'], 'duration' => 45, 'phone' => false],
                ['name' => 'Feedback & Review Session', 'type' => '1', 'days' => ['thursday'], 'duration' => 30, 'phone' => true],
                ['name' => 'Community Webinar', 'type' => '1', 'days' => ['friday','saturday'], 'duration' => 60, 'phone' => false],
                ['name' => 'Open House Session', 'type' => '1', 'days' => ['monday', 'wednesday'], 'duration' => 90, 'phone' => false]
            ];
            shuffle($appointments);
            foreach ($appointments as $apt) {

                Appointment::create([
                    'appointment_name' => $apt['name'],
                    'appointment_type' => $apt['type'],
                    'week_day' => json_encode($apt['days']),
                    'duration' => $apt['duration'],
                    'phone_enabled' => $apt['phone'],
                    'question_ids' => json_encode($questionIds),
                    'enabled' => true,
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ]);
            }
        }
    }
}
