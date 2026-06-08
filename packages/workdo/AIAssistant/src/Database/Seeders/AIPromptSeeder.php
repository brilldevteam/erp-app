<?php

namespace Workdo\AIAssistant\Database\Seeders;

use Illuminate\Database\Seeder;
use Workdo\AIAssistant\Models\AIPrompt;

class AIPromptSeeder extends Seeder
{
    public function run()
    {
        $prompts = [
            // general
            [
                'module'          => 'general',
                'submodule'       => null,
                'field_type'      => 'title',
                'prompt_template' => 'Generate a professional and engaging title for the given context: {context}',
                'description'     => 'Generic title generation for any module'
            ],
            [
                'module'          => 'general',
                'submodule'       => null,
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed and comprehensive description for: {context}. Include key features and benefits.',
                'description'     => 'Generic description generation for any module'
            ],
            [
                'module'          => 'general',
                'submodule'       => 'warehouses',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional warehouse name for a facility located in {city}. Make it descriptive and business-appropriate.',
                'description'     => 'Warehouse name generation'
            ],

            // CMMS
            [
                'module'          => 'cmms',
                'submodule'       => 'workorder',
                'field_type'      => 'workorder_name',
                'prompt_template' => 'Generate a professional work order name for maintenance tasks. Make it clear, specific and action-oriented.',
                'description'     => 'Work order name generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'workorder',
                'field_type'      => 'instructions',
                'prompt_template' => 'Write detailed maintenance instructions. Include step-by-step procedures, safety requirements, and expected outcomes.',
                'description'     => 'Work order instructions generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'workorder_logtime',
                'field_type'      => 'description',
                'prompt_template' => 'Create a detailed log time description for maintenance work. Include tasks completed and progress made.',
                'description'     => 'Work order log time description generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'workorder_invoice',
                'field_type'      => 'description',
                'prompt_template' => 'Generate a detailed invoice description for maintenance work. Include services provided and materials used.',
                'description'     => 'Work order invoice description generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'component',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional component name for industrial equipment. Make it descriptive and industry-standard.',
                'description'     => 'Component name generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'component',
                'field_type'      => 'sku',
                'prompt_template' => 'Generate a unique SKU code for industrial components. Use alphanumeric format with letters and numbers.',
                'description'     => 'Component SKU generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'component',
                'field_type'      => 'category',
                'prompt_template' => 'Generate an appropriate category name for industrial components. Make it industry-standard and descriptive.',
                'description'     => 'Component category generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'component',
                'field_type'      => 'component_tag',
                'prompt_template' => 'Generate relevant tags for industrial components. Include maintenance type and equipment classification.',
                'description'     => 'Component tag generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'component',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive description for industrial components. Include specifications, maintenance requirements, and operational details.',
                'description'     => 'Component description generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'component_logtime',
                'field_type'      => 'description',
                'prompt_template' => 'Create a detailed log time description for {hours} hours and {minutes} minutes of maintenance work on component. Include tasks performed and component status.',
                'description'     => 'Component log time description generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'preventive_maintenance',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive preventive maintenance description. Include maintenance procedures, safety protocols, and expected outcomes.',
                'description'     => 'Preventive maintenance description generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'preventive_maintenance_logtime',
                'field_type'      => 'description',
                'prompt_template' => 'Create a detailed log time description for {hours} hours and {minutes} minutes of preventive maintenance work. Include maintenance tasks performed and system status.',
                'description'     => 'Preventive maintenance log time description generation'
            ],
            [
                'module'          => 'cmms',
                'submodule'       => 'preventive_maintenance_invoice',
                'field_type'      => 'description',
                'prompt_template' => 'Generate a detailed invoice description for preventive maintenance work. Include maintenance services provided and materials used.',
                'description'     => 'Preventive maintenance invoice description generation'
            ],

            // Contract
            [
                'module'          => 'contract',
                'submodule'       => 'contract',
                'field_type'      => 'subject',
                'prompt_template' => 'Generate a professional contract subject line. Make it clear, specific and business-appropriate.',
                'description'     => 'Contract subject generation'
            ],

            // Lead
            [
                'module'          => 'lead',
                'submodule'       => 'lead',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional lead name for a potential customer or client. Make it realistic and business-appropriate.',
                'description'     => 'Lead name generation'
            ],
            [
                'module'          => 'lead',
                'submodule'       => 'lead',
                'field_type'      => 'subject',
                'prompt_template' => 'Generate a compelling lead subject line that captures interest and describes the business opportunity.',
                'description'     => 'Lead subject generation'
            ],
            [
                'module'          => 'lead',
                'submodule'       => 'lead',
                'field_type'      => 'notes',
                'prompt_template' => 'Write comprehensive lead notes including contact details, conversation summary, follow-up actions, and potential opportunities.',
                'description'     => 'Lead notes generation'
            ],
            [
                'module'          => 'lead',
                'submodule'       => 'lead_email',
                'field_type'      => 'subject',
                'prompt_template' => 'Generate a compelling email subject line for lead communication. Make it engaging and relevant to business opportunities.',
                'description'     => 'Lead email subject generation'
            ],
            [
                'module'          => 'lead',
                'submodule'       => 'lead_email',
                'field_type'      => 'description',
                'prompt_template' => 'Write a professional email content for lead communication. Include introduction, value proposition, and clear call-to-action.',
                'description'     => 'Lead email description generation'
            ],
            [
                'module'          => 'lead',
                'submodule'       => 'deal',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and compelling deal name that reflects the business opportunity and value proposition.',
                'description'     => 'Deal name generation'
            ],
            [
                'module'          => 'lead',
                'submodule'       => 'deal',
                'field_type'      => 'notes',
                'prompt_template' => 'Write comprehensive deal notes including opportunity details, client requirements, competitive analysis, timeline, and next steps.',
                'description'     => 'Deal notes generation'
            ],
            [
                'module'          => 'lead',
                'submodule'       => 'deal_email',
                'field_type'      => 'subject',
                'prompt_template' => 'Generate a compelling email subject line for deal communication. Make it engaging and relevant to the business opportunity.',
                'description'     => 'Deal email subject generation'
            ],
            [
                'module'          => 'lead',
                'submodule'       => 'deal_email',
                'field_type'      => 'description',
                'prompt_template' => 'Write a professional email content for deal communication. Include value proposition, deal benefits, and clear call-to-action.',
                'description'     => 'Deal email description generation'
            ],

            // ProductService
            [
                'module'          => 'productservice',
                'submodule'       => 'item',
                'field_type'      => 'description',
                'prompt_template' => 'Write a compelling short description for a product/service item. Make it concise, informative, and highlight key features and benefits.',
                'description'     => 'Item short description generation'
            ],

            // Recruitment
            [
                'module'          => 'recruitment',
                'submodule'       => 'job_posting',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a compelling and professional job title that accurately reflects the role and attracts qualified candidates.',
                'description'     => 'Job posting title generation'
            ],
            [
                'module'          => 'recruitment',
                'submodule'       => 'job_posting',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive job description that includes role overview, key responsibilities, and what makes this position attractive to candidates.',
                'description'     => 'Job posting description generation'
            ],
            [
                'module'          => 'recruitment',
                'submodule'       => 'job_posting',
                'field_type'      => 'requirements',
                'prompt_template' => 'Create detailed job requirements including education, experience, skills, and qualifications needed for the position.',
                'description'     => 'Job posting requirements generation'
            ],
            [
                'module'          => 'recruitment',
                'submodule'       => 'job_posting',
                'field_type'      => 'benefits',
                'prompt_template' => 'Write attractive employee benefits and perks that highlight what the company offers to employees in this role.',
                'description'     => 'Job posting benefits generation'
            ],
            [
                'module'          => 'recruitment',
                'submodule'       => 'job_posting',
                'field_type'      => 'terms_condition',
                'prompt_template' => 'Generate professional terms and conditions for job applications including employment terms, policies, and legal requirements.',
                'description'     => 'Job posting terms and conditions generation'
            ],
            [
                'module'          => 'recruitment',
                'submodule'       => 'offer_letter_template',
                'field_type'      => 'template',
                'prompt_template' => 'Generate a professional offer letter template including company introduction, position details, compensation, benefits, and terms. Use placeholders: {applicant_name}, {company_name}, {job_title}, {salary}, {start_date}, {workplace_location}, {days_of_week}, {salary_type}, {salary_duration}, {offer_expiration_date}.',
                'description'     => 'Offer letter template generation'
            ],

            // Sales
            [
                'module'          => 'sales',
                'submodule'       => 'account',
                'field_type'      => 'description',
                'prompt_template' => 'Write a professional account description including company overview, business activities, key services, and relevant business information.',
                'description'     => 'Account description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'call',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and descriptive sales call name that clearly indicates the purpose and context of the call.',
                'description'     => 'Sales call name generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'call',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive sales call description including call objectives, key discussion points, expected outcomes, and follow-up actions.',
                'description'     => 'Sales call description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'case',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and descriptive sales case name that summarizes the issue or request being addressed.',
                'description'     => 'Sales case name generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'case',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed sales case description including the issue details, customer impact, resolution steps, and expected timeline.',
                'description'     => 'Sales case description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'contact',
                'field_type'      => 'description',
                'prompt_template' => 'Write a professional contact description including role, responsibilities, key contact information, and relationship details.',
                'description'     => 'Sales contact description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'document',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and descriptive document name that clearly indicates the document type and purpose.',
                'description'     => 'Sales document name generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'document',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive document description including purpose, content overview, usage guidelines, and relevant details.',
                'description'     => 'Sales document description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'meeting',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and descriptive meeting name that clearly indicates the meeting purpose and context.',
                'description'     => 'Sales meeting name generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'meeting',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive meeting description including objectives, agenda items, expected outcomes, and preparation requirements.',
                'description'     => 'Sales meeting description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'opportunity',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a compelling and professional opportunity name that reflects the business potential and value proposition.',
                'description'     => 'Sales opportunity name generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'opportunity',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed opportunity description including business requirements, potential value, timeline, key stakeholders, and success criteria.',
                'description'     => 'Sales opportunity description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'quote',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and descriptive quote name that clearly identifies the proposal and its purpose.',
                'description'     => 'Sales quote name generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'quote',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive quote description including proposal overview, deliverables, terms, and value proposition.',
                'description'     => 'Sales quote description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'quote_item',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed item description including product/service details, specifications, features, and relevant information for the quote line item.',
                'description'     => 'Sales quote item description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'invoice',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and descriptive invoice name that clearly identifies the billing document and its purpose.',
                'description'     => 'Sales invoice name generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'invoice',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive invoice description including billing details, services provided, payment terms, and relevant information.',
                'description'     => 'Sales invoice description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'invoice_item',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed item description including product/service details, specifications, features, and relevant information for the invoice line item.',
                'description'     => 'Sales invoice item description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'order',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and descriptive sales order name that clearly identifies the order and its purpose.',
                'description'     => 'Sales order name generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'order',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive sales order description including order details, delivery requirements, terms, and relevant information.',
                'description'     => 'Sales order description generation'
            ],
            [
                'module'          => 'sales',
                'submodule'       => 'order_item',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed item description including product/service details, specifications, features, and relevant information for the sales order line item.',
                'description'     => 'Sales order item description generation'
            ],

            // Taskly
            [
                'module'          => 'taskly',
                'submodule'       => 'project',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and descriptive project name that clearly identifies the project scope and objectives.',
                'description'     => 'Project name generation'
            ],
            [
                'module'          => 'taskly',
                'submodule'       => 'project',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive project description including objectives, scope, deliverables, timeline, and key requirements.',
                'description'     => 'Project description generation'
            ],
            [
                'module'          => 'taskly',
                'submodule'       => 'milestone',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and descriptive milestone title that identifies the key deliverable or achievement.',
                'description'     => 'Milestone title generation'
            ],
            [
                'module'          => 'taskly',
                'submodule'       => 'milestone',
                'field_type'      => 'summary',
                'prompt_template' => 'Write a concise milestone summary including objectives, deliverables, success criteria, and key activities.',
                'description'     => 'Milestone summary generation'
            ],
            [
                'module'          => 'taskly',
                'submodule'       => 'task',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and actionable task title that describes what needs to be accomplished.',
                'description'     => 'Task title generation'
            ],
            [
                'module'          => 'taskly',
                'submodule'       => 'task',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed task description including objectives, requirements, acceptance criteria, and any relevant context or constraints.',
                'description'     => 'Task description generation'
            ],
            [
                'module'          => 'taskly',
                'submodule'       => 'bug',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and descriptive bug title that summarizes the issue, including the affected component or feature.',
                'description'     => 'Bug title generation'
            ],
            [
                'module'          => 'taskly',
                'submodule'       => 'bug',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive bug description including steps to reproduce, expected behavior, actual behavior, environment details, and impact assessment.',
                'description'     => 'Bug description generation'
            ],

            // Training
            [
                'module'          => 'training',
                'submodule'       => 'training',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive training description including learning objectives, target audience, key topics covered, training methods, and expected outcomes.',
                'description'     => 'Training description generation'
            ],

            // Account
            [
                'module'          => 'account',
                'submodule'       => 'bank_transfer',
                'field_type'      => 'description',
                'prompt_template' => 'Write a clear and professional bank transfer description including the purpose of transfer, transaction details, and any relevant reference information.',
                'description'     => 'Bank transfer description generation'
            ],
            [
                'module'          => 'account',
                'submodule'       => 'revenue',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed revenue description including the source of income, transaction details, and any relevant business context or reference information.',
                'description'     => 'Revenue description generation'
            ],
            [
                'module'          => 'account',
                'submodule'       => 'expense',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed expense description including the purpose of expenditure, business justification, and any relevant transaction or reference details.',
                'description'     => 'Expense description generation'
            ],

            // HRM
            [
                'module'          => 'hrm',
                'submodule'       => 'leave_application',
                'field_type'      => 'reason',
                'prompt_template' => 'Write a professional and clear leave application reason explaining the purpose of leave, duration justification, and any relevant personal or professional circumstances.',
                'description'     => 'Leave application reason generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'award',
                'field_type'      => 'description',
                'prompt_template' => 'Write a professional and meaningful award description highlighting the employee\'s achievements, contributions, and the specific reasons for recognition.',
                'description'     => 'Award description generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'resignation',
                'field_type'      => 'description',
                'prompt_template' => 'Write a professional resignation description including detailed reasons for leaving, transition plans, and any relevant circumstances or feedback.',
                'description'     => 'Resignation description generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'event',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and engaging event title that captures the purpose and nature of the corporate or organizational event.',
                'description'     => 'Event title generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'event',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive event description including objectives, agenda highlights, target audience, expected outcomes, and any relevant logistics or requirements.',
                'description'     => 'Event description generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'promotion',
                'field_type'      => 'reason',
                'prompt_template' => 'Write a professional promotion reason highlighting the employee\'s achievements, performance improvements, leadership qualities, and justification for career advancement.',
                'description'     => 'Promotion reason generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'complaint',
                'field_type'      => 'subject',
                'prompt_template' => 'Generate a clear and professional complaint subject line that summarizes the issue while maintaining workplace professionalism.',
                'description'     => 'Complaint subject generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'complaint',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed and professional complaint description including specific incidents, dates, witnesses, impact on work environment, and desired resolution.',
                'description'     => 'Complaint description generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'warning',
                'field_type'      => 'subject',
                'prompt_template' => 'Generate a clear and professional warning subject line that summarizes the performance or conduct issue while maintaining workplace professionalism.',
                'description'     => 'Warning subject generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'warning',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed and professional warning description including specific incidents, policy violations, expected improvements, consequences, and support resources available.',
                'description'     => 'Warning description generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'termination',
                'field_type'      => 'reason',
                'prompt_template' => 'Write a professional and clear termination reason including specific grounds for termination, policy violations, performance issues, or business circumstances.',
                'description'     => 'Termination reason generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'termination',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed termination description including timeline of events, documentation references, final performance review, transition arrangements, and legal compliance notes.',
                'description'     => 'Termination description generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'announcement',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and engaging announcement title that captures attention and communicates the key message effectively to employees.',
                'description'     => 'Announcement title generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'announcement',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive announcement description including key details, action items, deadlines, contact information, and any relevant policies or procedures.',
                'description'     => 'Announcement description generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'document',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and descriptive document title that accurately reflects the content and purpose of the HR document.',
                'description'     => 'HRM document title generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'document',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed document description including purpose, target audience, key contents, usage guidelines, and any compliance or policy information.',
                'description'     => 'HRM document description generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'holiday',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and appropriate holiday name that accurately reflects the occasion, cultural significance, or celebration being observed.',
                'description'     => 'Holiday name generation'
            ],
            [
                'module'          => 'hrm',
                'submodule'       => 'holiday',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive holiday description including cultural significance, traditions, observance details, employee benefits, and any relevant company policies.',
                'description'     => 'Holiday description generation'
            ],

            // RestaurantMenu
            [
                'module'          => 'restaurantmenu',
                'submodule'       => 'item',
                'field_type'      => 'name',
                'prompt_template' => 'Generate an appetizing and creative menu item name. Make it descriptive, memorable, and appealing to customers. Consider cuisine type, ingredients, and cooking method.',
                'description'     => 'Menu item name generation'
            ],
            [
                'module'          => 'restaurantmenu',
                'submodule'       => 'item',
                'field_type'      => 'description',
                'prompt_template' => 'Write a mouth-watering short description for a restaurant menu item. Highlight key ingredients, flavors, and preparation style to entice customers. Keep it concise but compelling.',
                'description'     => 'Menu item description generation'
            ],
            [
                'module'          => 'restaurantmenu',
                'submodule'       => 'category',
                'field_type'      => 'name',
                'prompt_template' => 'Generate creative and appealing restaurant menu category names. Make them descriptive, organized, and attractive to customers. Consider cuisine type, meal timing, or food style.',
                'description'     => 'Menu category name generation'
            ],
            [
                'module'          => 'restaurantmenu',
                'submodule'       => 'offer',
                'field_type'      => 'name',
                'prompt_template' => 'Generate compelling promotional offer names for restaurant deals. Make them catchy, clear about the value proposition, and appealing to customers. Include urgency or exclusivity when appropriate.',
                'description'     => 'Restaurant offer name generation'
            ],
            [
                'module'          => 'restaurantmenu',
                'submodule'       => 'blog',
                'field_type'      => 'title',
                'prompt_template' => 'Generate engaging blog titles for restaurant content. Focus on food stories, recipes, restaurant news, culinary tips, or dining experiences. Make them SEO-friendly and click-worthy.',
                'description'     => 'Restaurant blog title generation'
            ],
            [
                'module'          => 'restaurantmenu',
                'submodule'       => 'blog',
                'field_type'      => 'description',
                'prompt_template' => 'Write compelling blog descriptions for restaurant content. Include engaging storytelling, culinary insights, restaurant updates, or food-related topics. Make it informative and entertaining for readers.',
                'description'     => 'Restaurant blog description generation'
            ],

            // Appointment
            [
                'module'          => 'appointment',
                'submodule'       => 'appointment',
                'field_type'      => 'appointment_name',
                'prompt_template' => 'Generate professional appointment service names. Include duration, service type, and purpose. Make them client-friendly, descriptive, and clearly communicate what the appointment entails.',
                'description'     => 'Appointment service name generation'
            ],
            [
                'module'          => 'appointment',
                'submodule'       => 'question',
                'field_type'      => 'question_name',
                'prompt_template' => 'Generate relevant intake questions for appointment booking forms. Focus on gathering essential information while maintaining professional tone. Consider privacy, service requirements, and customer experience.',
                'description'     => 'Appointment intake question generation'
            ],

            // ToDo
            [
                'module'          => 'todo',
                'submodule'       => 'task',
                'field_type'      => 'title',
                'prompt_template' => 'Generate clear and actionable task titles. Make them specific, measurable, and outcome-focused. Consider priority level, assigned users, and project context to create meaningful task names.',
                'description'     => 'ToDo task title generation'
            ],
            [
                'module'          => 'todo',
                'submodule'       => 'task',
                'field_type'      => 'description',
                'prompt_template' => 'Write comprehensive task descriptions including objectives, acceptance criteria, deliverables, and any dependencies. Provide clear guidance on what needs to be accomplished and how success will be measured.',
                'description'     => 'ToDo task description generation'
            ],

            // FileSharing
            [
                'module'          => 'filesharing',
                'submodule'       => 'file',
                'field_type'      => 'description',
                'prompt_template' => 'Generate professional and informative file descriptions. Include file purpose, content overview, target audience, and sharing context. Consider file type, security settings, and intended use to create clear, searchable descriptions.',
                'description'     => 'File sharing description generation'
            ],

            // Internalknowledge
            [
                'module'          => 'internalknowledge',
                'submodule'       => 'book',
                'field_type'      => 'title',
                'prompt_template' => 'Generate professional and descriptive knowledge base book titles. Consider the subject matter, target audience, and organizational context. Make titles clear, searchable, and indicative of the content within.',
                'description'     => 'Knowledge base book title generation'
            ],
            [
                'module'          => 'internalknowledge',
                'submodule'       => 'book',
                'field_type'      => 'description',
                'prompt_template' => 'Write comprehensive knowledge base book descriptions. Include learning objectives, target audience, key topics covered, prerequisites, and how this knowledge contributes to organizational goals. Make it informative and engaging.',
                'description'     => 'Knowledge base book description generation'
            ],
            [
                'module'          => 'internalknowledge',
                'submodule'       => 'article',
                'field_type'      => 'title',
                'prompt_template' => 'Generate clear and descriptive article titles for knowledge base content. Make them specific, searchable, and indicative of the article content. Consider SEO and internal search optimization.',
                'description'     => 'Knowledge base article title generation'
            ],
            [
                'module'          => 'internalknowledge',
                'submodule'       => 'article',
                'field_type'      => 'description',
                'prompt_template' => 'Write engaging article overviews that summarize key points, learning outcomes, and practical applications. Include what readers will learn, prerequisites, and how this knowledge applies to their work.',
                'description'     => 'Knowledge base article overview generation'
            ],

            // Documents
            [
                'module'          => 'documents',
                'submodule'       => 'document',
                'field_type'      => 'subject',
                'prompt_template' => 'Generate professional and descriptive document subjects. Make them clear, specific, and indicative of the document content. Consider document type, purpose, and organizational context for better searchability.',
                'description'     => 'Document subject generation'
            ],
            [
                'module'          => 'documents',
                'submodule'       => 'document',
                'field_type'      => 'document_notes',
                'prompt_template' => 'Write structured and informative document notes. Include key points, important considerations, action items, and relevant context. Make notes clear, actionable, and useful for document management.',
                'description'     => 'Document notes generation'
            ],
            [
                'module'          => 'documents',
                'submodule'       => 'document',
                'field_type'      => 'document_description',
                'prompt_template' => 'Create comprehensive document descriptions that explain the purpose, scope, and content. Include target audience, key objectives, and how this document fits into organizational processes. Make it informative and professional.',
                'description'     => 'Document description generation'
            ],
            [
                'module'          => 'documents',
                'submodule'       => 'document',
                'field_type'      => 'additional_description',
                'prompt_template' => 'Generate supplementary document information including background context, related documents, implementation guidelines, and additional considerations. Provide valuable context that complements the main description.',
                'description'     => 'Document additional description generation'
            ],

            // VideoHub
            [
                'module'          => 'videohub',
                'submodule'       => 'video',
                'field_type'      => 'title',
                'prompt_template' => 'Generate engaging and descriptive video titles that capture viewer attention. Make them SEO-friendly, clear about the video content, and appropriate for the target audience. Consider the module context and video purpose.',
                'description'     => 'Video title generation'
            ],
            [
                'module'          => 'videohub',
                'submodule'       => 'video',
                'field_type'      => 'description',
                'prompt_template' => 'Write comprehensive video descriptions that explain the content, learning objectives, and key takeaways. Include what viewers will learn, prerequisites, duration expectations, and how this video relates to the module context. Make it engaging and informative.',
                'description'     => 'Video description generation'
            ],

            // InnovationCenter
            [
                'module'          => 'innovation-center',
                'submodule'       => 'challenge',
                'field_type'      => 'challenge_name',
                'prompt_template' => 'Generate creative and inspiring innovation challenge names that motivate participation and clearly communicate the challenge focus. Make them engaging, specific, and aligned with innovation goals.',
                'description'     => 'Innovation challenge name generation'
            ],
            [
                'module'          => 'innovation-center',
                'submodule'       => 'challenge',
                'field_type'      => 'explanation',
                'prompt_template' => 'Write comprehensive challenge explanations that outline objectives, scope, expected outcomes, and participation guidelines. Include background context, success criteria, and how this challenge drives innovation.',
                'description'     => 'Innovation challenge explanation generation'
            ],
            [
                'module'          => 'innovation-center',
                'submodule'       => 'challenge',
                'field_type'      => 'notes',
                'prompt_template' => 'Create detailed challenge notes including implementation tips, resource requirements, timeline considerations, and additional guidance for participants and organizers.',
                'description'     => 'Innovation challenge notes generation'
            ],
            [
                'module'          => 'innovation-center',
                'submodule'       => 'creativity',
                'field_type'      => 'creativity_name',
                'prompt_template' => 'Generate innovative and compelling creativity project names that capture the essence of the idea and inspire stakeholders. Make them memorable, descriptive, and aligned with innovation objectives.',
                'description'     => 'Innovation creativity name generation'
            ],
            [
                'module'          => 'innovation-center',
                'submodule'       => 'creativity',
                'field_type'      => 'description',
                'prompt_template' => 'Write detailed creativity descriptions that explain the innovative concept, methodology, target audience, and expected impact. Include technical details, implementation approach, and value proposition.',
                'description'     => 'Innovation creativity description generation'
            ],
            [
                'module'          => 'innovation-center',
                'submodule'       => 'creativity',
                'field_type'      => 'organisational_effects',
                'prompt_template' => 'Analyze and describe the potential organizational effects of this innovation. Include impact on processes, culture, efficiency, competitive advantage, and strategic alignment. Consider both short-term and long-term implications.',
                'description'     => 'Innovation organizational effects analysis'
            ],
            [
                'module'          => 'innovation-center',
                'submodule'       => 'creativity',
                'field_type'      => 'goal_description',
                'prompt_template' => 'Define clear and measurable goals for this innovation project. Include specific objectives, success metrics, timeline expectations, and alignment with organizational strategy. Make goals SMART and actionable.',
                'description'     => 'Innovation goal description generation'
            ],
            [
                'module'          => 'innovation-center',
                'submodule'       => 'creativity',
                'field_type'      => 'notes',
                'prompt_template' => 'Create comprehensive project notes including implementation considerations, risk factors, resource requirements, stakeholder insights, and additional context for successful execution.',
                'description'     => 'Innovation creativity notes generation'
            ],

            // Assets
            [
                'module'          => 'assets',
                'submodule'       => 'asset',
                'field_type'      => 'name',
                'prompt_template' => 'Generate professional and descriptive asset names based on category, type, and specifications. Make them clear, standardized, and suitable for asset tracking and inventory management.',
                'description'     => 'Asset name generation'
            ],
            [
                'module'          => 'assets',
                'submodule'       => 'asset',
                'field_type'      => 'description',
                'prompt_template' => 'Write comprehensive asset descriptions including technical specifications, features, capabilities, usage guidelines, and maintenance requirements. Include model numbers, dimensions, and operational parameters where relevant.',
                'description'     => 'Asset description generation'
            ],
            [
                'module'          => 'assets',
                'submodule'       => 'asset',
                'field_type'      => 'serial_code',
                'prompt_template' => 'Generate standardized serial codes and asset identification numbers following organizational naming conventions. Include category prefixes, sequential numbering, and location codes for effective asset tracking.',
                'description'     => 'Asset serial code generation'
            ],
            [
                'module'          => 'assets',
                'submodule'       => 'maintenance',
                'field_type'      => 'title',
                'prompt_template' => 'Generate clear and descriptive maintenance task titles based on maintenance type, asset category, and specific procedures. Make them actionable and easily identifiable for scheduling and tracking.',
                'description'     => 'Maintenance title generation'
            ],
            [
                'module'          => 'assets',
                'submodule'       => 'maintenance',
                'field_type'      => 'description',
                'prompt_template' => 'Write detailed maintenance descriptions including step-by-step procedures, safety protocols, required tools, expected duration, and quality checkpoints. Include preventive measures and troubleshooting guidelines.',
                'description'     => 'Maintenance description generation'
            ],
            [
                'module'          => 'assets',
                'submodule'       => 'maintenance',
                'field_type'      => 'notes',
                'prompt_template' => 'Create comprehensive maintenance notes including best practices, safety considerations, common issues, troubleshooting tips, spare parts requirements, and follow-up recommendations for optimal asset performance.',
                'description'     => 'Maintenance notes generation'
            ],

            // Retainer
            [
                'module'          => 'retainer',
                'submodule'       => 'retainer',
                'field_type'      => 'payment_terms',
                'prompt_template' => 'Generate professional payment terms for retainer agreements. Include industry-standard terms like Net 30, Net 15, or custom payment schedules. Make them clear, legally appropriate, and business-friendly.',
                'description'     => 'Retainer payment terms generation'
            ],
            [
                'module'          => 'retainer',
                'submodule'       => 'retainer',
                'field_type'      => 'notes',
                'prompt_template' => 'Write comprehensive retainer notes including scope of work, deliverables, terms and conditions, client expectations, and important project details. Make them professional and legally compliant.',
                'description'     => 'Retainer notes generation'
            ],

            // VCard
            [
                'module'          => 'vcard',
                'submodule'       => 'contact',
                'field_type'      => 'message',
                'prompt_template' => 'Create professional and personalized contact messages that encourage engagement. Tailor the tone based on the contact context and business relationship. Make it welcoming and action-oriented.',
                'description'     => 'Contact message generation'
            ],
            [
                'module'          => 'vcard',
                'submodule'       => 'contact',
                'field_type'      => 'notes',
                'prompt_template' => 'Generate detailed contact notes including interaction history, preferences, follow-up actions, and relationship insights. Make notes actionable and useful for future communications.',
                'description'     => 'Contact notes generation'
            ],
            [
                'module'          => 'vcard',
                'submodule'       => 'appointment',
                'field_type'      => 'description',
                'prompt_template' => 'Create clear and professional appointment descriptions that outline the purpose, agenda, and expected outcomes. Include relevant details and preparation requirements for participants.',
                'description'     => 'Appointment description generation'
            ],

            // Quotation
            [
                'module'          => 'quotation',
                'submodule'       => 'quotation',
                'field_type'      => 'notes',
                'prompt_template' => 'Write comprehensive quotation notes including terms and conditions, delivery details, warranty information, validity period, and important project specifications. Include any special requirements, assumptions, or exclusions. Make them professional, clear, and legally compliant.',
                'description'     => 'Quotation notes generation'
            ],

            // Performance
            [
                'module'          => 'performance',
                'submodule'       => 'employee_goal',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and achievable employee goal title that reflects professional development, performance targets, or skill improvement objectives.',
                'description'     => 'Employee goal title generation'
            ],
            [
                'module'          => 'performance',
                'submodule'       => 'employee_goal',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive employee goal description including specific objectives, measurable outcomes, timeline expectations, and success criteria.',
                'description'     => 'Employee goal description generation'
            ],
            [
                'module'          => 'performance',
                'submodule'       => 'employee_goal',
                'field_type'      => 'target',
                'prompt_template' => 'Generate specific and measurable target values for employee goals. Include quantifiable metrics, benchmarks, or achievement levels that align with performance objectives.',
                'description'     => 'Employee goal target generation'
            ],
            [
                'module'          => 'performance',
                'submodule'       => 'performance_indicator',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and measurable performance indicator name that reflects key business metrics or employee performance criteria.',
                'description'     => 'Performance indicator name generation'
            ],
            [
                'module'          => 'performance',
                'submodule'       => 'performance_indicator',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed performance indicator description including measurement criteria, calculation methods, target ranges, and business impact.',
                'description'     => 'Performance indicator description generation'
            ],
            [
                'module'          => 'performance',
                'submodule'       => 'review_cycle',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional review cycle name that clearly identifies the review period, frequency, and organizational scope.',
                'description'     => 'Review cycle name generation'
            ],
            [
                'module'          => 'performance',
                'submodule'       => 'review_cycle',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive review cycle description including objectives, timeline, participants, evaluation criteria, and expected outcomes.',
                'description'     => 'Review cycle description generation'
            ],

            // LMS
            [
                'module'          => 'lms',
                'submodule'       => 'course',
                'field_type'      => 'name',
                'prompt_template' => 'Generate an engaging and professional course name that clearly communicates the learning objectives and attracts students.',
                'description'     => 'Course name generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'course',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive course description including learning objectives, key topics, target audience, expected outcomes, and course benefits.',
                'description'     => 'Course description generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'course',
                'field_type'      => 'requirements',
                'prompt_template' => 'Create detailed course requirements including prerequisites, technical requirements, recommended background knowledge, and necessary tools or software.',
                'description'     => 'Course requirements generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'blog',
                'field_type'      => 'excerpt',
                'prompt_template' => 'Write a compelling blog excerpt that summarizes the key points and encourages readers to continue reading the full article.',
                'description'     => 'Blog excerpt generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'blog',
                'field_type'      => 'content',
                'prompt_template' => 'Create engaging and informative blog content that provides value to readers, supports learning objectives, and maintains reader interest throughout.',
                'description'     => 'Blog content generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'blog',
                'field_type'      => 'meta_title',
                'prompt_template' => 'Generate an SEO-optimized meta title that accurately describes the blog content and improves search engine visibility.',
                'description'     => 'Blog meta title generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'blog',
                'field_type'      => 'meta_description',
                'prompt_template' => 'Write an SEO-friendly meta description that summarizes the blog content and encourages click-through from search results.',
                'description'     => 'Blog meta description generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'category',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and descriptive category name that helps organize courses and content effectively for students.',
                'description'     => 'Category name generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'category',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive category description that explains the types of courses or content included and helps students understand the category purpose.',
                'description'     => 'Category description generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'testimonial',
                'field_type'      => 'review',
                'prompt_template' => 'Create an authentic and compelling testimonial review that highlights the positive learning experience, course benefits, and student satisfaction.',
                'description'     => 'Testimonial review generation'
            ],
            [
                'module'          => 'lms',
                'submodule'       => 'custom_page',
                'field_type'      => 'content',
                'prompt_template' => 'Create informative and engaging page content that serves the intended purpose and provides value to LMS users and students.',
                'description'     => 'Custom page content generation'
            ],

            // TeamWorkload
            [
                'module'          => 'teamworkload',
                'submodule'       => 'holiday',
                'field_type'      => 'occasion',
                'prompt_template' => 'Generate a clear and appropriate holiday occasion name that accurately reflects the celebration, observance, or event being recognized.',
                'description'     => 'Holiday occasion generation'
            ],
            [
                'module'          => 'teamworkload',
                'submodule'       => 'timesheet',
                'field_type'      => 'notes',
                'prompt_template' => 'Write professional timesheet notes describing work activities, tasks completed, progress made, and any relevant details for accurate time tracking.',
                'description'     => 'Timesheet notes generation'
            ],

            // Planning
            [
                'module'          => 'planning',
                'submodule'       => 'charter',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a professional and strategic charter name that clearly communicates the project purpose, scope, and business value. Make it concise, memorable, and aligned with organizational goals.',
                'description'     => 'Planning charter name generation'
            ],
            [
                'module'          => 'planning',
                'submodule'       => 'charter',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive charter description that outlines the project overview, business case, key stakeholders, and strategic importance. Include background context, problem statement, and high-level approach.',
                'description'     => 'Planning charter description generation'
            ],
            [
                'module'          => 'planning',
                'submodule'       => 'charter',
                'field_type'      => 'organisational_effects',
                'prompt_template' => 'Analyze and describe the organizational effects of this charter. Include impact on processes, culture, efficiency, competitive advantage, resource allocation, and strategic alignment. Consider both short-term and long-term implications across departments.',
                'description'     => 'Planning charter organizational effects analysis'
            ],
            [
                'module'          => 'planning',
                'submodule'       => 'charter',
                'field_type'      => 'goal_description',
                'prompt_template' => 'Define clear, measurable, and achievable goals for this charter. Include specific objectives, success metrics, timeline expectations, deliverables, and alignment with organizational strategy. Make goals SMART (Specific, Measurable, Achievable, Relevant, Time-bound).',
                'description'     => 'Planning charter goal description generation'
            ],
            [
                'module'          => 'planning',
                'submodule'       => 'charter',
                'field_type'      => 'notes',
                'prompt_template' => 'Create comprehensive charter notes including implementation considerations, risk factors, resource requirements, stakeholder insights, dependencies, assumptions, and additional context for successful project execution.',
                'description'     => 'Planning charter notes generation'
            ],
            [
                'module'          => 'planning',
                'submodule'       => 'challenge',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and compelling challenge name that accurately describes the business problem, opportunity, or strategic initiative. Make it specific, actionable, and aligned with organizational priorities.',
                'description'     => 'Planning challenge name generation'
            ],
            [
                'module'          => 'planning',
                'submodule'       => 'challenge',
                'field_type'      => 'explanation',
                'prompt_template' => 'Write a detailed challenge explanation that outlines the problem statement, business impact, root causes, constraints, and desired outcomes. Include context, urgency, and strategic importance to the organization.',
                'description'     => 'Planning challenge explanation generation'
            ],
            [
                'module'          => 'planning',
                'submodule'       => 'challenge',
                'field_type'      => 'notes',
                'prompt_template' => 'Create comprehensive challenge notes including implementation considerations, risk factors, resource requirements, stakeholder insights, dependencies, assumptions, and additional context for successful challenge resolution.',
                'description'     => 'Planning challenge notes generation'
            ],
            [
                'module'          => 'planning',
                'submodule'       => 'category',
                'field_type'      => 'name',
                'prompt_template' => 'Generate professional and descriptive category names for planning organization. Make them clear, standardized, and suitable for grouping related planning items, challenges, or charters effectively.',
                'description'     => 'Planning category name generation'
            ],

            // Commission
            [
                'module'          => 'commission',
                'submodule'       => 'plan',
                'field_type'      => 'plan_name',
                'prompt_template' => 'Generate a professional and descriptive commission plan name that clearly identifies the plan purpose, target audience, and commission structure. Make it business-appropriate and easily recognizable for sales teams.',
                'description'     => 'Commission plan name generation'
            ],
            [
                'module'          => 'commission',
                'submodule'       => 'plan',
                'field_type'      => 'notes',
                'prompt_template' => 'Write comprehensive commission plan notes including plan objectives, eligibility criteria, calculation methods, payment terms, performance requirements, and important terms and conditions. Make it clear and professional for sales documentation.',
                'description'     => 'Commission plan notes generation'
            ],

            // FixEquipment
            [
                'module'          => 'fixequipment',
                'submodule'       => 'asset',
                'field_type'      => 'asset_name',
                'prompt_template' => 'Generate a professional and descriptive asset name based on equipment type, category, and specifications. Make it clear, standardized, and suitable for asset tracking and inventory management. Follow industry naming conventions.',
                'description'     => 'Fix equipment asset name generation'
            ],
            [
                'module'          => 'fixequipment',
                'submodule'       => 'asset',
                'field_type'      => 'model',
                'prompt_template' => 'Generate appropriate model numbers or identifiers for equipment assets. Consider manufacturer standards, equipment type, and technical specifications. Make them professional and industry-standard.',
                'description'     => 'Fix equipment asset model generation'
            ],
            [
                'module'          => 'fixequipment',
                'submodule'       => 'asset',
                'field_type'      => 'serial_number',
                'prompt_template' => 'Generate standardized serial numbers for equipment assets following organizational naming conventions. Include category prefixes, sequential numbering, and location codes for effective asset tracking.',
                'description'     => 'Fix equipment asset serial number generation'
            ],
            [
                'module'          => 'fixequipment',
                'submodule'       => 'asset',
                'field_type'      => 'description',
                'prompt_template' => 'Write comprehensive asset descriptions including technical specifications, features, capabilities, usage guidelines, and maintenance requirements. Include model numbers, dimensions, and operational parameters where relevant.',
                'description'     => 'Fix equipment asset description generation'
            ],
            [
                'module'          => 'fixequipment',
                'submodule'       => 'maintenance',
                'field_type'      => 'maintenance_type',
                'prompt_template' => 'Generate clear and descriptive maintenance type classifications based on maintenance procedures, frequency, and equipment requirements. Use industry-standard maintenance terminology and categories.',
                'description'     => 'Fix equipment maintenance type generation'
            ],
            [
                'module'          => 'fixequipment',
                'submodule'       => 'maintenance',
                'field_type'      => 'description',
                'prompt_template' => 'Write detailed maintenance descriptions including step-by-step procedures, safety protocols, required tools, expected duration, and quality checkpoints. Include preventive measures and troubleshooting guidelines for optimal equipment performance.',
                'description'     => 'Fix equipment maintenance description generation'
            ],
            [
                'module'          => 'fixequipment',
                'submodule'       => 'audit',
                'field_type'      => 'title',
                'prompt_template' => 'Generate professional and descriptive audit titles that clearly identify the audit scope, purpose, and equipment focus. Make them systematic, organized, and suitable for compliance and tracking purposes.',
                'description'     => 'Fix equipment audit title generation'
            ],
            [
                'module'          => 'fixequipment',
                'submodule'       => 'component',
                'field_type'      => 'title',
                'prompt_template' => 'Generate clear and descriptive component titles based on equipment type, function, and specifications. Make them standardized, professional, and suitable for inventory management and parts tracking.',
                'description'     => 'Fix equipment component title generation'
            ],

            // Notes
            [
                'module'          => 'notes',
                'submodule'       => 'note',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and descriptive note title that summarizes the main topic or purpose. Make it concise, professional, and easily searchable for future reference. Consider the context and content to create meaningful titles.',
                'description'     => 'Note title generation'
            ],
            [
                'module'          => 'notes',
                'submodule'       => 'note',
                'field_type'      => 'description',
                'prompt_template' => 'Write well-structured note content with proper formatting, clear sections, and organized information. Include relevant details, action items, and key points in a professional format. Make it comprehensive yet concise for effective knowledge management.',
                'description'     => 'Note description generation'
            ],

            // SupportTicket
            [
                'module'          => 'supportticket',
                'submodule'       => 'ticket',
                'field_type'      => 'subject',
                'prompt_template' => 'Generate a clear and professional support ticket subject line based on the issue description. Make it concise, specific, and actionable for support staff to quickly understand the problem.',
                'description'     => 'Support ticket subject generation'
            ],
            [
                'module'          => 'supportticket',
                'submodule'       => 'ticket',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed support ticket description including problem statement, steps to reproduce, expected vs actual behavior, system information, and any error messages. Make it comprehensive for efficient troubleshooting.',
                'description'     => 'Support ticket description generation'
            ],
            [
                'module'          => 'supportticket',
                'submodule'       => 'ticket',
                'field_type'      => 'name',
                'prompt_template' => 'Generate professional customer names for support tickets. Make them realistic, diverse, and business-appropriate for testing and demonstration purposes.',
                'description'     => 'Support ticket customer name generation'
            ],
            [
                'module'          => 'supportticket',
                'submodule'       => 'faq',
                'field_type'      => 'question',
                'prompt_template' => 'Generate clear and commonly asked questions for FAQ based on support topics and customer inquiries. Make them specific, searchable, and address real user concerns.',
                'description'     => 'FAQ question generation'
            ],
            [
                'module'          => 'supportticket',
                'submodule'       => 'faq',
                'field_type'      => 'answer',
                'prompt_template' => 'Write comprehensive and helpful FAQ answers that provide clear solutions, step-by-step instructions, and additional resources. Make them easy to understand and actionable.',
                'description'     => 'FAQ answer generation'
            ],
            [
                'module'          => 'supportticket',
                'submodule'       => 'knowledge',
                'field_type'      => 'title',
                'prompt_template' => 'Create informative knowledge base article titles that are searchable and help users find solutions quickly. Make them descriptive, specific, and SEO-friendly.',
                'description'     => 'Knowledge base title generation'
            ],
            [
                'module'          => 'supportticket',
                'submodule'       => 'knowledge',
                'field_type'      => 'description',
                'prompt_template' => 'Write comprehensive knowledge base article content including detailed explanations, step-by-step procedures, troubleshooting guides, and helpful tips. Make it informative and user-friendly.',
                'description'     => 'Knowledge base description generation'
            ],
            [
                'module'          => 'supportticket',
                'submodule'       => 'category',
                'field_type'      => 'name',
                'prompt_template' => 'Generate professional and descriptive category names for support ticket organization. Make them clear, standardized, and suitable for grouping related support issues effectively.',
                'description'     => 'Support ticket category name generation'
            ],
            [
                'module'          => 'supportticket',
                'submodule'       => 'custom_page',
                'field_type'      => 'content',
                'prompt_template' => 'Create informative and engaging custom page content for support portal. Include helpful information, policies, procedures, and user guidance that enhances the support experience.',
                'description'     => 'Support custom page content generation'
            ],

            // Inventory
            [
                'module'          => 'inventory',
                'submodule'       => 'adjustment',
                'field_type'      => 'reason',
                'prompt_template' => 'Write a professional and clear inventory adjustment reason. Include the specific cause for the adjustment (such as damaged goods, stock recount, returns, or system correction), reference details if applicable, and any relevant warehouse or product information. Make it audit-compliant and suitable for inventory records.',
                'description'     => 'Inventory adjustment reason generation'
            ],

            // Goal
            [
                'module'          => 'goal',
                'submodule'       => 'goal',
                'field_type'      => 'goal_description',
                'prompt_template' => 'Write a clear and actionable goal description that explains the purpose, expected outcomes, success criteria, and strategic importance. Include context about why this goal matters, key milestones, and how progress will be measured. Make it motivating and specific enough to guide execution.',
                'description'     => 'Goal description generation'
            ],
            [
                'module'          => 'goal',
                'submodule'       => 'milestone',
                'field_type'      => 'milestone_description',
                'prompt_template' => 'Write a comprehensive milestone description that defines deliverables, acceptance criteria, progress checkpoints, and expected outcomes. Include specific targets, dependencies, and how this milestone contributes to the overall goal. Make it measurable and time-bound.',
                'description'     => 'Milestone description generation'
            ],
            [
                'module'          => 'goal',
                'submodule'       => 'contribution',
                'field_type'      => 'notes',
                'prompt_template' => 'Write professional contribution notes documenting the source, purpose, and context of funds added to a goal. Include relevant transaction details, references, and any notes about the contribution that would be useful for tracking and reporting purposes.',
                'description'     => 'Contribution notes generation'
            ],

            // BudgetPlanner
            [
                'module'          => 'budgetplanner',
                'submodule'       => 'budget',
                'field_type'      => 'budget_name',
                'prompt_template' => 'Generate a professional and descriptive budget name based on the budget type (operational, capital, or cash flow) and context. Make it clear, specific, and easily identifiable for financial planning and reporting purposes.',
                'description'     => 'Budget name generation'
            ],
            [
                'module'          => 'budgetplanner',
                'submodule'       => 'budgetperiod',
                'field_type'      => 'period_name',
                'prompt_template' => 'Generate a professional and standardized budget period name based on the financial year and date range. Use common naming conventions like FY, Q1/Q2/Q3/Q4, or annual periods. Make it clear and suitable for financial reporting.',
                'description'     => 'Budget period name generation'
            ],

            // Procurement
            [
                'module'          => 'procurement',
                'submodule'       => 'rfx',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive RFx description that clearly outlines the procurement scope, objectives, background context, and what the organization is looking for. Include project overview, expected deliverables, evaluation approach, and any relevant business context. Make it professional and compelling for potential vendors.',
                'description'     => 'RFx description generation'
            ],
            [
                'module'          => 'procurement',
                'submodule'       => 'rfx',
                'field_type'      => 'requirement',
                'prompt_template' => 'Write detailed RFx requirements including technical specifications, functional requirements, performance criteria, compliance standards, and qualification requirements. Organize into clear categories: mandatory requirements, preferred requirements, and evaluation criteria. Make it specific and measurable for vendor responses.',
                'description'     => 'RFx requirement generation'
            ],
            [
                'module'          => 'procurement',
                'submodule'       => 'rfx',
                'field_type'      => 'terms_and_conditions',
                'prompt_template' => 'Draft professional procurement terms and conditions including payment terms, delivery requirements, confidentiality clauses, intellectual property rights, termination conditions, liability limitations, dispute resolution, and compliance requirements. Make them legally sound and appropriate for vendor contracts.',
                'description'     => 'RFx terms and conditions generation'
            ],
            [
                'module'          => 'procurement',
                'submodule'       => 'interviewschedule',
                'field_type'      => 'comment',
                'prompt_template' => 'Write professional interview schedule comments including interview objectives, key discussion points, evaluation criteria, candidate background notes, and any specific questions or topics to cover. Make it structured and useful for interviewers.',
                'description'     => 'Interview schedule comment generation'
            ],
            [
                'module'          => 'procurement',
                'submodule'       => 'rfxapplication',
                'field_type'      => 'cover_letter',
                'prompt_template' => 'Write a professional and compelling cover letter for an RFx application. Include introduction, relevant experience, key qualifications, understanding of the project requirements, value proposition, and a strong closing statement. Tailor the tone to the specific procurement context.',
                'description'     => 'RFx application cover letter generation'
            ],

            // SalesAgent
            [
                'module'          => 'salesagent',
                'submodule'       => 'salesagent',
                'field_type'      => 'notes',
                'prompt_template' => 'Write professional sales agent notes including agent background, skills summary, experience highlights, territory coverage, and any relevant performance indicators or specializations. Make it useful for management review and team coordination.',
                'description'     => 'Sales agent notes generation'
            ],
            [
                'module'          => 'salesagent',
                'submodule'       => 'commissionplan',
                'field_type'      => 'plan_name',
                'prompt_template' => 'Generate a professional and descriptive commission plan name based on the plan type (percentage, fixed, tiered, or hybrid) and sales context. Make it clear, specific, and easily identifiable for sales team management.',
                'description'     => 'Commission plan name generation'
            ],
            [
                'module'          => 'salesagent',
                'submodule'       => 'salesterritory',
                'field_type'      => 'territory_name',
                'prompt_template' => 'Generate a professional and clear sales territory name based on the geographic region, city, state, or country. Make it descriptive, business-appropriate, and easy to identify on maps and reports.',
                'description'     => 'Sales territory name generation'
            ],
            [
                'module'          => 'salesagent',
                'submodule'       => 'salesterritory',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive sales territory description including geographic scope, key markets, customer segments, growth opportunities, and any special coverage notes. Make it useful for sales planning and agent onboarding.',
                'description'     => 'Sales territory description generation'
            ],
            [
                'module'          => 'salesagent',
                'submodule'       => 'commissionadjustment',
                'field_type'      => 'adjustment_reason',
                'prompt_template' => 'Write a professional and clear commission adjustment reason explaining the purpose of the bonus, penalty, or correction. Include relevant transaction details, performance context, and justification for the adjustment. Make it audit-compliant and suitable for commission records.',
                'description'     => 'Commission adjustment reason generation'
            ],
            [
                'module'          => 'salesagent',
                'submodule'       => 'commissionpayment',
                'field_type'      => 'notes',
                'prompt_template' => 'Write professional commission payment notes including payment purpose, allocation details, reference information, and any relevant transaction context. Make it clear and useful for accounting and sales agent records.',
                'description'     => 'Commission payment notes generation'
            ],

            // Rotas
            [
                'module'          => 'rotas',
                'submodule'       => 'leavetype',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive leave type description including eligibility criteria, accrual rules, carry-forward policy, approval workflow, and any special conditions. Make it clear, professional, and suitable for HR policy documentation.',
                'description'     => 'Leave type description generation'
            ],
            [
                'module'          => 'rotas',
                'submodule'       => 'employeedocumenttype',
                'field_type'      => 'description',
                'prompt_template' => 'Write a clear document type description explaining what the document is, why it is required, acceptable formats, validity requirements, and any submission guidelines. Make it helpful for employees and HR administrators.',
                'description'     => 'Employee document type description generation'
            ],
            [
                'module'          => 'rotas',
                'submodule'       => 'availability',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and descriptive availability schedule name based on the time period, shift pattern, or special occasion. Make it easily identifiable for roster planning and employee scheduling.',
                'description'     => 'Availability schedule name generation'
            ],

            // Holidayz
            [
                'module'          => 'holidayz',
                'submodule'       => 'blog',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a compelling and SEO-friendly blog post title for a travel or hospitality topic. Make it catchy, relevant, and optimized for search engines while being engaging for readers.',
                'description'     => 'Blog title generation'
            ],
            [
                'module'          => 'holidayz',
                'submodule'       => 'blog',
                'field_type'      => 'content',
                'prompt_template' => 'Write a comprehensive, engaging blog post for a travel or hospitality website. Include an introduction, key points with practical information, tips for travelers, local insights, and a compelling conclusion. Use a friendly yet professional tone and optimize for readability.',
                'description'     => 'Blog content generation'
            ],
            [
                'module'          => 'holidayz',
                'submodule'       => 'blog',
                'field_type'      => 'meta_description',
                'prompt_template' => 'Write a concise and compelling SEO meta description for a travel blog post. Include relevant keywords, summarize the content attractively, and stay within 155-160 characters for optimal search engine display.',
                'description'     => 'Blog meta description generation'
            ],
            [
                'module'          => 'holidayz',
                'submodule'       => 'room',
                'field_type'      => 'short_description',
                'prompt_template' => 'Write a brief, appealing short description for a hotel room listing. Highlight key amenities, unique features, and the guest experience in 2-3 sentences suitable for booking platforms and search results.',
                'description'     => 'Room short description generation'
            ],
            [
                'module'          => 'holidayz',
                'submodule'       => 'room',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed and persuasive room description for a hotel or resort. Include room features, amenities, views, bed configuration, occupancy details, special perks, and nearby attractions. Make it appealing to potential guests while being accurate and informative.',
                'description'     => 'Room description generation'
            ],
            [
                'module'          => 'holidayz',
                'submodule'       => 'custompage',
                'field_type'      => 'description',
                'prompt_template' => 'Write a clear and concise description for a custom website page. Summarize the page purpose, target audience, and key information in a professional tone suitable for SEO and user navigation.',
                'description'     => 'Custom page description generation'
            ],
            [
                'module'          => 'holidayz',
                'submodule'       => 'custompage',
                'field_type'      => 'contents',
                'prompt_template' => 'Write comprehensive and well-structured content for a custom website page. Include appropriate headings, relevant information, call-to-action elements, and formatting suitable for a travel or hospitality website. Ensure the content is engaging, accurate, and aligned with the page purpose.',
                'description'     => 'Custom page contents generation'
            ],

            // PropertyManagement
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'blog',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a compelling and SEO-friendly blog post title for a real estate or property management topic. Make it catchy, relevant, and optimized for search engines while being engaging for readers.',
                'description'     => 'Property blog title generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'blog',
                'field_type'      => 'description',
                'prompt_template' => 'Write a concise and engaging description for a property management blog post. Summarize the key points, target audience benefits, and include relevant keywords for SEO.',
                'description'     => 'Property blog description generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'blog',
                'field_type'      => 'blog_content',
                'prompt_template' => 'Write a comprehensive, engaging blog post for a property management or real estate website. Include an introduction, key insights, practical tips for property owners or tenants, market trends, and a compelling conclusion. Use a professional yet approachable tone.',
                'description'     => 'Property blog content generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'property',
                'field_type'      => 'description',
                'prompt_template' => 'Write a compelling and detailed property description for a real estate listing. Highlight unique features, amenities, location benefits, nearby attractions, and the lifestyle the property offers. Make it persuasive and suitable for marketing materials.',
                'description'     => 'Property description generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'property',
                'field_type'      => 'address',
                'prompt_template' => 'Write a clear and complete property address description including street, area landmarks, neighborhood highlights, and proximity to key amenities. Make it useful for tenants, buyers, and property managers.',
                'description'     => 'Property address generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'propertyunit',
                'field_type'      => 'unit_name',
                'prompt_template' => 'Generate a clear and descriptive property unit name based on the unit type, size, layout, or special features. Make it easily identifiable for property listings and tenant communications.',
                'description'     => 'Property unit name generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'propertyunit',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed and appealing unit description for a rental or sale listing. Include room details, amenities, unique features, and the ideal tenant profile. Make it persuasive and informative.',
                'description'     => 'Property unit description generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'propertyunit',
                'field_type'      => 'utilities_included',
                'prompt_template' => 'Generate a clear list of utilities included in a property rental or sale. Include common utilities like water, electricity, gas, internet, trash, and any other services. Make it concise and easy to understand for prospective tenants or buyers.',
                'description'     => 'Property unit utilities generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'custompage',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a professional and SEO-friendly title for a property management website page. Make it clear, relevant, and appealing to property owners, tenants, or investors.',
                'description'     => 'Property custom page title generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'custompage',
                'field_type'      => 'description',
                'prompt_template' => 'Write a clear and concise description for a property management website page. Summarize the page purpose, target audience, and key information in a professional tone suitable for SEO and user navigation.',
                'description'     => 'Property custom page description generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'custompage',
                'field_type'      => 'contents',
                'prompt_template' => 'Write comprehensive and well-structured content for a property management website page. Include appropriate headings, relevant information, call-to-action elements, and formatting suitable for real estate and property management. Ensure the content is engaging, accurate, and aligned with the page purpose.',
                'description'     => 'Property custom page contents generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'propertymaintenancerequest',
                'field_type'      => 'issue',
                'prompt_template' => 'Generate a clear and concise maintenance issue title based on the problem description. Make it specific, actionable, and suitable for maintenance tracking and contractor dispatch.',
                'description'     => 'Maintenance request issue generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'propertymaintenancerequest',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed maintenance request description including the problem details, affected areas, urgency level, and any relevant context. Make it clear for maintenance staff and contractors to understand and act upon.',
                'description'     => 'Maintenance request description generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'propertyinspection',
                'field_type'      => 'inspection_result',
                'prompt_template' => 'Generate a clear and professional property inspection result summary. Include the overall condition, key findings, areas of concern, and recommendations. Make it suitable for property records and tenant communications.',
                'description'     => 'Property inspection result generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'propertyinspection',
                'field_type'      => 'comments',
                'prompt_template' => 'Write detailed and professional property inspection comments. Include observations about property condition, maintenance needs, safety concerns, and recommendations for improvements. Make it thorough and useful for property managers and owners.',
                'description'     => 'Property inspection comments generation'
            ],
            [
                'module'          => 'propertymanagement',
                'submodule'       => 'propertytenantcommunication',
                'field_type'      => 'message',
                'prompt_template' => 'Write a professional and clear tenant communication message. Include the purpose, relevant details, any required actions, and appropriate tone for property management correspondence. Make it polite, concise, and effective.',
                'description'     => 'Tenant communication message generation'
            ],

            // School
            [
                'module'          => 'school',
                'submodule'       => 'homework',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and descriptive homework assignment title for a school subject. Include the topic, assignment type, and relevant academic context. Make it easy for students and teachers to understand.',
                'description'     => 'Homework title generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'homework',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive homework assignment description for students. Include learning objectives, task instructions, expected outcomes, resources needed, and submission guidelines. Make it clear, engaging, and age-appropriate.',
                'description'     => 'Homework description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'book',
                'field_type'      => 'title',
                'prompt_template' => 'Generate an accurate book title for a library catalog entry. Ensure proper capitalization and formatting suitable for educational library systems.',
                'description'     => 'Book title generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'book',
                'field_type'      => 'author',
                'prompt_template' => 'Generate a properly formatted author name for a library catalog entry. Include full name with appropriate formatting for educational library systems.',
                'description'     => 'Book author generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'book',
                'field_type'      => 'description',
                'prompt_template' => 'Write a concise and informative book description for a school library catalog. Include a brief summary, key themes, reading level, and educational value. Make it helpful for students and educators selecting reading materials.',
                'description'     => 'Book description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'noticeboard',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and attention-grabbing notice title for a school notice board. Make it concise, informative, and suitable for students, parents, and staff.',
                'description'     => 'Notice board title generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'noticeboard',
                'field_type'      => 'description',
                'prompt_template' => 'Write a clear and professional notice description for a school notice board. Include all relevant details, deadlines, contact information, and action items. Make it easy to read and understand for students, parents, and staff.',
                'description'     => 'Notice board description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'meeting',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a professional and descriptive meeting title for a school meeting. Include the meeting purpose, participants, and context. Make it clear for scheduling and record-keeping.',
                'description'     => 'Meeting title generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'meeting',
                'field_type'      => 'description',
                'prompt_template' => 'Write a professional meeting description for a school meeting. Include purpose, expected outcomes, background context, and any preparation required. Make it informative for all attendees.',
                'description'     => 'Meeting description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'meeting',
                'field_type'      => 'agenda',
                'prompt_template' => 'Write a structured meeting agenda for a school meeting. Include discussion topics, time allocations, responsible persons, and expected outcomes. Make it organized and easy to follow.',
                'description'     => 'Meeting agenda generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'meeting',
                'field_type'      => 'minutes',
                'prompt_template' => 'Write comprehensive meeting minutes for a school meeting. Include attendees, key discussion points, decisions made, action items with owners and deadlines. Make it clear, accurate, and useful for record-keeping.',
                'description'     => 'Meeting minutes generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'event',
                'field_type'      => 'title',
                'prompt_template' => 'Generate an engaging and descriptive title for a school event. Make it catchy, informative, and appealing to students, parents, and staff.',
                'description'     => 'School event title generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'event',
                'field_type'      => 'description',
                'prompt_template' => 'Write an engaging school event description. Include event details, purpose, activities, target audience, and any special instructions. Make it exciting and informative for participants.',
                'description'     => 'School event description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'event',
                'field_type'      => 'location',
                'prompt_template' => 'Generate a clear location description for a school event. Include venue details, directions, accessibility information, and any relevant setup notes.',
                'description'     => 'School event location generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'healthrecord',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and professional health record title. Include the type of record, student context, and relevant medical category. Make it suitable for school health records.',
                'description'     => 'Health record title generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'healthrecord',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed health record description for a student. Include symptoms, observations, medical context, and relevant health information. Make it professional, accurate, and suitable for school health records while maintaining privacy standards.',
                'description'     => 'Health record description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'healthrecord',
                'field_type'      => 'notes',
                'prompt_template' => 'Write professional health record notes for a school health record. Include follow-up instructions, recommendations, and any important observations. Make it clear, actionable, and suitable for school health staff.',
                'description'     => 'Health record notes generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'feecategory',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and descriptive fee category name for a school fee structure. Make it easily understandable for parents and administrators.',
                'description'     => 'Fee category name generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'feecategory',
                'field_type'      => 'description',
                'prompt_template' => 'Write a clear fee category description for a school. Explain what the fee covers, payment terms, and any relevant conditions. Make it transparent and informative for parents.',
                'description'     => 'Fee category description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'feecollection',
                'field_type'      => 'remarks',
                'prompt_template' => 'Write professional fee collection remarks for a school payment record. Include payment context, any special considerations, and relevant notes for accounting records.',
                'description'     => 'Fee collection remarks generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'hostel',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and descriptive name for a school hostel or dormitory. Make it identifiable and suitable for student accommodation.',
                'description'     => 'Hostel name generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'hostel',
                'field_type'      => 'description',
                'prompt_template' => 'Write a descriptive hostel description for a school accommodation. Include facilities, rules, amenities, and living conditions. Make it informative for students and parents.',
                'description'     => 'Hostel description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'hostel',
                'field_type'      => 'address',
                'prompt_template' => 'Write a clear hostel address description including location details, nearby landmarks, and accessibility information. Make it useful for students, parents, and visitors.',
                'description'     => 'Hostel address generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'hostelroom',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed room description for a school hostel room. Include room features, amenities, occupancy details, and any special facilities. Make it informative for students selecting accommodation.',
                'description'     => 'Hostel room description generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'hostelroom',
                'field_type'      => 'facilities',
                'prompt_template' => 'Generate a clear list of facilities available in a school hostel room. Include furniture, utilities, amenities, and any shared facilities. Make it comprehensive and easy to understand.',
                'description'     => 'Hostel room facilities generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'bookissue',
                'field_type'      => 'notes',
                'prompt_template' => 'Write professional library book issue notes. Include borrowing context, condition notes, return reminders, and any special instructions. Make it useful for library staff and borrowers.',
                'description'     => 'Book issue notes generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'hostelstudent',
                'field_type'      => 'remarks',
                'prompt_template' => 'Write professional remarks for a hostel student record. Include accommodation notes, behavior observations, and any relevant administrative notes. Make it clear and useful for hostel management.',
                'description'     => 'Hostel student remarks generation'
            ],
            [
                'module'          => 'school',
                'submodule'       => 'vehicle',
                'field_type'      => 'remarks',
                'prompt_template' => 'Write professional vehicle remarks for a school transport record. Include maintenance notes, condition observations, and any relevant operational notes. Make it useful for transport management.',
                'description'     => 'Vehicle remarks generation'
            ],

            // BeautySpaManagement
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'service',
                'field_type'      => 'name',
                'prompt_template' => 'Generate an attractive and professional spa or beauty service name. Make it catchy, memorable, and suitable for a beauty salon or spa menu. Include the type of treatment or service.',
                'description'     => 'Beauty service name generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'service',
                'field_type'      => 'description',
                'prompt_template' => 'Write a compelling and detailed description for a beauty or spa service. Include the benefits, procedure overview, duration, and what the client can expect. Use warm, inviting spa language.',
                'description'     => 'Beauty service description generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'serviceoffer',
                'field_type'      => 'title',
                'prompt_template' => 'Generate an eye-catching promotional title for a beauty spa service offer or discount. Make it enticing and marketing-friendly to attract customers.',
                'description'     => 'Service offer title generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'serviceoffer',
                'field_type'      => 'name',
                'prompt_template' => 'Generate a clear and appealing name for a beauty spa promotional offer or package deal. Make it descriptive and customer-friendly.',
                'description'     => 'Service offer name generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'serviceoffer',
                'field_type'      => 'description',
                'prompt_template' => 'Write a persuasive description for a beauty spa promotional offer. Include the discount details, included services, terms, and call-to-action. Use promotional marketing language.',
                'description'     => 'Service offer description generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'membership',
                'field_type'      => 'name',
                'prompt_template' => 'Generate an appealing membership plan name for a beauty spa or salon. Make it sound exclusive, valuable, and attractive to regular customers.',
                'description'     => 'Membership name generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'membership',
                'field_type'      => 'benefits',
                'prompt_template' => 'Write a list of attractive benefits for a beauty spa membership plan. Include perks like discounts, priority booking, exclusive services, and loyalty rewards. Make it compelling for customers.',
                'description'     => 'Membership benefits generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'membership',
                'field_type'      => 'description',
                'prompt_template' => 'Write a detailed description for a beauty spa membership plan. Include what the membership includes, pricing overview, duration, and why customers should join. Use persuasive spa marketing language.',
                'description'     => 'Membership description generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'training',
                'field_type'      => 'training_name',
                'prompt_template' => 'Generate a professional and descriptive name for a beauty spa staff training program. Include the skill area or certification focus.',
                'description'     => 'Training name generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'training',
                'field_type'      => 'description',
                'prompt_template' => 'Write a comprehensive training program description for beauty spa staff. Include learning objectives, topics covered, expected outcomes, and certification details. Make it professional and informative.',
                'description'     => 'Training description generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'custompage',
                'field_type'      => 'title',
                'prompt_template' => 'Generate a clear and SEO-friendly page title for a beauty spa website custom page. Make it descriptive and appealing to visitors.',
                'description'     => 'Custom page title generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'custompage',
                'field_type'      => 'description',
                'prompt_template' => 'Write a concise meta description for a beauty spa website custom page. Include key services, unique selling points, and a call-to-action. Keep it under 160 characters for SEO.',
                'description'     => 'Custom page description generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'custompage',
                'field_type'      => 'contents',
                'prompt_template' => 'Write rich, engaging content for a beauty spa website page. Include service highlights, brand story, customer benefits, and a call-to-action. Use warm, inviting spa tone and format with proper headings and paragraphs for web readability.',
                'description'     => 'Custom page contents generation'
            ],
            [
                'module'          => 'beautyspamanagement',
                'submodule'       => 'booking',
                'field_type'      => 'additional_notes',
                'prompt_template' => 'Write helpful additional notes or special requests for a beauty spa booking. Include allergy information, skin concerns, preferred products, or any special accommodations the client may need.',
                'description'     => 'Booking additional notes generation'
            ],
        ];

        foreach ($prompts as $prompt) {
            AIPrompt::firstOrCreate(
                [
                    'module'     => $prompt['module'],
                    'submodule'  => $prompt['submodule'] ?? null,
                    'field_type' => $prompt['field_type']
                ],
                $prompt
            );
        }
    }
}
