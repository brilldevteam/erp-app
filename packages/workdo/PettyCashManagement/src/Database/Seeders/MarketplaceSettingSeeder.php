<?php

namespace Workdo\PettyCashManagement\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/PettyCashManagement/src/marketplace/' . $file->getFilename();
                }
            }
        }

        sort($screenshots);

        MarketplaceSetting::firstOrCreate(['module' => 'PettyCashManagement'], [
            'module' => 'PettyCashManagement',
            'title' => 'Petty Cash Management System',
            'subtitle' => 'Handling petty cash doesn\'t have to be a tedious task. The Petty Cash Management module brings structure and ease to the process by offering a centralized platform to record and monitor transactions.',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Petty Cash Management for WorkDo Dash',
                        'subtitle' => 'Handling petty cash doesn\'t have to be a tedious task. The Petty Cash Management module in Dash SaaS brings structure and ease to the process. By offering a centralized platform to record and monitor transactions, it helps businesses maintain order and transparency in their day-to-day cash dealings, making petty cash management stress-free and efficient.',
                        'primary_button_text' => 'Install Petty Cash Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/PettyCashManagement/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Petty Cash Management Module',
                        'subtitle' => 'Streamline cash handling, expense tracking, and financial accountability'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Comprehensive Petty Cash Management Features',
                        'description' => 'Our petty cash management module delivers complete financial control with structured workflows, approval processes, and comprehensive tracking capabilities for stress-free and efficient cash management.',
                        'subSections' => [
                            [
                                'title' => 'Fund Addition & Balance Calculation',
                                'description' => 'Easily add funds to the petty cash account while maintaining real-time calculations of total balance, expenses, and available cash. Ensure every transaction is accounted for with automatic balance updates and accurate record maintenance.',
                                'keyPoints' => ['Automatic balance updates', 'Maintain accurate records of added funds', 'Ensure proper financial management'],
                                'screenshot' => '/packages/workdo/PettyCashManagement/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Petty Cash Request & Approval',
                                'description' => 'Employees can request petty cash for business expenses. Requests go through an approval process, ensuring proper oversight and preventing unauthorized expenditures with streamlined workflow management.',
                                'keyPoints' => ['Streamlined request process', 'Approval-based workflow for transparency', 'Status tracking (pending, approved, rejected)'],
                                'screenshot' => '/packages/workdo/PettyCashManagement/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Category-Based Expense Management',
                                'description' => 'Expenses are categorized for better tracking and analysis. Assign expenses to predefined categories to monitor spending patterns effectively with organized tracking and customizable reporting.',
                                'keyPoints' => ['Organized expense tracking', 'Customizable categories for reporting', 'Easier financial auditing'],
                                'screenshot' => '/packages/workdo/PettyCashManagement/src/marketplace/image3.png'
                            ],
                            [
                                'title' => 'Reimbursement Management System',
                                'description' => 'Users can submit reimbursement requests with receipts for business-related expenses. Approvals ensure compliance with financial policies before completion with multi-step verification process.',
                                'keyPoints' => ['Upload receipts for verification', 'Multi-step approval system', 'Track status from request to completion'],
                                'screenshot' => '/packages/workdo/PettyCashManagement/src/marketplace/image4.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Petty Cash Management in Action',
                        'subtitle' => 'See how our petty cash management system streamlines your operations',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Petty Cash Management?',
                        'subtitle' => 'Improve efficiency with comprehensive petty cash management',
                        'benefits' => [
                            [
                                'title' => 'Fund Management',
                                'description' => 'Easily add funds with real-time balance calculations and accurate record keeping.',
                                'icon' => 'Wallet',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Request Approval Workflow',
                                'description' => 'Streamlined request process with approval-based workflow for transparency.',
                                'icon' => 'CheckSquare',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Category-Based Tracking',
                                'description' => 'Organize expenses with customizable categories for better analysis.',
                                'icon' => 'FolderOpen',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Reimbursement System',
                                'description' => 'Multi-step approval system with receipt verification and status tracking.',
                                'icon' => 'Receipt',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Financial Transparency',
                                'description' => 'Maintain order and transparency in day-to-day cash dealings.',
                                'icon' => 'Eye',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Centralized Platform',
                                'description' => 'Record and monitor all transactions from one unified platform.',
                                'icon' => 'Database',
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
