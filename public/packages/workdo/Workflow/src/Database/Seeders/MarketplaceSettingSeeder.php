<?php

namespace Workdo\Workflow\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/Workflow/src/marketplace/' . $file->getFilename();
                }
            }
        }
        sort($screenshots);

        MarketplaceSetting::firstOrCreate(['module' => 'Workflow'], [
            'module' => 'Workflow',
            'title' => 'Workflow Automation System',
            'subtitle' => 'Advanced workflow automation with conditional triggers and multi-channel notifications',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Workflow Automation System for WorkDo Dash',
                        'subtitle' => 'Automate your business processes with intelligent workflows, conditional triggers, and multi-channel notifications.',
                        'primary_button_text' => 'Install Workflow Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/Workflow/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Workflow Automation Module',
                        'subtitle' => 'Enhance productivity with intelligent workflow automation and conditional processing'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Advanced Workflow Automation Features',
                        'description' => 'Our workflow automation system provides intelligent process automation with conditional logic and multi-channel communication capabilities.',
                        'subSections' => [
                            [
                                'title' => 'Conditional Workflow Engine',
                                'description' => 'Create sophisticated workflows with conditional logic based on field values, operators, and dynamic data evaluation. Build complex automation rules that respond intelligently to different business scenarios and trigger appropriate actions based on specific conditions.',
                                'keyPoints' => ['Dynamic condition evaluation with multiple operators', 'Field-based conditional logic processing', 'Complex workflow rule creation', 'Real-time data-driven decision making'],
                                'screenshot' => '/packages/workdo/Workflow/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Multi-Channel Action System',
                                'description' => 'Execute automated actions across multiple communication channels including Email, SMS, Telegram, Slack, and WhatsApp. Integrate seamlessly with various notification services to ensure your team stays informed through their preferred communication methods.',
                                'keyPoints' => ['Email automation with customizable templates', 'SMS notifications via Twilio integration', 'Telegram and Slack messaging support', 'WhatsApp API integration for instant alerts'],
                                'screenshot' => '/packages/workdo/Workflow/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Cross-Module Integration',
                                'description' => 'Seamlessly integrate workflows across all business modules including CRM, Project Management, Accounting, and HR systems. Automate processes that span multiple departments and ensure consistent workflow execution across your entire organization.',
                                'keyPoints' => ['CRM workflow automation for leads and deals', 'Project and task management integration', 'Financial process automation support', 'HR workflow integration capabilities'],
                                'screenshot' => '/packages/workdo/Workflow/src/marketplace/image3.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Workflow Automation in Action',
                        'subtitle' => 'See how intelligent workflows transform your business processes',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Workflow Automation Module?',
                        'subtitle' => 'Transform business processes with intelligent automation and seamless integration',
                        'benefits' => [
                            [
                                'title' => 'Intelligent Automation',
                                'description' => 'Create smart workflows with conditional logic and dynamic processing.',
                                'icon' => 'Zap',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Multi-Channel Notifications',
                                'description' => 'Send notifications via Email, SMS, Telegram, Slack, and WhatsApp.',
                                'icon' => 'MessageSquare',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Cross-Module Integration',
                                'description' => 'Integrate workflows across CRM, Projects, Accounting, and HR modules.',
                                'icon' => 'GitBranch',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Conditional Processing',
                                'description' => 'Execute actions based on complex conditions and field evaluations.',
                                'icon' => 'Filter',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Real-Time Triggers',
                                'description' => 'Instant workflow execution when events occur across modules.',
                                'icon' => 'Clock',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Process Automation',
                                'description' => 'Eliminate manual tasks with comprehensive business process automation.',
                                'icon' => 'Play',
                                'color' => 'teal'
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
