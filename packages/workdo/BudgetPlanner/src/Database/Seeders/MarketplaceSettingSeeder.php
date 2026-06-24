<?php

namespace Workdo\BudgetPlanner\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/BudgetPlanner/src/marketplace/' . $file->getFilename();
                }
            }
        }

        sort($screenshots);

        MarketplaceSetting::firstOrCreate(['module' => 'BudgetPlanner'], [
            'module' => 'BudgetPlanner',
            'title' => 'Budget Planner Module Marketplace',
            'subtitle' => 'Comprehensive budget planner tools for your applications',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Budget Planner Module for wazely.io',
                        'subtitle' => 'Streamline your budget planner workflow with comprehensive tools and automated management.',
                        'primary_button_text' => 'Install Budget Planner Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/BudgetPlanner/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Budget Planner Module',
                        'subtitle' => 'Enhance your workflow with powerful budget planner tools'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Dedicated Budget Planner Features',
                        'description' => 'Our budget planner module provides comprehensive capabilities for modern workflows.',
                        'subSections' => [
                            [
                                'title' => 'All-in-one Budget Management',
                                'description' => 'The Budget Planner lets your organization manage multiple budgets with defined periods throughout the year. It supports operational, capital, and cash flow budgets, allowing for organized financial planning. The system follows a structured workflow from draft to approval, ensuring proper authorization. This ensures financial discipline across your organization.',
                                'keyPoints' => ['Manage multiple budgets with defined periods each year.', 'Supports operational, capital, and cash flow budgets.', 'Structured workflow ensures proper draft-to-approval process.', 'Promotes financial discipline and oversight across the organization.'],
                                'screenshot' => '/packages/workdo/BudgetPlanner/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Smart Fund Allocation',
                                'description' => 'The allocation functionality lets you distribute budget amounts across different accounts, ensuring proper funding for each department. You can assign specific amounts to accounts, creating a financial plan aligned with your priorities. The system tracks allocated, spent, and remaining funds for full visibility. This helps prevent overspending, ensures resources reach the right areas, and maintains consistency between planning and execution.',
                                'keyPoints' => ['Distribute budget amounts across different accounts efficiently.', 'Assign specific amounts to align with business priorities.', 'Track allocated, spent, and remaining funds for visibility.', 'Prevent overspending and ensure proper resource allocation.'],
                                'screenshot' => '/packages/workdo/BudgetPlanner/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Automated Spending Tracking',
                                'description' => 'The Budget Planner automatically tracks spending against allocated budgets by syncing with journal entries, ensuring accuracy and eliminating manual input. Real-time updates show how current expenses compare to planned amounts. You can use date filters to spot trends and potential budget issues early. Variance analysis provides insights on whether you\'re under or over budget, enabling proactive adjustments to stay on track with financial goals.',
                                'keyPoints' => ['Automatically track spending by syncing with journal entries.', 'Real-time updates compare expenses to planned amounts.', 'Use date filters to spot trends and issues.', 'Variance analysis helps stay on track with goals.'],
                                'screenshot' => '/packages/workdo/BudgetPlanner/src/marketplace/image3.png'
                            ],
                            [
                                'title' => 'Seamless Integration & Control',
                                'description' => 'The Budget Planner integrates with your accounting system, linking directly to your chart of accounts and journal entries. It tracks budget activities with user accountability, ensuring transparency and proper approvals. Date range validation prevents data inconsistencies and maintains report integrity. The system\'s controlled workflows ensure budgets follow proper approval processes, creating a unified and accurate financial planning environment.',
                                'keyPoints' => ['Integrates with the accounting system and a chart of accounts.', 'Tracks budget activities with user accountability and transparency.', 'Date range validation ensures data consistency and integrity.', 'Controlled workflows ensure proper budget approval processes.'],
                                'screenshot' => '/packages/workdo/BudgetPlanner/src/marketplace/image4.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Budget Planner Module in Action',
                        'subtitle' => 'See how our budget planner tools improve your workflow',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Budget Planner Module?',
                        'subtitle' => 'Improve efficiency with comprehensive budget planner management',
                        'benefits' => [
                            [
                                'title' => 'Multi-Budget Management',
                                'description' => 'Manage operational, capital, and cash flow budgets with defined periods throughout the year.',
                                'icon' => 'Layers',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Structured Workflow',
                                'description' => 'Follow a controlled draft-to-approval process ensuring proper authorization and financial discipline.',
                                'icon' => 'GitBranch',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Smart Allocation',
                                'description' => 'Distribute budget amounts across accounts efficiently and track allocated, spent, and remaining funds.',
                                'icon' => 'PieChart',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Automated Tracking',
                                'description' => 'Automatically sync spending with journal entries for real-time expense tracking and variance analysis.',
                                'icon' => 'Activity',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Accounting Integration',
                                'description' => 'Seamlessly integrate with your chart of accounts and journal entries for unified financial planning.',
                                'icon' => 'Link',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Full Transparency',
                                'description' => 'Track budget activities with user accountability, proper approvals, and data validation for integrity.',
                                'icon' => 'Eye',
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
