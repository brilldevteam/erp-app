import { PaginatedData, ModalState, AuthContext } from '@/types/common';
import { HelpdeskCategory } from '../categories/types';

export interface HelpdeskTicket {
    id: number;
    ticket_id: string;
    title: string;
    description: string;
    status: 'open' | 'in_progress' | 'waiting_customer' | 'waiting_internal' | 'resolved' | 'closed';
    priority: 'low' | 'medium' | 'high' | 'urgent';
    category_id: number;
    category?: HelpdeskCategory;
    creator_id: number;
    creator?: {
        id: number;
        name: string;
        email: string;
    };
    requester_user_id?: number | null;
    requester?: {
        id: number;
        name: string;
        email: string;
    } | null;
    assigned_agent_id?: number | null;
    assigned_agent?: {
        id: number;
        name: string;
        email: string;
    } | null;
    status_histories?: Array<{ id: number; from_status: string | null; to_status: string; changed_by_name: string | null; created_at: string }>;
    assignment_histories?: Array<{ id: number; from_agent_name: string | null; to_agent_name: string | null; changed_by_name: string | null; created_at: string }>;
    resolved_at?: string;
    first_response_at?: string;
    closed_at?: string;
    last_reply_at?: string;
    created_at: string;
    replies?: HelpdeskReply[];
}

export interface HelpdeskReply {
    id: number;
    ticket_id: number;
    message: string;
    attachments?: string[];
    files?: Array<{ id: number; original_name: string; mime_type?: string | null; size: number }>;
    is_internal: boolean;
    created_by: number;
    creator?: {
        id: number;
        name: string;
        email: string;
    };
    sender_name?: string | null;
    created_at: string;
}

export interface CreateHelpdeskTicketFormData {
    title: string;
    description: string;
    priority: 'low' | 'medium' | 'high' | 'urgent';
    category_id: number;
    company_id?: number | null;
}

export interface EditHelpdeskTicketFormData {
    title: string;
    description: string;
    status: 'open' | 'in_progress' | 'waiting_customer' | 'waiting_internal' | 'resolved' | 'closed';
    priority: 'low' | 'medium' | 'high' | 'urgent';
    category_id: number;
}

export interface CreateHelpdeskReplyFormData {
    message: string;
    attachments?: string[];
    is_internal: boolean;
}

export interface HelpdeskTicketFilters {
    title: string;
    status: string;
    priority: string;
    category_id: string;
    company_id: string;
}

export type PaginatedHelpdeskTickets = PaginatedData<HelpdeskTicket>;
export type HelpdeskTicketModalState = ModalState<HelpdeskTicket>;

export interface HelpdeskTicketsIndexProps {
    tickets: PaginatedHelpdeskTickets;
    categories: HelpdeskCategory[];
    companies: { id: number; name: string; }[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface CreateHelpdeskTicketProps {
    categories: HelpdeskCategory[];
    companies: { id: number; name: string; }[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface EditHelpdeskTicketProps {
    ticket: HelpdeskTicket;
    categories: HelpdeskCategory[];
    onSuccess: () => void;
}

export interface TodayTicketsProps {
    tickets: HelpdeskTicket[];
    stats: {
        total: number;
        urgent: number;
        high: number;
        medium: number;
        low: number;
        open: number;
        in_progress: number;
        waiting_customer: number;
        waiting_internal: number;
    };
    categories: HelpdeskCategory[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface ShowHelpdeskTicketProps {
    ticket: HelpdeskTicket;
    supportAgents: Array<{ id: number; name: string; email: string }>;
    canChangeStatus: boolean;
    auth: AuthContext;
    [key: string]: unknown;
}
