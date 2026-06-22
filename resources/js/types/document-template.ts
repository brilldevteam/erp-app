export type DocumentTemplateType = 'quotation' | 'invoice';
export type DocumentTemplateStatus = 'active' | 'inactive';

export interface DocumentTemplateConfig {
    header: {
        showLogo: boolean;
        showCompanyName: boolean;
        showCompanyAddress: boolean;
        showContactDetails: boolean;
        alignment: 'left' | 'center' | 'right';
    };
    customerBlock: {
        showBillingAddress: boolean;
        showShippingAddress: boolean;
        showContactPerson: boolean;
    };
    documentDetails: {
        showDocumentNumber: boolean;
        showDocumentDate: boolean;
        showDueDate: boolean;
    };
    itemsTable: {
        columns: string[];
    };
    totals: {
        showSubtotal: boolean;
        showDiscount: boolean;
        showTax: boolean;
        showGrandTotal: boolean;
    };
    footer: {
        showTerms: boolean;
        showNotes: boolean;
        showBankDetails: boolean;
        showSignature: boolean;
        footerText: string;
    };
}

export interface DocumentTemplate {
    id: number;
    company_id: number;
    name: string;
    type: DocumentTemplateType;
    status: DocumentTemplateStatus;
    is_default: boolean;
    primary_color: string;
    logo_url?: string | null;
    config_json: DocumentTemplateConfig;
    terms?: string | null;
    notes?: string | null;
    bank_details?: string | null;
    signature_text?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface TemplateSampleDocument {
    type: DocumentTemplateType;
    template: Partial<DocumentTemplate>;
    company: {
        name: string;
        address?: string;
        city?: string;
        country?: string;
        phone?: string;
        email?: string;
        logo?: string | null;
    };
    customer: {
        name: string;
        contact_person?: string;
        email?: string;
        billing_address?: string[];
        shipping_address?: string[];
    };
    number: string;
    date: string;
    due_date: string;
    items: Array<Record<string, any>>;
    totals: {
        subtotal: number;
        discount: number;
        tax: number;
        grand_total: number;
    };
}
