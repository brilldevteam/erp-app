<?php

namespace Workdo\Contract\Database\Seeders;

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
                    $screenshots[] = '/packages/workdo/Contract/src/marketplace/' . $file->getFilename();
                }
            }
        }
        
        sort($screenshots);
        
        MarketplaceSetting::firstOrCreate(['module' => 'Contract'], [
            'module' => 'Contract',
            'title' => 'Contract Add-On',
            'subtitle' => 'Complete contract management solution to create, manage, sign, and renew contracts',
            'config_sections' => [
                'sections' => [
                    'hero' => [
                        'variant' => 'hero1',
                        'title' => 'Contract Add-On for wazely.io',
                        'subtitle' => 'A complete contract management solution to create, manage, sign, and renew contracts. Supports custom types, attachments, comments, signatures, and contract duplication for smooth workflows.',
                        'primary_button_text' => 'Install Contract Add-On',
                        'primary_button_link' => '#install',
                        'secondary_button_text' => 'Learn More',
                        'secondary_button_link' => '#learn',
                        'image' => '/packages/workdo/Contract/src/marketplace/hero.png'
                    ],
                    'modules' => [
                        'variant' => 'modules1',
                        'title' => 'Contract Add-On',
                        'subtitle' => 'Create, manage, sign, and renew contracts with custom types, attachments, and secure signatures'
                    ],
                    'dedication' => [
                        'variant' => 'dedication1',
                        'title' => 'Contract Management Features',
                        'description' => 'The Contract Add-On streamlines the entire lifecycle of agreements — from creation and categorization to signing and renewal.',
                        'subSections' => [
                            [
                                'title' => 'Secure Contract Signing',
                                'description' => 'Finalize contracts with verified signatures from both involved parties. Whether it\'s the client or the business owner, the system captures and stores signatures, creating a clear audit trail. This promotes trust and speeds up agreement processes without external tools.',
                                'keyPoints' => ['Capture verified signatures from parties', 'Store signatures with audit trail', 'Speed up agreement processes', 'Eliminate need for external tools'],
                                'screenshot' => '/packages/workdo/Contract/src/marketplace/image1.png'
                            ],
                            [
                                'title' => 'Contract Lifecycle Management',
                                'description' => 'Manage every stage of a contract\'s journey—from creation and approval to execution, renewal, and completion. This system ensures transparency, accountability, and smooth coordination across teams. Easily duplicate existing contracts for similar use cases.',
                                'keyPoints' => ['Manage creation to completion stages', 'Duplicate contracts for similar cases', 'Track status updates and renewals', 'Ensure transparency and accountability'],
                                'screenshot' => '/packages/workdo/Contract/src/marketplace/image2.png'
                            ],
                            [
                                'title' => 'Complete Contract Solution',
                                'description' => 'The Contract Add-On enables teams to attach files, add comments, and manage contract types with ease. Digital signatures, contract duplication, and renewal tracking ensure seamless workflow and better collaboration.',
                                'keyPoints' => ['Streamline lifecycle from creation to renewal', 'Attach files and add comments', 'Manage contract types with ease', 'Enable better team collaboration'],
                                'screenshot' => '/packages/workdo/Contract/src/marketplace/image3.png'
                            ],
                             
                        ]
                    ],
                    'screenshots' => [
                        'variant' => 'screenshots1',
                        'title' => 'Contract Add-On in Action',
                        'subtitle' => 'See how the Contract management solution streamlines your agreement workflows',
                        'images' => $screenshots
                    ],
                    'why_choose' => [
                        'variant' => 'whychoose1',
                        'title' => 'Why Choose Contract Add-On?',
                        'subtitle' => 'Streamline contract management with secure signing and lifecycle tracking',
                        'benefits' => [
                            [
                                'title' => 'Secure Digital Signatures',
                                'description' => 'Both company and client can securely sign contracts with verified signatures.',
                                'icon' => 'PenTool',
                                'color' => 'blue'
                            ],
                            [
                                'title' => 'Complete Lifecycle Management',
                                'description' => 'Manage every stage from creation to renewal and completion.',
                                'icon' => 'RotateCcw',
                                'color' => 'green'
                            ],
                            [
                                'title' => 'Contract Duplication',
                                'description' => 'Easily duplicate existing contracts for similar use cases.',
                                'icon' => 'Copy',
                                'color' => 'purple'
                            ],
                            [
                                'title' => 'File Attachments',
                                'description' => 'Attach files and add comments to contracts with ease.',
                                'icon' => 'Paperclip',
                                'color' => 'red'
                            ],
                            [
                                'title' => 'Legal Compliance',
                                'description' => 'Ensure legal compliance and faster approvals with audit trails.',
                                'icon' => 'Shield',
                                'color' => 'yellow'
                            ],
                            [
                                'title' => 'Custom Contract Types',
                                'description' => 'Support for custom contract types and categorization.',
                                'icon' => 'FileText',
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