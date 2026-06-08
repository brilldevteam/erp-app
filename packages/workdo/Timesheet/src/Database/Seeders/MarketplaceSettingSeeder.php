<?php

namespace Workdo\Timesheet\Database\Seeders;

use Illuminate\Database\Seeder;
use Workdo\LandingPage\Models\MarketplaceSetting;
use Illuminate\Support\Facades\File;

class MarketplaceSettingSeeder extends Seeder
{
    public function run()
    {
        // Get all available screenshots from marketplace directory
        $marketplaceDir = __DIR__ . '/../../marketplace';
        $screenshots = [];

        if (File::exists($marketplaceDir)) {
            $files = File::files($marketplaceDir);
            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                    $screenshots[] = '/packages/workdo/Timesheet/src/marketplace/' . $file->getFilename();
                }
            }
        }

        sort($screenshots);
        
        MarketplaceSetting::firstOrCreate(['module' => 'Timesheet'], [
            'module' => 'Timesheet',
            'title' => 'Timesheet Add-On',
            'subtitle' => 'Flexible Time Tracking & Smart Workflow Logging with HRM and Project integration',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Timesheet Add-On for WorkDo Dash',
                        'subtitle' => 'Easily capture and manage work hours with a system built for flexibility and precision. The Timesheet Add-On allows teams to log time through clock-in/out records, project-based tasks, or manual entries—adapting to every workflow.',
                        'primary_button_text' => 'Install Timesheet Add-On',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'View Features',
                        'secondary_button_link' => '#features',
                        'image' => '/packages/workdo/Timesheet/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Timesheet Add-On',
                        'subtitle' => 'Flexible Time Tracking & Smart Workflow Logging with HRM and Project Integration'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Complete Time Management Solution',
                        'description' => 'With smart role-based controls and seamless integration with HRM and Project Add-Ons, it keeps your time data accurate, organized, and actionable.',
                        'subSections' => [
                            [
                                'title' => 'Flexible Timesheet Entry & Role-Based Access',
                                'description' => 'The Timesheet Add-On offers three intelligent ways to record time — automatically logs time using HRM attendance, tracks hours linked to specific projects and tasks, or allows manual entry when no Add-On is active. Built with security and simplicity in mind, staff users can manage only their own entries, while admin users gain full visibility and control.',
                                'keyPoints' => ['Automatically logs time using HRM attendance', 'Tracks hours linked to specific projects and tasks', 'Staff can view and manage only their own data', 'Admins have access to all timesheets and users'],
                                'screenshot' => '/packages/workdo/Timesheet/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Streamlined Timesheet Management',
                                'description' => 'Effortlessly log work hours and minutes on a daily basis. Users can track the exact amount of time spent on specific tasks and projects. Each entry can include a time type and detailed notes to maintain clarity and purpose. Automatic timestamps ensure every log is auditable and transparent.',
                                'keyPoints' => ['Daily time entry with hour & minute split', 'Entries can be searched, filtered, or added instantly', 'Detailed note logging for each entry', 'Automatic timestamps for auditable transparency'],
                                'screenshot' => '/packages/workdo/Timesheet/src/marketplace/image2.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Timesheet Add-On in Action',
                        'subtitle' => 'See how our flexible time tracking adapts to every workflow',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Timesheet Add-On?',
                        'subtitle' => 'Flexible time tracking with smart workflow logging and seamless integration',
                        'benefits' => [
                            [
                                'title' => 'HRM & Project Integration',
                                'description' => 'Seamlessly works with HRM and Project Add-Ons for complete time management.',
                                'icon' => 'GitBranch',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Flexible Entry Methods',
                                'description' => 'Three intelligent ways to record time: HRM attendance, project tasks, or manual entry.',
                                'icon' => 'Clock',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Role-Based Smart Access',
                                'description' => 'Staff manage their own entries while admins have full visibility and control.',
                                'icon' => 'Users',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Daily Time Tracking',
                                'description' => 'Log work hours and minutes with exact time spent on specific tasks and projects.',
                                'icon' => 'Timer',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Detailed Note Logging',
                                'description' => 'Each entry includes time type and detailed notes for clarity and purpose.',
                                'icon' => 'FileText',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Automatic Timestamps',
                                'description' => 'Every log is auditable and transparent with automatic timestamp recording.',
                                'icon' => 'CheckCircle',
                                'color' => 'indigo'
                            ]
                        ]
                    ]
                ],
                'section_visibility' => [
                    'header' => true,
                    'hero' => true,
                    'modules' => true,
                    'dedication' => true,
                    'screenshots' => true,
                    'why_choose' => true,
                    'cta' => true,
                    'footer' => true
                ],
                'section_order' => ['header', 'hero', 'modules', 'dedication', 'screenshots', 'why_choose', 'cta', 'footer']
            ]
        ]);
    }
}
