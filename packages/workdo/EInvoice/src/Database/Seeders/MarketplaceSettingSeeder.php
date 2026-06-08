<?php

namespace Workdo\EInvoice\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/EInvoice/src/marketplace/' . $file->getFilename();
                }
            }
        }

        sort($screenshots);

        MarketplaceSetting::firstOrCreate(['module' => 'EInvoice'], [
            'module' => 'EInvoice',
            'title' => 'E-Invoice Module Marketplace',
            'subtitle' => 'Comprehensive e-invoice tools for your applications',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'E-Invoice Module for WorkDo Dash',
                        'subtitle' => 'Streamline your e-invoice workflow with comprehensive tools and automated management.',
                        'primary_button_text' => 'Install E-Invoice Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/EInvoice/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'E-Invoice Module',
                        'subtitle' => 'Enhance your workflow with powerful e-invoice tools'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Dedicated E-Invoice Features',
                        'description' => 'Our e-invoice module provides comprehensive capabilities for modern workflows.',
                        'subSections' => [
                            [
                                'title' => 'Electronic Invoice Download',
                                'description' => 'Download invoices in electronic format with complete customer and product details. Includes tax calculations, electronic addresses, and company settings for full compliance with e-invoice standards.',
                                'keyPoints' => ['Electronic format download', 'Customer electronic addresses', 'Tax calculation integration', 'Company schema configuration'],
                                'screenshot' => '/packages/workdo/EInvoice/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Company Settings Management',
                                'description' => 'Configure electronic address schemas, company IDs, and tax settings through an intuitive interface. Manage all e-invoice related company settings with proper permission controls and validation.',
                                'keyPoints' => ['Electronic address configuration', 'Company ID schema setup', 'Permission-based access', 'Settings validation'],
                                'screenshot' => '/packages/workdo/EInvoice/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Customer Electronic Integration',
                                'description' => 'Seamlessly integrate customer electronic addresses and schemes for compliant invoice generation. Automatic validation ensures all required electronic fields are properly configured before invoice processing.',
                                'keyPoints' => ['Customer electronic addresses', 'Address scheme validation', 'Integration with sales invoices', 'Compliance checking'],
                                'screenshot' => '/packages/workdo/EInvoice/src/marketplace/image3.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'E-Invoice Module in Action',
                        'subtitle' => 'See how our e-invoice tools improve your workflow',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose E-Invoice Module?',
                        'subtitle' => 'Improve efficiency with comprehensive e-invoice management',
                        'benefits' => [
                            [
                                'title' => 'Electronic Compliance',
                                'description' => 'Ensure full compliance with electronic invoice standards and regulations.',
                                'icon' => 'FileText',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Settings Management',
                                'description' => 'Centralized configuration for all e-invoice related settings.',
                                'icon' => 'Settings',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Customer Integration',
                                'description' => 'Seamless integration with customer electronic address data.',
                                'icon' => 'Users',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Permission Control',
                                'description' => 'Role-based access control for e-invoice management.',
                                'icon' => 'Shield',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Tax Integration',
                                'description' => 'Automatic tax calculation and validation for invoices.',
                                'icon' => 'Calculator',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Data Validation',
                                'description' => 'Comprehensive validation of electronic invoice data.',
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
