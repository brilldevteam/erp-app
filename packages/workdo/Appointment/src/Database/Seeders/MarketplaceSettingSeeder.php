<?php

namespace Workdo\Appointment\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/Appointment/src/marketplace/' . $file->getFilename();
                }
            }
        }
        
        sort($screenshots);
        
        MarketplaceSetting::firstOrCreate(['module' => 'Appointment'], [
            'module' => 'Appointment',
            'title' => 'Appointment Add-On',
            'subtitle' => 'Streamlines scheduling by allowing users to create appointments with custom questions and seamless client management',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Appointment Add-On for wazely.io',
                        'subtitle' => 'Create appointments with custom questions, share links with clients, and track all submissions with efficient appointment tracking.',
                        'primary_button_text' => 'Install Appointment Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/Appointment/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Appointment Add-On',
                        'subtitle' => 'Complete booking system with dashboard overview, calendar integration, and seamless follow-ups'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Comprehensive Appointment Management Features',
                        'description' => 'Dashboard overview with key metrics, calendar view, and recent activity tracking for efficient appointment management.',
                        'subSections' => [
                            [
                                'title' => 'Create & Manage Appointments',
                                'description' => 'Design personalized appointment types with both free and paid options, customizable availability, and precise duration control.',
                                'keyPoints' => ['Free and paid services', 'Business hours availability', 'Information collection control', 'Essential client details gathering'],
                                'screenshot' => '/packages/workdo/Appointment/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Custom Question Builder',
                                'description' => 'Create questions to collect information before booking with various question types and customizable requirements.',
                                'keyPoints' => ['Relevant client information collection', 'Customized booking forms', 'Required information provision', 'Unnecessary data avoidance'],
                                'screenshot' => '/packages/workdo/Appointment/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Manage Schedules',
                                'description' => 'Handle appointment schedules with detailed tracking, status management, and feedback integration capabilities.',
                                'keyPoints' => ['Central schedule tracking', 'Efficient status monitoring', 'Schedule updates capability', 'Client feedback gathering'],
                                'screenshot' => '/packages/workdo/Appointment/src/marketplace/image3.png'
                            ],
                            [
                                'title' => 'Handle Appointment Callbacks',
                                'description' => 'Manage callbacks for appointments with comprehensive tracking and reconnection capabilities for missed appointments.',
                                'keyPoints' => ['Missed appointment reconnection', 'Callback organization maintenance', 'Effective client follow-up', 'Outdated entry removal'],
                                'screenshot' => '/packages/workdo/Appointment/src/marketplace/image4.png'
                            ],
                            [
                                'title' => 'Client-Friendly Frontend Form',
                                'description' => 'Provide intuitive booking experience with clean professional forms and real-time slot availability display.',
                                'keyPoints' => ['Intuitive booking experience', 'Relevant booking fields display', 'Easy appointment searching', 'Real-time slot availability'],
                                'screenshot' => '/packages/workdo/Appointment/src/marketplace/image5.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Appointment Add-On in Action',
                        'subtitle' => 'See how streamlined scheduling and client management transforms your appointment workflow',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Appointment Add-On?',
                        'subtitle' => 'Transform appointment management with streamlined scheduling and comprehensive client tracking',
                        'benefits' => [
                            [
                                'title' => 'Custom Question Builder',
                                'description' => 'Create personalized forms with various question types to collect essential client information.',
                                'icon' => 'FileText',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Dashboard Overview',
                                'description' => 'Clear booking system overview with key metrics and status breakdown visualization.',
                                'icon' => 'Activity',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Schedule Management',
                                'description' => 'Handle appointment schedules with detailed tracking and efficient status monitoring.',
                                'icon' => 'CheckCircle',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Callback System',
                                'description' => 'Manage callbacks for missed appointments with effective client follow-up capabilities.',
                                'icon' => 'Users',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Frontend Integration',
                                'description' => 'Provide intuitive booking experience with real-time slot availability display.',
                                'icon' => 'GitBranch',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Flexible Pricing',
                                'description' => 'Offer both free and paid appointment options with customizable availability settings.',
                                'icon' => 'Play',
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