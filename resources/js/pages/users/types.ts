import { PaginatedData, ModalState, AuthContext, CreateProps, EditProps } from '@/types/common';

export interface User {
    id: number;
    name: string;
    email: string;
    mobile_no: string;
    role: string;
    type: string;
    is_enable_login: boolean;
    is_disable?: number;
    is_online?: number;
    avatar?: string;
    active_plan?: number | null;
    created_at: string;
}

export interface UserPlan {
    id: number;
    name: string;
    description?: string | null;
    number_of_users: number;
    storage_limit: number;
    modules?: string[] | null;
    free_plan: boolean;
    trial: boolean;
    trial_days: number;
    package_price_monthly: number;
    package_price_yearly: number;
}

export interface CreateUserFormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    mobile_no: string;
    type: string;
    is_enable_login: boolean;
}

export interface EditUserFormData {
    name: string;
    email: string;
    mobile_no: string;
    is_enable_login: boolean;
    plan_id: string;
    plan_duration: 'Month' | 'Year';
    plan_changed: boolean;
}

export interface ChangePasswordFormData {
    password: string;
    password_confirmation: string;
}

export interface CreateUserProps extends CreateProps {
    roles?: Record<string, string>;
}

export interface EditUserProps {
    user: User;
    onSuccess: () => void;
    roles?: Record<string, string>;
    plans?: UserPlan[];
}

export interface ChangePasswordProps {
    user: User;
    onSuccess: () => void;
}

export interface ChangePlanProps {
    user: User;
    plans: UserPlan[];
    onSuccess: () => void;
}

export interface UserFilters {
    name: string;
    email: string;
    role: string;
    is_enable_login: string;
}

export type PaginatedUsers = PaginatedData<User>;
export interface UserModalState {
    isOpen: boolean;
    mode: '' | 'add' | 'edit' | 'change-password' | 'change-plan';
    data: User | null;
}

export interface UsersIndexProps {
    users: PaginatedUsers;
    roles: Record<string, string>;
    plans: UserPlan[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface UserFormErrors {
    name?: string;
    email?: string;
    password?: string;
    password_confirmation?: string;
    mobile_no?: string;
    type?: string;
    is_enable_login?: string;
}
