<?php

namespace Workdo\ContractTemplate\Database\Seeders;

use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractAttachment;
use Workdo\Contract\Models\ContractComment;
use Workdo\Contract\Models\ContractNote;
use Illuminate\Database\Seeder;
use Workdo\Contract\Models\ContractType;
use App\Models\User;
use Carbon\Carbon;

class DemoContractTemplateSeeder extends Seeder
{
    public function run($userId): void
    {
        if (Contract::where('created_by', $userId)->where('source_type', 'template')->exists()) {
            return;
        }

            setSetting('contract_prefix', 'CON', $userId, false);

        $templateData = [
            [
                'subject' => 'Software Development Agreement Template',
                'description' => 'Standard template for software development projects including scope definition, deliverables, timelines, and payment terms.',
                'value' => 150000.00,
                'status' => 'active',
                'duration_months' => 6
            ],
            [
                'subject' => 'Consulting Services Contract Template',
                'description' => 'Professional consulting services template covering advisory services, expertise delivery, and knowledge transfer agreements.',
                'value' => 75000.00,
                'status' => 'active',
                'duration_months' => 3
            ],
            [
                'subject' => 'Maintenance & Support Agreement Template',
                'description' => 'Ongoing maintenance and technical support services template with SLA definitions and response time commitments.',
                'value' => 45000.00,
                'status' => 'active',
                'duration_months' => 12
            ],
            [
                'subject' => 'Cloud Services Agreement Template',
                'description' => 'Cloud infrastructure and services template including hosting, backup, security, and scalability provisions.',
                'value' => 120000.00,
                'status' => 'active',
                'duration_months' => 6
            ],
            [
                'subject' => 'Digital Marketing Services Template',
                'description' => 'Comprehensive digital marketing services template covering SEO, social media, content creation, and analytics.',
                'value' => 65000.00,
                'status' => 'draft',
                'duration_months' => 6
            ],
            [
                'subject' => 'Training & Education Services Template',
                'description' => 'Professional training and education services template for corporate learning and skill development programs.',
                'value' => 35000.00,
                'status' => 'draft',
                'duration_months' => 3
            ],
            [
                'subject' => 'Data Analytics & BI Services Template',
                'description' => 'Business intelligence and data analytics services template including reporting, dashboards, and insights delivery.',
                'value' => 95000.00,
                'status' => 'active',
                'duration_months' => 5
            ],
            [
                'subject' => 'Cybersecurity Services Agreement Template',
                'description' => 'Comprehensive cybersecurity services template covering assessment, implementation, monitoring, and compliance.',
                'value' => 110000.00,
                'status' => 'active',
                'duration_months' => 3
            ],
            [
                'subject' => 'Web Development & Design Template',
                'description' => 'Complete web development and design services template including UI/UX design, frontend/backend development, and deployment.',
                'value' => 85000.00,
                'status' => 'active',
                'duration_months' => 5
            ],
            [
                'subject' => 'Mobile App Development Template',
                'description' => 'Mobile application development template for iOS and Android platforms including design, development, testing, and deployment.',
                'value' => 135000.00,
                'status' => 'draft',
                'duration_months' => 6
            ],
            [
                'subject' => 'E-commerce Platform Template',
                'description' => 'E-commerce platform development template including shopping cart, payment integration, inventory management, and order processing.',
                'value' => 175000.00,
                'status' => 'active',
                'duration_months' => 9
            ],
            [
                'subject' => 'API Development & Integration Template',
                'description' => 'RESTful API development and third-party integration services template including documentation, testing, and deployment.',
                'value' => 95000.00,
                'status' => 'draft',
                'duration_months' => 4
            ],
            [
                'subject' => 'Database Design & Optimization Template',
                'description' => 'Database architecture, design, and optimization services template including performance tuning and data migration.',
                'value' => 65000.00,
                'status' => 'active',
                'duration_months' => 2
            ],
            [
                'subject' => 'DevOps & Infrastructure Template',
                'description' => 'DevOps services and infrastructure management template including CI/CD, monitoring, and cloud infrastructure setup.',
                'value' => 105000.00,
                'status' => 'active',
                'duration_months' => 4
            ],
            [
                'subject' => 'Quality Assurance & Testing Template',
                'description' => 'Software testing and quality assurance services template including manual testing, automation, and performance testing.',
                'value' => 55000.00,
                'status' => 'draft',
                'duration_months' => 3
            ]
        ];

        $users = User::where('created_by', $userId)
            ->whereIn('type', ['staff', 'client'])
            ->get();
        $contractTypes = ContractType::where('created_by', $userId)->get();

        foreach ($templateData as $index => $data) {
            $createdAt = Carbon::now()->subDays(90 - ($index * 6));
            $startDate = $createdAt->copy()->addDays(rand(1, 30));
            $endDate = $startDate->copy()->addMonths(rand(6, 24));
            $template = Contract::create([
                'subject' => $data['subject'],
                'user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                'value' => $data['value'],
                'type_id' => $contractTypes->isNotEmpty() ? $contractTypes->random()->id : null,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'description' => $data['description'],
                'status' => $data['status'],
                'source_type' => 'template',
                'creator_id' => $userId,
                'created_by' => $userId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->createAttachments($template, $users);
            $this->createComments($template, $users);
            $this->createNotes($template, $users);
        }
    }

    private function createAttachments($template, $users)
    {
        $attachmentTypes = [
            ['name' => 'budget_breakdown.pdf', 'path' => 'budget_breakdown.pdf'],
            ['name' => 'contract_agreement_signed.pdf', 'path' => 'contract_agreement_signed.pdf'],
            ['name' => 'deployment_guide.pdf', 'path' => 'deployment_guide.pdf'],
            ['name' => 'project_timeline.xlsx', 'path' => 'project_timeline.xlsx'],
            ['name' => 'requirements_document.pdf', 'path' => 'requirements_document.pdf'],
            ['name' => 'security_compliance_report.pdf', 'path' => 'security_compliance_report.pdf'],
            ['name' => 'technical_specifications.docx', 'path' => 'technical_specifications.docx'],
            ['name' => 'testing_plan.docx', 'path' => 'testing_plan.docx'],
            ['name' => 'user_manual.pdf', 'path' => 'user_manual.pdf']
        ];

        $attachmentCount = fake()->numberBetween(1, 3);
        for ($i = 0; $i < $attachmentCount; $i++) {
            $attachment = fake()->randomElement($attachmentTypes);
            ContractAttachment::create([
                'contract_id' => $template->id,
                'file_name' => $attachment['name'],
                'file_path' => $attachment['path'],
                'uploaded_by' => $users->random()->id,
                'creator_id' => $template->creator_id,
                'created_by' => $template->created_by,
                'created_at' => fake()->dateTimeBetween($template->created_at, 'now'),
            ]);
        }
    }

    private function createComments($template, $users)
    {
        $comments = [
            'Template structure reviewed and approved by legal team.',
            'Standard clauses updated to reflect current industry practices.',
            'Payment terms section revised for better clarity.',
            'Intellectual property clauses strengthened.',
            'Termination conditions clearly defined and documented.',
            'Service level agreements aligned with industry standards.',
            'Risk mitigation strategies incorporated into template.',
            'Compliance requirements updated for latest regulations.',
            'Template formatting improved for better readability.',
            'Stakeholder feedback incorporated into final version.',
            'Version control system implemented for template management.',
            'Template testing completed with sample scenarios.',
            'Documentation updated with usage guidelines.',
            'Template approved for production use.',
            'Training materials prepared for template usage.'
        ];

        $commentCount = fake()->numberBetween(5, 10);
        for ($i = 0; $i < $commentCount; $i++) {
            ContractComment::create([
                'contract_id' => $template->id,
                'comment' => fake()->randomElement($comments),
                'user_id' => $users->random()->id,
                'is_edited' => fake()->boolean(15),
                'creator_id' => $template->creator_id,
                'created_by' => $template->created_by,
                'created_at' => fake()->dateTimeBetween($template->created_at, 'now'),
            ]);
        }
    }

    private function createNotes($template, $users)
    {
        $notes = [
            'Template designed for reusability across multiple similar projects.',
            'Customizable sections marked for client-specific modifications.',
            'Legal review required before using template for high-value contracts.',
            'Template includes optional clauses for different service types.',
            'Regular updates scheduled quarterly to maintain relevance.',
            'Template usage tracking implemented for optimization insights.',
            'Backup versions maintained for rollback capabilities.',
            'Template integration with CRM system completed successfully.',
            'User access controls configured for template management.',
            'Template performance metrics established for continuous improvement.'
        ];

        $noteCount = fake()->numberBetween(3, 7);
        for ($i = 0; $i < $noteCount; $i++) {
            ContractNote::create([
                'contract_id' => $template->id,
                'note' => fake()->randomElement($notes),
                'user_id' => $users->random()->id,
                'is_edited' => fake()->boolean(10),
                'creator_id' => $template->creator_id,
                'created_by' => $template->created_by,
                'created_at' => fake()->dateTimeBetween($template->created_at, 'now'),
            ]);
        }
    }
}
