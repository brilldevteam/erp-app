<?php

namespace Workdo\VideoProduction\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Workdo\LandingPage\Models\MarketplaceSetting;

class MarketplaceSettingSeeder extends Seeder
{
    public function run()
    {
        // Get all available screenshots from marketplace directory
        $marketplaceDir = __DIR__.'/../../marketplace';
        $screenshots = [];

        if (File::exists($marketplaceDir)) {
            $files = File::files($marketplaceDir);
            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                    $screenshots[] = '/packages/workdo/VideoProduction/src/marketplace/'.$file->getFilename();
                }
            }
        }

        sort($screenshots);

        MarketplaceSetting::firstOrCreate(['module' => 'VideoProduction'], [
            'module' => 'VideoProduction',
            'title' => 'VideoProduction Module Marketplace',
            'subtitle' => 'Comprehensive videoproduction tools for your applications',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'VideoProduction Module for wazely.io',
                        'subtitle' => 'Streamline your videoproduction workflow with comprehensive tools and automated management.',
                        'primary_button_text' => 'Install VideoProduction Module',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '',
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'VideoProduction Module',
                        'subtitle' => 'Enhance your workflow with powerful videoproduction tools',
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Dedicated VideoProduction Features',
                        'description' => 'Our videoproduction module provides comprehensive capabilities for modern workflows.',
                        'subSections' => [
                            [
                                'title' => 'Feature 1',
                                'description' => 'Description of first key feature for videoproduction management.',
                                'keyPoints' => ['Point 1', 'Point 2', 'Point 3', 'Point 4'],
                                'screenshot' => '/packages/workdo/VideoProduction/src/marketplace/image1.png',
                            ],
                            [
                                'title' => 'Feature 2',
                                'description' => 'Description of second key feature for videoproduction organization.',
                                'keyPoints' => ['Point 1', 'Point 2', 'Point 3', 'Point 4'],
                                'screenshot' => '/packages/workdo/VideoProduction/src/marketplace/image2.png',
                            ],
                            [
                                'title' => 'Feature 3',
                                'description' => 'Description of third key feature for videoproduction tracking.',
                                'keyPoints' => ['Point 1', 'Point 2', 'Point 3', 'Point 4'],
                                'screenshot' => '/packages/workdo/VideoProduction/src/marketplace/image3.png',
                            ],
                        ],
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'VideoProduction Module in Action',
                        'subtitle' => 'See how our videoproduction tools improve your workflow',
                        'images' => $screenshots,
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose VideoProduction Module?',
                        'subtitle' => 'Improve efficiency with comprehensive videoproduction management',
                        'benefits' => [
                            [
                                'title' => 'Automated Process',
                                'description' => 'Automate your videoproduction workflow to save time and reduce errors.',
                                'icon' => 'Play',
                                'color' => 'blue',
                            ],
                            [
                                'title' => 'Comprehensive Reports',
                                'description' => 'Get detailed reports with metrics and performance data.',
                                'icon' => 'FileText',
                                'color' => 'green',
                            ],
                            [
                                'title' => 'Team Collaboration',
                                'description' => 'Share results and collaborate effectively with your team.',
                                'icon' => 'Users',
                                'color' => 'purple',
                            ],
                            [
                                'title' => 'Easy Integration',
                                'description' => 'Seamlessly integrate with your existing workflow.',
                                'icon' => 'GitBranch',
                                'color' => 'red',
                            ],
                            [
                                'title' => 'Quality Management',
                                'description' => 'Maintain high quality with comprehensive management tools.',
                                'icon' => 'CheckCircle',
                                'color' => 'yellow',
                            ],
                            [
                                'title' => 'Performance Tracking',
                                'description' => 'Track performance and identify improvements early.',
                                'icon' => 'Activity',
                                'color' => 'indigo',
                            ],
                        ],
                    ],
                ],
                'section_visibility' => [
                    'header' => true,
                    'hero' => true,
                    'modules' => true,
                    'dedication' => true,
                    'screenshots' => true,
                    'why_choose' => true,
                    'cta' => true,
                    'footer' => true,
                ],
                'section_order' => ['header', 'hero', 'modules', 'dedication', 'screenshots', 'why_choose', 'cta', 'footer'],
            ],
        ]);
    }
}
