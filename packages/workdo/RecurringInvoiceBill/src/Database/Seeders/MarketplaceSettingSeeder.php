<?php

namespace Workdo\RecurringInvoiceBill\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/RecurringInvoiceBill/src/marketplace/' . $file->getFilename();
                }
            }
        }
        
        sort($screenshots);
        
        MarketplaceSetting::firstOrCreate(['module' => 'RecurringInvoiceBill'], [
            'module' => 'RecurringInvoiceBill',
            'title' => 'Recurring Invoice Purchase',
            'subtitle' => 'Automate sales and purchase invoice creation with flexible scheduling',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Recurring Invoice Purchase for wazely.io',
                        'subtitle' => 'Automate sales and purchase invoice creation with flexible scheduling and customizable intervals.',
                        'primary_button_text' => 'Install Recurring Invoice Purchase',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/RecurringInvoiceBill/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Recurring Invoice Purchase',
                        'subtitle' => 'Automate invoice creation with flexible scheduling and daily cron job integration'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Recurring Invoice Purchase',
                        'description' => 'Automate sales and purchase invoice creation with flexible scheduling, customizable intervals, and daily cron job integration.',
                        'subSections' => [
                            [
                                'title' => 'Recurring Invoice Automation',
                                'description' => 'The Recurring Invoice Purchase Add-On automates the creation of recurring sales and purchase invoices, reducing repetitive administrative tasks. After enabling it in both the Admin and Company panels and setting up a daily cron job, the system generates invoices at custom intervals. You can choose from various preset options like daily, weekly, or monthly, or set a unique schedule using the custom option. The cron job runs daily at midnight to duplicate invoices based on your defined intervals.',
                                'keyPoints' => [
                                    'Automates recurring sales and purchase invoice creation',
                                    'Reduces repetitive administrative tasks',
                                    'Enable in both Admin and Company panels',
                                    'Daily cron job runs at midnight for automatic generation',
                                    'Preset options: daily, weekly, monthly, or custom intervals',
                                    'Duplicates invoices based on defined intervals'
                                ],
                                'screenshot' => '/packages/workdo/RecurringInvoiceBill/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Dual Panel Configuration',
                                'description' => 'The add-on uses a two-level configuration system for full control, with settings in both the Admin and Company Panels. In the Admin Panel, you can enable the feature and set up the daily cron job for automatic invoice creation. The Company Panel allows you to activate the feature specifically for your company. Ensure you contact your Super Admin for proper cron job setup before using the feature.',
                                'keyPoints' => [
                                    'Two-level configuration system for full control',
                                    'Admin Panel: enable feature and set up daily cron job',
                                    'Company Panel: activate feature for specific company',
                                    'Daily cron job for automatic invoice creation',
                                    'Contact Super Admin for proper cron job setup',
                                    'Flexible control across admin and company levels'
                                ],
                                'screenshot' => '/packages/workdo/RecurringInvoiceBill/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Recurring Invoice Cycles',
                                'description' => 'When setting up a recurring invoice, you can specify how many times it should repeat by entering a value in the "Number of Cycles" field. For unlimited recurring invoices, toggle the "Unlimited Cycles" option to allow ongoing invoice generation. For example, setting "Every 1 Day" and "1" cycle generates a daily invoice. The cron job runs daily at midnight, duplicating invoices based on your chosen intervals.',
                                'keyPoints' => [
                                    'Specify number of cycles for invoice repetition',
                                    'Toggle "Unlimited Cycles" for ongoing generation',
                                    'Example: "Every 1 Day" with "1" cycle for daily invoices',
                                    'Cron job runs daily at midnight',
                                    'Duplicates invoices based on chosen intervals',
                                    'Flexible cycle configuration for different needs'
                                ],
                                'screenshot' => '/packages/workdo/RecurringInvoiceBill/src/marketplace/image3.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Recurring Invoice Purchase in Action',
                        'subtitle' => 'See how automated invoice creation reduces manual tasks',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Recurring Invoice Purchase?',
                        'subtitle' => 'Automate invoice creation with flexible scheduling and customizable intervals',
                        'benefits' => [
                            [
                                'title' => 'Automated Invoice Creation',
                                'description' => 'Reduce manual tasks with automated sales and purchase invoice generation.',
                                'icon' => 'Zap',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Flexible Scheduling',
                                'description' => 'Choose from daily, weekly, monthly, or custom intervals for invoice generation.',
                                'icon' => 'Calendar',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Daily Cron Job',
                                'description' => 'Automatic invoice duplication runs daily at midnight based on your intervals.',
                                'icon' => 'Clock',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Dual Panel Control',
                                'description' => 'Configure settings in both Admin and Company panels for full control.',
                                'icon' => 'Settings',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Unlimited Cycles',
                                'description' => 'Set specific cycle counts or enable unlimited recurring invoice generation.',
                                'icon' => 'RefreshCw',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Custom Intervals',
                                'description' => 'Define unique schedules with customizable interval options for any business need.',
                                'icon' => 'Sliders',
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