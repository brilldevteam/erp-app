export type ContractorType = 'main' | 'subcontractor';

export interface ProjectContract {
    id: number;
    project_id: number;
    vendor_id: number;
    type: ContractorType;
    parent_contract_id?: number | null;
    scope_of_work: string;
    contract_value: number;
    amount_paid: number;
    remaining_balance: number;
    work_start_date: string;
    completion_date: string;
    project: { id: number; name: string };
    vendor: { id: number; company_name: string; vendor_code: string; user?: { id: number; name: string } };
    parent_contract?: { id: number; vendor?: { id: number; company_name: string } } | null;
}

export interface ContractorOption {
    id: number;
    project_id: number;
    vendor_id: number;
    scope_of_work: string;
    project: { id: number; name: string };
    vendor: { id: number; company_name: string };
}
