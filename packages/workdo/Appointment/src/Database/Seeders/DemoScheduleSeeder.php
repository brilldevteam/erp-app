<?php

namespace Workdo\Appointment\Database\Seeders;

use Workdo\Appointment\Models\Schedule;
use Workdo\Appointment\Models\Appointment;
use Workdo\Appointment\Models\Question;
use Workdo\Appointment\Models\AppointmentHour;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DemoScheduleSeeder extends Seeder
{
    public function run($userId): void
    {
        if (Schedule::where('created_by', $userId)->exists()) {
            return;
        }

        if (!empty($userId))
        {
            $appointments = Appointment::where('created_by', $userId)->get();
            $questions = Question::where('created_by', $userId)->get();
            $appointmentHours = AppointmentHour::where('created_by', $userId)->get()->keyBy('day_name');

            if ($appointments->isEmpty()) {
                return;
            }

            $cancelReasons = [
                'Client requested appointment reschedule due to conflicting business priorities and scheduling conflicts with board meeting',
                'Appointment cancelled due to budget constraints and financial approval delays from management team',
                'Meeting postponed pending internal approval process and stakeholder review for project continuation',
                'Emergency situation arose requiring immediate attention from key decision makers and executive team',
                'Key stakeholder unavailable for scheduled appointment time due to unexpected travel and business commitments',
                'Technical difficulties prevented virtual appointment connection and system integration issues with meeting platform',
                'Travel restrictions impacted client attendance and international business travel policies changed unexpectedly',
                'Company restructuring delayed appointment scheduling and organizational changes affected meeting priorities',
                'Legal review required before proceeding with appointment and compliance documentation needs additional verification',
                'Vendor selection process still in progress and appointment timing conflicts with evaluation schedule',
                'Budget approval pending from finance department and quarterly review process delayed funding decisions',
                'Project timeline shifted affecting appointment relevance and deliverable schedules changed due to client requirements',
                'Client requested different consultant for appointment session and expertise alignment with project needs',
                'Compliance issues need resolution first before appointment and regulatory requirements must be addressed',
                'Market conditions changed requiring strategy revision and appointment objectives need realignment with business goals'
            ];

            $baseDates = [
                // Past 6 months to current (25 records) - oldest first
                Carbon::now()->subMonths(6)->setDay(5), Carbon::now()->subMonths(6)->setDay(12), Carbon::now()->subMonths(5)->setDay(8),
                Carbon::now()->subMonths(5)->setDay(15), Carbon::now()->subMonths(5)->setDay(22), Carbon::now()->subMonths(4)->setDay(3),
                Carbon::now()->subMonths(4)->setDay(10), Carbon::now()->subMonths(4)->setDay(18), Carbon::now()->subMonths(3)->setDay(5),
                Carbon::now()->subMonths(3)->setDay(12), Carbon::now()->subMonths(3)->setDay(20), Carbon::now()->subMonths(2)->setDay(7),
                Carbon::now()->subMonths(2)->setDay(14), Carbon::now()->subMonths(2)->setDay(21), Carbon::now()->subMonths(1)->setDay(4),
                Carbon::now()->subMonths(1)->setDay(11), Carbon::now()->subMonths(1)->setDay(18), Carbon::now()->subMonths(1)->setDay(25),
                Carbon::now()->subDays(25), Carbon::now()->subDays(20), Carbon::now()->subDays(15), Carbon::now()->subDays(12),
                Carbon::now()->subDays(8), Carbon::now()->subDays(5), Carbon::now()->subDays(2),
                // Future 2 months (15 records)
                Carbon::now()->addDays(3), Carbon::now()->addDays(7), Carbon::now()->addDays(12), Carbon::now()->addDays(18),
                Carbon::now()->addDays(25), Carbon::now()->addMonth()->setDay(5), Carbon::now()->addMonth()->setDay(10),
                Carbon::now()->addMonth()->setDay(15), Carbon::now()->addMonth()->setDay(20), Carbon::now()->addMonth()->setDay(25),
                Carbon::now()->addMonths(2)->setDay(5), Carbon::now()->addMonths(2)->setDay(10), Carbon::now()->addMonths(2)->setDay(15),
                Carbon::now()->addMonths(2)->setDay(20), Carbon::now()->addMonths(2)->setDay(25)
            ];

            $schedules = [
                ['name' => 'John Anderson', 'email' => 'john.anderson123@gmail.com', 'phone' => '+12135550101', 'status' => 'complete'],
                ['name' => 'Sarah Mitchell', 'email' => 'sarah.mitchell456@yahoo.com', 'phone' => '+14045550102', 'status' => 'complete'],
                ['name' => 'Michael Chen', 'email' => 'michael.chen789@hotmail.com', 'phone' => '+22175550103', 'status' => 'complete'],
                ['name' => 'Emily Rodriguez', 'email' => 'emily.rodriguez321@outlook.com', 'phone' => '+33145550104', 'status' => 'reject'],
                ['name' => 'David Thompson', 'email' => 'david.thompson654@gmail.com', 'phone' => '+44207550105', 'status' => 'complete'],
                ['name' => 'Lisa Wang', 'email' => 'lisa.wang987@icloud.com', 'phone' => '+49305550106', 'status' => 'complete'],
                ['name' => 'Robert Johnson', 'email' => 'robert.johnson147@yahoo.com', 'phone' => '+81335550107', 'status' => 'complete'],
                ['name' => 'Amanda Davis', 'email' => 'amanda.davis258@gmail.com', 'phone' => '+86105550108', 'status' => 'reject'],
                ['name' => 'James Wilson', 'email' => 'james.wilson369@hotmail.com', 'phone' => '+55115550109', 'status' => 'complete'],
                ['name' => 'Jennifer Brown', 'email' => 'jennifer.brown741@outlook.com', 'phone' => '+52555550110', 'status' => 'complete'],
                ['name' => 'Christopher Lee', 'email' => 'christopher.lee852@gmail.com', 'phone' => '+61295550111', 'status' => 'complete'],
                ['name' => 'Michelle Garcia', 'email' => 'michelle.garcia963@yahoo.com', 'phone' => '+91805550112', 'status' => 'complete'],
                ['name' => 'Kevin Martinez', 'email' => 'kevin.martinez159@icloud.com', 'phone' => '+12125550113', 'status' => 'complete'],
                ['name' => 'Rachel Taylor', 'email' => 'rachel.taylor357@gmail.com', 'phone' => '+14155550114', 'status' => 'reject'],
                ['name' => 'Daniel White', 'email' => 'daniel.white468@hotmail.com', 'phone' => '+22175550115', 'status' => 'complete'],
                ['name' => 'Nicole Harris', 'email' => 'nicole.harris579@outlook.com', 'phone' => '+33145550116', 'status' => 'complete'],
                ['name' => 'Andrew Clark', 'email' => 'andrew.clark681@gmail.com', 'phone' => '+44207550117', 'status' => 'complete'],
                ['name' => 'Stephanie Lewis', 'email' => 'stephanie.lewis792@yahoo.com', 'phone' => '+49305550118', 'status' => 'reject'],
                ['name' => 'Matthew Walker', 'email' => 'matthew.walker813@icloud.com', 'phone' => '+81335550119', 'status' => 'complete'],
                ['name' => 'Laura Hall', 'email' => 'laura.hall924@gmail.com', 'phone' => '+86105550120', 'status' => 'complete'],
                ['name' => 'Ryan Allen', 'email' => 'ryan.allen135@hotmail.com', 'phone' => '+55115550121', 'status' => 'complete'],
                ['name' => 'Kimberly Young', 'email' => 'kimberly.young246@outlook.com', 'phone' => '+52555550122', 'status' => 'complete'],
                ['name' => 'Brandon King', 'email' => 'brandon.king357@gmail.com', 'phone' => '+61295550123', 'status' => 'complete'],
                ['name' => 'Megan Scott', 'email' => 'megan.scott468@yahoo.com', 'phone' => '+91805550124', 'status' => 'complete'],
                ['name' => 'Tyler Adams', 'email' => 'tyler.adams579@icloud.com', 'phone' => '+12125550125', 'status' => 'complete'],
                ['name' => 'Samantha Baker', 'email' => 'samantha.baker681@gmail.com', 'phone' => '+14155550126', 'status' => 'approved'],
                ['name' => 'Jonathan Green', 'email' => 'jonathan.green792@hotmail.com', 'phone' => '+22175550127', 'status' => 'pending'],
                ['name' => 'Ashley Nelson', 'email' => 'ashley.nelson813@outlook.com', 'phone' => '+33145550128', 'status' => 'approved'],
                ['name' => 'Joshua Carter', 'email' => 'joshua.carter924@gmail.com', 'phone' => '+44207550129', 'status' => 'pending'],
                ['name' => 'Brittany Mitchell', 'email' => 'brittany.mitchell135@yahoo.com', 'phone' => '+49305550130', 'status' => 'approved'],
                ['name' => 'Nathan Rodriguez', 'email' => 'nathan.rodriguez246@icloud.com', 'phone' => '+81335550131', 'status' => 'pending'],
                ['name' => 'Alexis Thompson', 'email' => 'alexis.thompson357@gmail.com', 'phone' => '+86105550132', 'status' => 'approved'],
                ['name' => 'Marcus Johnson', 'email' => 'marcus.johnson468@hotmail.com', 'phone' => '+55115550133', 'status' => 'pending'],
                ['name' => 'Vanessa Davis', 'email' => 'vanessa.davis579@outlook.com', 'phone' => '+52555550134', 'status' => 'approved'],
                ['name' => 'Gregory Wilson', 'email' => 'gregory.wilson681@gmail.com', 'phone' => '+61295550135', 'status' => 'pending'],
                ['name' => 'Tiffany Brown', 'email' => 'tiffany.brown792@yahoo.com', 'phone' => '+91805550136', 'status' => 'approved'],
                ['name' => 'Derek Garcia', 'email' => 'derek.garcia813@icloud.com', 'phone' => '+12125550137', 'status' => 'pending'],
                ['name' => 'Crystal Martinez', 'email' => 'crystal.martinez924@gmail.com', 'phone' => '+14155550138', 'status' => 'approved'],
                ['name' => 'Trevor Lee', 'email' => 'trevor.lee135@hotmail.com', 'phone' => '+22175550139', 'status' => 'pending'],
                ['name' => 'Monica White', 'email' => 'monica.white246@outlook.com', 'phone' => '+33145550140', 'status' => 'approved']
            ];

            foreach ($schedules as $index => $schedule) {
                $appointment = $appointments->random();
                if (!$appointment) continue;

                // Get appointment week days and find valid date
                $appointmentWeekDays = json_decode($appointment->week_day, true) ?? [];
                $baseDate = $baseDates[$index];

                // Find next valid day for this appointment
                $validDate = $baseDate->copy();
                for ($i = 0; $i < 7; $i++) {
                    $dayName = strtolower($validDate->format('l'));
                    if (in_array($dayName, $appointmentWeekDays)) {
                        break;
                    }
                    $validDate->addDay();
                }

                $dayName = strtolower($validDate->format('l'));
                $businessHour = $appointmentHours->get($dayName);

                // Use default business hours if not found or day is off
                if (!$businessHour || $businessHour->day_off) {
                    $startHour = '09:00:00';
                    $endHour = '17:00:00';
                } else {
                    $startHour = $businessHour->start_time;
                    $endHour = $businessHour->end_time;
                }

                // Calculate appointment times based on duration - use exact time slots
                $dayStart = Carbon::parse($startHour);
                $dayEnd = Carbon::parse($endHour);
                $duration = $appointment->duration ?? 60;

                // Generate available time slots based on duration
                $availableSlots = [];
                $currentSlot = $dayStart->copy();

                while ($currentSlot->copy()->addMinutes($duration)->lte($dayEnd)) {
                    $availableSlots[] = $currentSlot->copy();
                    $currentSlot->addMinutes($duration);
                }

                if (empty($availableSlots)) {
                    $startTime = $dayStart->copy();
                } else {
                    $startTime = $availableSlots[array_rand($availableSlots)];
                }

                $endTime = $startTime->copy()->addMinutes($duration);

                // Get questions for this appointment and generate suitable answers
                $appointmentQuestionIds = json_decode($appointment->question_ids, true) ?? [];
                $appointmentQuestions = $questions->whereIn('id', $appointmentQuestionIds);

                $questionAnswers = [];
                foreach ($appointmentQuestions as $question) {
                    $availableAnswers = json_decode($question->available_answers, true);

                    if ($question->question_type == '2') {
                        // Text input - generate specific answers based on actual questions
                        switch ($question->question_name) {
                            case 'Do you have any specific concerns or symptoms?':
                                $options = [
                                    'Experiencing frequent headaches and fatigue',
                                    'Having trouble sleeping and feeling anxious',
                                    'Joint pain and stiffness in the morning',
                                    'Digestive issues and stomach discomfort',
                                    'No specific concerns, just routine checkup'
                                ];
                                $questionAnswers[$question->question_name] = $options[array_rand($options)];
                                break;

                            case 'Any allergies or medical conditions?':
                                $options = [
                                    'Allergic to penicillin and shellfish',
                                    'Diabetes type 2, controlled with medication',
                                    'High blood pressure, taking daily medication',
                                    'No known allergies or medical conditions',
                                    'Asthma, use inhaler as needed'
                                ];
                                $questionAnswers[$question->question_name] = $options[array_rand($options)];
                                break;

                            case 'Additional notes or requests':
                                $options = [
                                    'Please schedule early morning appointment if possible',
                                    'Need parking assistance due to mobility issues',
                                    'Prefer female doctor for consultation',
                                    'Will bring medical records from previous doctor',
                                    'No special requests at this time'
                                ];
                                $questionAnswers[$question->question_name] = $options[array_rand($options)];
                                break;

                            case 'Do you have chronic illnesses?':
                                $options = [
                                    'Hypertension managed with medication',
                                    'Type 2 diabetes, diet controlled',
                                    'Arthritis in knees and hands',
                                    'No chronic illnesses',
                                    'Heart condition, regular monitoring required'
                                ];
                                $questionAnswers[$question->question_name] = $options[array_rand($options)];
                                break;

                            case 'Emergency contact phone':
                                $countryCodes = ['+91', '+1', '+44', '+61', '+98', '+22', '+33', '+55', '+66', '+77', '+88', '+99', '+11', '+22'];
                                $phone = $countryCodes[array_rand($countryCodes)] . mt_rand(1000000000, 9999999999);
                                $questionAnswers[$question->question_name] = $phone;
                                break;

                            case 'Emergency contact name':
                                $names = ['John Doe', 'Jane Smith', 'Michael Brown', 'Emily Johnson', 'David Lee'];
                                $questionAnswers[$question->question_name] = $names[array_rand($names)];
                                break;

                            default:
                                $sentences = [
                                    'Yes', 'No', 'Not applicable', 'Will discuss during meeting', 'No comment'
                                ];
                                $questionAnswers[$question->question_name] = $sentences[array_rand($sentences)];
                        }

                    } elseif (!empty($availableAnswers)) {
                        // Dropdown/Radio/Checkbox - use available options from question
                        if ($question->question_type == '3') {
                            // Checkbox - can select multiple
                            $count = rand(1, min(3, count($availableAnswers)));
                            shuffle($availableAnswers);
                            $questionAnswers[$question->question_name] = array_slice($availableAnswers, 0, $count);
                        } else {
                            // Radio/Dropdown - single selection
                            $questionAnswers[$question->question_name] = $availableAnswers[array_rand($availableAnswers)];
                        }
                    } else {
                        // Fallback for questions without available answers
                        $defaults = ['Yes', 'No', 'Not applicable', 'Will discuss during meeting'];
                        $questionAnswers[$question->question_name] = $defaults[array_rand($defaults)];
                    }
                }

                Schedule::create([
                    'unique_id' => 'SCH-' . strtoupper(uniqid()),
                    'user_id' => $userId,
                    'name' => $schedule['name'],
                    'email' => $schedule['email'],
                    'phone' => $schedule['phone'],
                    'date' => $validDate->format('Y-m-d'),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'appointment_id' => $appointment->id,
                    'questions' => json_encode($questionAnswers),
                    'cancel_description' => $schedule['status'] === 'reject' ? $cancelReasons[array_rand($cancelReasons)] : null,
                    'status' => $schedule['status'],
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ]);
            }
        }
    }
}
