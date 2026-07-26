import { PaginatedData, ModalState, AuthContext, CreateProps, EditProps } from '@/types/common';
import { CountryAddress } from '@/types/address';

export interface ProjectPropertyInformation extends CountryAddress {
    plot_number: string;
    property_number: string;
    location_url: string;
}

export const emptyProjectPropertyInformation = (): ProjectPropertyInformation => ({
    country: '',
    country_code: '',
    plot_number: '',
    property_number: '',
    location_url: '',
});

export interface Project {
    id: number;
    name: string;
    description?: string;
    budget?: number;
    start_date?: string;
    end_date?: string;
    status: 'Ongoing' | 'Onhold' | 'Finished';
    property_information?: ProjectPropertyInformation | null;
    team_members?: Array<{
        id: number;
        name: string;
    }>;
    created_at: string;
}

export interface ProjectFormData {
    name: string;
    description?: string;
    budget?: number;
    start_date?: string;
    end_date?: string;
    status: 'Ongoing' | 'Onhold' | 'Finished';
    user_ids?: number[];
    property_information: ProjectPropertyInformation;
}

export interface CreateProjectProps extends CreateProps {
    users: Array<{
        id: number;
        name: string;
    }>;
}

export interface EditProjectProps extends EditProps<Project> {
    users: Array<{
        id: number;
        name: string;
    }>;
}

export interface ProjectFilters {
    name: string;
    status: string;
}

export type PaginatedProjects = PaginatedData<Project>;
export type ProjectModalState = ModalState<Project>;

export interface ProjectsIndexProps {
    items: PaginatedProjects;
    users: Array<{
        id: number;
        name: string;
    }>;
    auth: AuthContext;
    [key: string]: unknown;
}

export interface ProjectFormErrors {
    name?: string;
    description?: string;
    budget?: string;
    start_date?: string;
    end_date?: string;
    status?: string;
    user_ids?: string;
    property_information?: string;
    [key: `property_information.${string}`]: string | undefined;
}
