<?php

namespace Workdo\Assets\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/Assets/src/marketplace/' . $file->getFilename();
                }
            }
        }

        sort($screenshots);

        MarketplaceSetting::firstOrCreate(['module' => 'Assets'], [
            'module' => 'Assets',
            'title' => 'Assets Module Marketplace',
            'subtitle' => 'Comprehensive assets tools for your applications',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Assets Module for WorkDo Dash',
                        'subtitle' => 'Streamline your assets workflow with comprehensive tools and automated management.',
                        'primary_button_text' => 'Install Assets Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/Assets/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Assets Module',
                        'subtitle' => 'Enhance your workflow with powerful assets tools'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Dedicated Assets Features',
                        'description' => 'Our assets module provides comprehensive capabilities for modern workflows.',
                        'subSections' => [
                            [
                                'title' => 'Asset Management & Tracking',
                                'description' => 'Comprehensive asset lifecycle management from acquisition to disposal with detailed tracking capabilities. Organize assets by categories, locations, and status while maintaining complete records of ownership, condition, and usage history.',
                                'keyPoints' => ['Complete asset lifecycle tracking', 'Category-based organization', 'Location and status management', 'Detailed asset history records'],
                                'screenshot' => '/packages/workdo/Assets/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Asset Assignment & Return System',
                                'description' => 'Streamlined asset assignment process with employee tracking and automated return management. Monitor asset utilization, track assignments to specific employees, and manage the complete assignment lifecycle with return functionality.',
                                'keyPoints' => ['Employee asset assignment', 'Assignment tracking system', 'Automated return process', 'Asset utilization monitoring'],
                                'screenshot' => '/packages/workdo/Assets/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Maintenance & Depreciation Management',
                                'description' => 'Proactive maintenance scheduling with automated depreciation calculations for accurate asset valuation. Schedule preventive maintenance, track repair history, and calculate asset depreciation using various methods for financial reporting.',
                                'keyPoints' => ['Preventive maintenance scheduling', 'Repair history tracking', 'Automated depreciation calculation', 'Financial reporting integration'],
                                'screenshot' => '/packages/workdo/Assets/src/marketplace/image3.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Assets Module in Action',
                        'subtitle' => 'See how our assets tools improve your workflow',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Assets Module?',
                        'subtitle' => 'Improve efficiency with comprehensive assets management',
                        'benefits' => [
                            [
                                'title' => 'Complete Asset Tracking',
                                'description' => 'Track all your assets from acquisition to disposal with detailed lifecycle management and real-time status updates.',
                                'icon' => 'Package',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Automated Depreciation',
                                'description' => 'Calculate asset depreciation automatically using multiple methods for accurate financial reporting and valuation.',
                                'icon' => 'TrendingDown',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Maintenance Scheduling',
                                'description' => 'Schedule preventive maintenance, track repairs, and maintain asset health with automated reminders and notifications.',
                                'icon' => 'Settings',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'Employee Assignment',
                                'description' => 'Assign assets to employees, track utilization, and manage returns with a streamlined assignment system.',
                                'icon' => 'UserCheck',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Detailed Reporting',
                                'description' => 'Generate comprehensive reports on asset value, depreciation, maintenance costs, and utilization metrics.',
                                'icon' => 'BarChart',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Multi-Location Support',
                                'description' => 'Manage assets across multiple locations with centralized tracking and location-based organization.',
                                'icon' => 'MapPin',
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
