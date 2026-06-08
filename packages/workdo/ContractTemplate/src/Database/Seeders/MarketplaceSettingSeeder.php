<?php

namespace Workdo\ContractTemplate\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/ContractTemplate/src/marketplace/' . $file->getFilename();
                }
            }
        }
        
        sort($screenshots);
        
        MarketplaceSetting::firstOrCreate(['module' => 'ContractTemplate'], [
            'module' => 'ContractTemplate',
            'title' => 'Contract Template Add-On',
            'subtitle' => 'Create, store, and reuse standardized contract templates for consistent agreements',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Contract Template Add-On for WorkDo Dash',
                        'subtitle' => 'Contract templates allow businesses to create, store, and reuse standardized agreements. Users can save finalized contracts as templates and quickly generate new ones by adjusting them.',
                        'primary_button_text' => 'Install Contract Template Add-On',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/ContractTemplate/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Contract Template Add-On',
                        'subtitle' => 'Save finalized contracts as templates and quickly generate new ones for consistent agreements'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Contract Template Features',
                        'description' => 'This streamlines contract creation, ensuring consistency and efficiency in managing agreements. Note: This Add-On depends on the Contract Add-On.',
                        'subSections' => [
                            [
                                'title' => 'Save as Contract Template',
                                'description' => 'The "Save as Template" feature allows you to create a reusable contract template from any contract you draft. Once finalized, you can save the document as a template that can be used again for future contracts, eliminating the need to start from scratch.',
                                'keyPoints' => ['Save finalized contracts as templates', 'Reuse templates for future contracts', 'Eliminate starting from scratch', 'Ensure consistency in all agreements'],
                                'screenshot' => '/packages/workdo/ContractTemplate/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Contract Template List & Conversion',
                                'description' => 'With the contract template list, users can view all saved templates and quickly convert them into active contracts. This feature offers an organized view of all available templates, enabling you to select the most relevant one and make necessary adjustments before finalizing.',
                                'keyPoints' => ['View all saved templates', 'Convert templates into active contracts', 'Make adjustments before finalizing', 'Reduce administrative workload'],
                                'screenshot' => '/packages/workdo/ContractTemplate/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Easy Template Management',
                                'description' => 'The contract template management system provides an intuitive way to organize and handle templates. Users can add descriptions, attach relevant files, and include comments or notes for clarity. Additionally, templates can be previewed, downloaded, duplicated, or modified as needed.',
                                'keyPoints' => ['Add descriptions and attach files', 'Include comments for clarity', 'Preview and download templates', 'Duplicate or modify templates easily'],
                                'screenshot' => '/packages/workdo/ContractTemplate/src/marketplace/image3.png'
                            ]
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Contract Template Add-On in Action',
                        'subtitle' => 'See how contract templates streamline your agreement creation process',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Contract Template Add-On?',
                        'subtitle' => 'Streamline contract creation with reusable templates and consistent agreements',
                        'benefits' => [
                            [
                                'title' => 'Reusable Templates',
                                'description' => 'Save finalized contracts as templates for future use and consistency.',
                                'icon' => 'Copy',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Quick Contract Generation',
                                'description' => 'Convert templates into active contracts with necessary adjustments.',
                                'icon' => 'Zap',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Template Organization',
                                'description' => 'View and manage all saved templates in an organized list.',
                                'icon' => 'FolderOpen',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'File Attachments',
                                'description' => 'Add descriptions, attach files, and include comments for clarity.',
                                'icon' => 'Paperclip',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Template Preview',
                                'description' => 'Preview, download, duplicate, or modify templates as needed.',
                                'icon' => 'Eye',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Workflow Efficiency',
                                'description' => 'Eliminate starting from scratch and reduce administrative workload.',
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