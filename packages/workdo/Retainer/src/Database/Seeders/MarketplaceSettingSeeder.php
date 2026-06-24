<?php

namespace Workdo\Retainer\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/Retainer/src/marketplace/' . $file->getFilename();
                }
            }
        }
        
        sort($screenshots);
        
        MarketplaceSetting::firstOrCreate(['module' => 'Retainer'], [
            'module' => 'Retainer',
            'title' => 'Retainer Module Marketplace',
            'subtitle' => 'Comprehensive retainer tools for your applications',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Retainer Module for wazely.io',
                        'subtitle' => 'Streamline your retainer workflow with comprehensive tools and automated management.',
                        'primary_button_text' => 'Install Retainer Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/Retainer/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Retainer Module',
                        'subtitle' => 'Enhance your workflow with powerful retainer tools'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Dedicated Retainer Features',
                        'description' => 'Our retainer module provides comprehensive capabilities for modern workflows.',
                        'subSections' => [
                            [
                                'title' => 'Retainer Management',
                                'description' => 'The Retainer Management system allows businesses to efficiently handle agreements with their customers. Retainers can be created by selecting a customer, assigning dates, choosing a warehouse, and adding products or services along with prices, quantities, discounts, and taxes. Each retainer is automatically numbered and can include multiple items.',
                                'keyPoints' =>[],
                                'screenshot' => '/packages/workdo/Retainer/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Retainer Overview & Controls',
                                'description' => 'The Retainer Overview & Controls section provides complete insight into each retainer. It displays all key details including customer information, retainer dates, status, and the items included with their quantities, unit prices, discounts, and taxes. The section also shows the overall financial summary with subtotal, tax, total amount, and balance, as well as any payment allocations, giving you full visibility and control over retainer management.',
                                'keyPoints' => [],
                                'screenshot' => '/packages/workdo/Retainer/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Retainer Payment',
                                'description' => 'The Retainer Payment section allows you to manage payments made against retainers efficiently. You can record partial or full payments, select the customer and associated retainer, and allocate amounts to specific line items. The system tracks the payment status, updates the retainer status to Partial or Paid accordingly, and provides a clear summary of all payments and allocations. Once payments are completed, retainers can be converted into sales invoices for seamless billing and accounting.',
                                'keyPoints' =>[],
                                'screenshot' => '/packages/workdo/Retainer/src/marketplace/image3.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Retainer Module in Action',
                        'subtitle' => 'See how our retainer tools improve your workflow',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Retainer Module?',
                        'subtitle' => 'Improve efficiency with comprehensive retainer management',
                        'benefits' => [
                            [
                                'title' => 'Complete Management',
                                'description' => 'Handle retainer agreements with automated numbering and multi-item support.',
                                'icon' => 'FileText',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Full Visibility',
                                'description' => 'Complete overview with financial summaries and payment tracking.',
                                'icon' => 'Eye',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Payment Processing',
                                'description' => 'Manage payments with invoice conversion and status tracking.',
                                'icon' => 'CreditCard',
                                'color' => 'purple'
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