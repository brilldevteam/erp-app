<?php

namespace Workdo\AIAssistant\Database\Seeders;

use Illuminate\Database\Seeder;
use Workdo\LandingPage\Models\MarketplaceSetting;
use Illuminate\Support\Facades\File;

class MarketplaceSettingSeeder extends Seeder
{
    public function run()
    {
        // Get all available screenshots from marketplace directory
        $marketplaceDir = __DIR__ . '/../../marketplace';
        $screenshots    = [];

        if (File::exists($marketplaceDir)) {
            $files = File::files($marketplaceDir);
            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                    $screenshots[] = '/packages/workdo/AIAssistant/src/marketplace/' . $file->getFilename();
                }
            }
        }

        sort($screenshots);

        MarketplaceSetting::firstOrCreate(['module' => 'AIAssistant'], [
            'module'          => 'AIAssistant',
            'title'           => 'AI Assistant Add-on',
            'subtitle'        => 'AI-Powered Content Generator with Multilingual & Tonal Flexibility integrated across 16+ Add-Ons',
            'config_sections' => [
                'sections'           => [
                    'hero'        => [
                        'variant'               => 'hero1',
                        'title'                 => 'AI Assistant Add-on for wazely.io',
                        'subtitle'              => 'Effortlessly craft tailored content with adjustable character length, multiple result variations, and contextual understanding across all platform modules.',
                        'primary_button_text'   => 'Install AI Assistant Add-on',
                        'primary_button_link'   => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image'                 => '/packages/workdo/AIAssistant/src/marketplace/hero.png'
                    ],
                    'modules'     => [
                        'variant'  => 'modules1',
                        'title'    => 'AI Assistant Add-on',
                        'subtitle' => 'Comprehensive platform integrating multiple AI providers with seamless integration across 16+ major business add-ons'
                    ],
                    'dedication'  => [
                        'variant'     => 'dedication1',
                        'title'       => 'Comprehensive AI Assistant Features',
                        'description' => 'Supporting 15+ languages with advanced creativity controls and seamless integration across 16+ major business add-ons with context-aware content generation.',
                        'subSections' => [
                            [
                                'title'       => 'Multi-Provider AI Integration',
                                'description' => 'Support for multiple AI providers including OpenAI, Claude, Gemini, and Anthropic with dynamic model selection and secure API management.',
                                'keyPoints'   => ['Multiple AI provider support', 'Dynamic model selection', 'Secure API key management', 'Enterprise-grade reliability'],
                                'screenshot'  => '/packages/workdo/AIAssistant/src/marketplace/image1.png'
                            ],
                            [
                                'title'       => 'Advanced AI Content Generation',
                                'description' => 'Generate content in 15+ languages with creativity controls, batch generation, and character limit control for tailored content length.',
                                'keyPoints'   => ['Fifteen+ language options', 'Advanced creativity control', 'Batch generation with 1-5 variations', 'Real-time preview capabilities'],
                                'screenshot'  => '/packages/workdo/AIAssistant/src/marketplace/image2.png'
                            ],
                            [
                                'title'       => 'Universal Add-On Integration',
                                'description' => 'Seamlessly integrates across 16+ major business modules with context-aware AI generation and one-click content population.',
                                'keyPoints'   => ['Comprehensive integration across 16+ modules', 'Context-aware generation', 'One-click content population', 'Business process optimization'],
                                'screenshot'  => '/packages/workdo/AIAssistant/src/marketplace/image3.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant'  => 'screenshots1',
                        'title'    => 'AI Assistant in Action',
                        'subtitle' => 'See how AI-powered content generation streamlines communication across all business functions',
                        'images'   => $screenshots
                    ],
                    'why_choose'  => [
                        'variant'  => 'whychoose1',
                        'title'    => 'Why Choose AI Assistant?',
                        'subtitle' => 'Transform content creation with multilingual AI-powered generation and universal platform integration',
                        'benefits' => [
                            [
                                'title'       => 'Multi-Provider AI Support',
                                'description' => 'Access OpenAI, Claude, Gemini, and Anthropic with dynamic model selection.',
                                'icon'        => 'GitBranch',
                                'color'       => 'blue'
                            ],
                            [
                                'title'       => 'Multilingual Content',
                                'description' => 'Generate content in 15+ languages with comprehensive localization support.',
                                'icon'        => 'FileText',
                                'color'       => 'green'
                            ],
                            [
                                'title'       => 'Universal Integration',
                                'description' => 'Seamlessly integrated across 16+ major business modules with context awareness.',
                                'icon'        => 'Users',
                                'color'       => 'purple'
                            ],
                            [
                                'title'       => 'Advanced Customization',
                                'description' => 'Control creativity levels, character length, and generate multiple variations.',
                                'icon'        => 'CheckCircle',
                                'color'       => 'red'
                            ],
                            [
                                'title'       => 'One-Click Population',
                                'description' => 'Generate and populate content directly into forms with seamless integration.',
                                'icon'        => 'Play',
                                'color'       => 'yellow'
                            ],
                            [
                                'title'       => 'Real-time Preview',
                                'description' => 'Instant preview capabilities with contextual intelligence and brand consistency.',
                                'icon'        => 'Activity',
                                'color'       => 'indigo'
                            ]
                        ]
                    ]
                ],
                'section_visibility' => [
                    'header'      => true,
                    'hero'        => true,
                    'modules'     => true,
                    'dedication'  => true,
                    'screenshots' => true,
                    'why_choose'  => true,
                    'cta'         => true,
                    'footer'      => true
                ],
                'section_order'      => ['header', 'hero', 'modules', 'dedication', 'screenshots', 'why_choose', 'cta', 'footer']
            ]
        ]);
    }
}
