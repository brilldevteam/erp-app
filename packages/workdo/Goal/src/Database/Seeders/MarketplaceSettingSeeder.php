<?php

namespace Workdo\Goal\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/Goal/src/marketplace/' . $file->getFilename();
                }
            }
        }

        sort($screenshots);

        MarketplaceSetting::firstOrCreate(['module' => 'Goal'], [
            'module' => 'Goal',
            'title' => 'Goal Module Marketplace',
            'subtitle' => 'Comprehensive goal tools for your applications',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Goal Module for wazely.io',
                        'subtitle' => 'Streamline your goal workflow with comprehensive tools and automated management.',
                        'primary_button_text' => 'Install Goal Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/Goal/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Goal Module',
                        'subtitle' => 'Enhance your workflow with powerful goal tools'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Dedicated Financial Goal Features',
                        'description' => 'Manage your money effectively by setting goals, tracking progress automatically, and achieving financial milestones with ease.',
                        'subSections' => [
                            [
                                'title' => 'Define Financial Goals',
                                'description' => 'The Financial Goal Add-On helps you manage your money by letting you set and organize different goals. Whether it\'s saving for a vacation, paying off debt, or cutting monthly costs, it helps you stay on track. You can prioritize goals, link them to your accounts, and automatically track your progress with journal entries.',
                                'keyPoints' => ['Set and organize financial goals for easy tracking', 'Prioritize goals and link them to bank accounts', 'Automatically track progress with journal entry updates', 'Simplify saving, debt repayment, and cost-cutting goals'],
                                'screenshot' => '/packages/workdo/Goal/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Automatically Track Progress',
                                'description' => 'This Add-On makes tracking your progress super easy. It updates your goal every time you contribute, showing exactly where you stand. You can see how much progress you\'ve made with visual progress bars and check if you\'re on track, ahead, or behind. It also calculates when you\'ll finish based on how much you\'ve been working, and uses color codes (green, yellow, red) to quickly show your progress.',
                                'keyPoints' => ['Track progress with real-time updates after each contribution', 'Visual progress bars show how far you\'ve come', 'Color codes indicate if you\'re on track or behind', 'Calculates estimated completion time based on contributions made'],
                                'screenshot' => '/packages/workdo/Goal/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Achieve Financial Milestones',
                                'description' => 'Breaking big financial goals into smaller milestones makes them easier to reach and more rewarding. You can set specific amounts and dates for each milestone, helping you stay motivated. As you make progress, the Add-On automatically updates each milestone from "pending" to "achieved." This lets you see both your short-term progress and long-term goals, while celebrating each win along the way.',
                                'keyPoints' => ['Break large goals into smaller, more achievable milestones', 'Set specific amounts and dates for each milestone', 'Track progress as milestones shift from "pending" to "achieved"', 'Celebrate short-term wins while working toward long-term goals'],
                                'screenshot' => '/packages/workdo/Goal/src/marketplace/image3.png'
                            ],
                            [
                                'title' => 'Manage Financial Contributions',
                                'description' => 'Every dollar you contribute counts, and this Add-On tracks all your payments, transfers, and deposits automatically. It records everything in one system, including any manual entries for transactions outside the system. Each contribution shows the date, amount, and any notes you add. You can see a complete history of your contributions, helping you track your progress and spot patterns in your financial journey.',
                                'keyPoints' => ['Automatically track all payments, transfers, and deposits', 'Record manual entries for transactions outside the system', 'View a complete history of all your contributions', 'Track progress and spot patterns in your financial journey'],
                                'screenshot' => '/packages/workdo/Goal/src/marketplace/image4.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Goal Module in Action',
                        'subtitle' => 'See how our goal tools improve your workflow',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Goal Module?',
                        'subtitle' => 'Improve efficiency with comprehensive goal management',
                        'benefits' => [
                            [
                                'title' => 'Goal Organization & Prioritization',
                                'description' => 'Set and organize multiple financial goals with priorities and link them to bank accounts for seamless tracking.',
                                'icon' => 'Target',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Automatic Progress Updates',
                                'description' => 'Track progress automatically with journal entry updates every time you contribute to your goals.',
                                'icon' => 'RefreshCw',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Visual Progress Tracking',
                                'description' => 'See your progress with visual bars and color codes (green, yellow, red) showing if you\'re on track or behind.',
                                'icon' => 'TrendingUp',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Milestone Management',
                                'description' => 'Break large goals into smaller milestones with specific amounts and dates that automatically update from pending to achieved.',
                                'icon' => 'Flag',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Contribution History',
                                'description' => 'View complete history of all payments, transfers, and deposits with dates, amounts, and notes in one place.',
                                'icon' => 'DollarSign',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Completion Estimates',
                                'description' => 'Calculate estimated completion time based on your contribution patterns and spot trends in your financial journey.',
                                'icon' => 'Clock',
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
