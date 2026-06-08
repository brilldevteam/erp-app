import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { FileDown } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDate } from '@/utils/helpers';
import { toast } from 'sonner';
import axios from 'axios';

export const invoiceActionButtons = (data?: any) => {
    const { t } = useTranslation();
    
    if (!data?.invoice_id) {
        return [];
    }
    
    // Check if user has permission to manage e-invoices
    if (!data?.auth?.user?.permissions?.includes('manage-einvoice')) {
        return [];
    }

    const handleDownload = async () => {
        try {
            const response = await axios.get(route('invoice.download', data.invoice_id), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const xmlData = response.data;
           
            // Generate XML content
            const xmlContent = generateXML(xmlData);
            
            // Download XML file
            const blob = new Blob([xmlContent], { type: 'application/xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${xmlData.invoiceNumber}.xml`;
            a.click();
            URL.revokeObjectURL(url);
        } catch (error) {
            
            if (error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error('Failed to generate E-Invoice. Please try again.');
            }
        }
    };

    const generateXML = (data: any) => {
        const { invoice, customer, totalTaxPrice, totalTaxRate, productname, invoiceNumber, currency, companySettings } = data;
        
        return `<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>
    <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
    <cbc:ID>${invoiceNumber}</cbc:ID>
    <cbc:IssueDate>${formatDate(invoice.invoice_date, 'YYYY-MM-DD')}</cbc:IssueDate>
    <cbc:DueDate>${formatDate(invoice.due_date, 'YYYY-MM-DD')}</cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>${currency}</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>${currency}</cbc:TaxCurrencyCode>
    <cbc:BuyerReference>${invoiceNumber}</cbc:BuyerReference>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:EndpointID schemeID="${companySettings.electronic_address_schema}">${companySettings.electronic_address}</cbc:EndpointID>            
            <cac:PostalAddress>
                <cbc:StreetName>${customer.billing_address?.address || ''}</cbc:StreetName>
                <cbc:CityName>${customer.billing_address?.city || ''}</cbc:CityName>
                <cbc:PostalZone>${customer.billing_address?.zip_code || ''}</cbc:PostalZone>
                <cac:Country>
                    <cbc:IdentificationCode>${(customer.billing_address?.country || '').substring(0, 2).toUpperCase()}</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>${companySettings.tax_number || ''}</cbc:CompanyID>
                <cac:TaxScheme>
                    <cbc:ID>${companySettings.tax_type || ''}</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>${customer.company_name || customer.contact_person_name || 'Customer Name'}</cbc:RegistrationName>
                <cbc:CompanyID schemeID="${companySettings.company_id_schema || ''}">${companySettings.company_id || ''}</cbc:CompanyID>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cbc:EndpointID schemeID="${customer.electronic_address_scheme || ''}">${customer.electronic_address || ''}</cbc:EndpointID>
            <cac:PostalAddress>
                <cac:Country>
                    <cbc:IdentificationCode />
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>${customer.company_name || customer.contact_person_name || 'Customer Name'}</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="${currency}">${totalTaxPrice || ''}</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="${currency}">${invoice.subtotal || ''}</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="${currency}">${totalTaxPrice || ''}</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>${totalTaxRate || ''}</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID>${companySettings.tax_type || ''}</cbc:ID>
                </cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="${currency}">${invoice.subtotal || ''}</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="${currency}">${invoice.subtotal || ''}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="${currency}">${invoice.total_amount || ''}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="${currency}">${invoice.total_amount || ''}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="${currency}">${invoice.subtotal || ''}</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Name>${productname || ''}</cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>${totalTaxRate || ''}</cbc:Percent>
                <cac:TaxScheme>
                    <cbc:ID>${companySettings.tax_type || ''}</cbc:ID>
                </cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="${currency}">${invoice.total_amount || ''}</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
</Invoice>`;
    };

    return [{
        id: 'einvoice-download',
        order: 10,
        component: (
            <Tooltip delayDuration={0}>
                <TooltipTrigger asChild>
                    <Button
                        key="einvoice-download"
                        onClick={handleDownload}
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700"
                    >
                        <FileDown className="h-4 w-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{t('Generate E-invoice')}</p>
                </TooltipContent>
            </Tooltip>
        )
    }];
};