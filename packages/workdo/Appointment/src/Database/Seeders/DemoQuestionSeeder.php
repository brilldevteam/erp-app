<?php

namespace Workdo\Appointment\Database\Seeders;

use Workdo\Appointment\Models\Question;
use Illuminate\Database\Seeder;

class DemoQuestionSeeder extends Seeder
{
    public function run($userId): void
    {
        if (Question::where('created_by', $userId)->exists()) {
            return;
        }

        if (!empty($userId)) {
            $questions = [
                [
                    'question_name' => 'Purpose of your appointment?',
                    'question_type' => '1', // dropdown
                    'available_answers' => json_encode(['Consultation', 'Follow-up', 'New Patient', 'Emergency', 'Routine Check-up']),
                    'required_answer' => true,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'How did you hear about us?',
                    'question_type' => '0', // radio
                    'available_answers' => json_encode(['Google Search', 'Social Media', 'Friend Referral', 'Advertisement', 'Website']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Do you have any specific concerns or symptoms?',
                    'question_type' => '2', // text
                    'available_answers' => '',
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Which services are you interested in?',
                    'question_type' => '3', // checkbox
                    'available_answers' => json_encode(['General Consultation', 'Health Screening', 'Vaccination', 'Lab Tests', 'Specialist Referral', 'Dietary Consultation', 'Mental Health Counseling']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Preferred communication method',
                    'question_type' => '0', // radio
                    'available_answers' => json_encode(['Email', 'Phone Call', 'SMS', 'WhatsApp']),
                    'required_answer' => true,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Do you have insurance?',
                    'question_type' => '0', // radio
                    'available_answers' => json_encode(['Yes', 'No', 'Not Sure']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Any allergies or medical conditions?',
                    'question_type' => '2', // text
                    'available_answers' => '',
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Age group',
                    'question_type' => '1', // dropdown
                    'available_answers' => json_encode(['Under 18', '18-25', '26-35', '36-45', '46-55', '56-65', 'Over 65']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Is this your first visit?',
                    'question_type' => '0', // radio
                    'available_answers' => json_encode(['Yes', 'No']),
                    'required_answer' => true,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Additional notes or requests',
                    'question_type' => '2', // text
                    'available_answers' => '',
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Preferred appointment time',
                    'question_type' => '1', // dropdown
                    'available_answers' => json_encode(['Morning', 'Afternoon', 'Evening']),
                    'required_answer' => true,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Do you need a reminder call?',
                    'question_type' => '0', // radio
                    'available_answers' => json_encode(['Yes', 'No']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Do you have a referral?',
                    'question_type' => '0', // radio
                    'available_answers' => json_encode(['Yes', 'No']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Preferred doctor or staff member',
                    'question_type' => '1', // dropdown
                    'available_answers' => json_encode(['Any Available', 'Dr. Smith', 'Dr. Johnson', 'Dr. Lee']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Do you need wheelchair access?',
                    'question_type' => '0', // radio
                    'available_answers' => json_encode(['Yes', 'No']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Preferred language',
                    'question_type' => '3', // checkbox
                    'available_answers' => json_encode(['English', 'Spanish', 'French', 'Mandarin', 'Other', 'Sign Language', 'Braille Support']),
                    'required_answer' => true,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Do you have chronic illnesses?',
                    'question_type' => '2', // text
                    'available_answers' => '',
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Preferred consultation types',
                    'question_type' => '3', // checkbox
                    'available_answers' => json_encode(['In-person', 'Video Call', 'Home Visit']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Preferred reminders',
                    'question_type' => '3', // checkbox
                    'available_answers' => json_encode(['Email', 'SMS', 'Phone Call', 'App Notification']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Emergency contact phone',
                    'question_type' => '2', // text
                    'available_answers' => '',
                    'required_answer' => true,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Health concerns you want to discuss',
                    'question_type' => '3', // checkbox
                    'available_answers' => json_encode(['Heart', 'Diabetes', 'Respiratory', 'Allergies', 'Mental Health', 'Other']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Emergency contact name',
                    'question_type' => '2', // text
                    'available_answers' => '',
                    'required_answer' => true,
                    'enabled' => true,
                ],
                [
                    'question_name' => 'Additional services you may need',
                    'question_type' => '3', // checkbox
                    'available_answers' => json_encode(['Home Care', 'Follow-up Call', 'Dietary Advice', 'Physical Therapy', 'Mental Health Support']),
                    'required_answer' => false,
                    'enabled' => true,
                ],
            ];

            foreach ($questions as $questionData) {
                Question::create(array_merge($questionData, [
                    'enabled' => $questionData['enabled'] ?? true,
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ]));
            }
        }
    }
}
