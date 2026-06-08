<?php

namespace Workdo\Appointment\Database\Seeders;

use Workdo\Appointment\Models\AppointmentSetting;
use Illuminate\Database\Seeder;

class DemoAppointmentSettingsSeeder extends Seeder
{
    public function run($userId): void
    {
        if (AppointmentSetting::where('created_by', $userId)->exists()) {
            return;
        }

        if (!empty($userId))
        {
            $settings = [
                'terms_conditions' => json_encode([
                    'content' => '<h2>Terms and Conditions</h2>
                    <h3>1. Appointment Booking</h3>
                    <p>By booking an appointment through our system, you agree to arrive on time and provide accurate information.</p>

                    <h3>2. Cancellation Policy</h3>
                    <p>Appointments can be cancelled up to 24 hours in advance. Late cancellations may incur a fee.</p>

                    <h3>3. No-Show Policy</h3>
                    <p>Failure to attend your scheduled appointment without prior notice may result in a charge.</p>

                    <h3>4. Rescheduling</h3>
                    <p>Appointments can be rescheduled subject to availability. Please provide at least 4 hours notice.</p>

                    <h3>5. Privacy</h3>
                    <p>All personal information provided during booking will be kept confidential and used only for appointment purposes.</p>',
                    'enabled' => true
                ]),

                'privacy_policy' => json_encode([
                    'content' => '<h2>Privacy Policy</h2>
                    <h3>Information We Collect</h3>
                    <p>We collect information you provide when booking appointments, including your name, email, phone number, and appointment preferences.</p>

                    <h3>How We Use Your Information</h3>
                    <ul>
                        <li>To schedule and manage your appointments</li>
                        <li>To send appointment confirmations and reminders</li>
                        <li>To improve our services</li>
                        <li>To communicate important updates</li>
                    </ul>

                    <h3>Information Sharing</h3>
                    <p>We do not sell, trade, or share your personal information with third parties without your consent, except as required by law.</p>

                    <h3>Data Security</h3>
                    <p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

                    <h3>Contact Us</h3>
                    <p>If you have questions about this Privacy Policy, please contact us at privacy@example.com</p>',
                    'enabled' => true
                ]),

                'faq_settings' => json_encode([
                    'faq_title' => 'Frequently Asked Questions',
                    'faq_description' => 'Find answers to common questions about our appointment booking system.',
                    'faq_questions' => [
                        [
                            'title' => 'How do I book an appointment?',
                            'description' => 'Simply select your preferred date and time from our online booking calendar, fill in your details, and confirm your appointment. You will receive a confirmation email immediately.'
                        ],
                        [
                            'title' => 'Can I reschedule my appointment?',
                            'description' => 'Yes, you can reschedule your appointment up to 4 hours before the scheduled time. Please use the link in your confirmation email or contact us directly.'
                        ],
                        [
                            'title' => 'What if I need to cancel?',
                            'description' => 'You can cancel your appointment up to 24 hours in advance without any penalty. Cancellations made less than 24 hours before may incur a fee.'
                        ],
                        [
                            'title' => 'Will I receive a reminder?',
                            'description' => 'Yes, we send email reminders 24 hours and 1 hour before your scheduled appointment. Make sure to check your email regularly.'
                        ],
                        [
                            'title' => 'What should I prepare for my appointment?',
                            'description' => 'Please have any relevant documents ready and ensure you have a stable internet connection if this is a virtual appointment. Specific preparation instructions will be provided in your confirmation email.'
                        ],
                        [
                            'title' => 'How long will my appointment last?',
                            'description' => 'Appointment duration varies depending on the type of service selected. The estimated duration is shown during the booking process and in your confirmation email.'
                        ],
                        [
                            'title' => 'Is my personal information secure?',
                            'description' => 'Yes, we take data security seriously. All personal information is encrypted and stored securely. We never share your information with third parties without your consent.'
                        ],
                        [
                            'title' => 'What if I have technical issues during a virtual appointment?',
                            'description' => 'If you experience technical difficulties, please contact our support team immediately. We can provide alternative connection methods or reschedule if necessary.'
                        ]
                    ]
                ]),

                'logo_dark' => '',
                'favicon' => '',
                'title_text' => 'AppointmentHub',
                'footer_text' => '© 2025 AppointmentHub. All rights reserved.',
            ];

            foreach ($settings as $key => $value) {
                AppointmentSetting::updateOrCreate(
                    [
                        'key' => $key,
                        'created_by' => $userId
                    ],
                    [
                        'value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
