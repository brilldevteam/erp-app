import { PaginatedData, ModalState, AuthContext } from '@/types/common';



export interface Appointment {
    id: number;
    encrypted_id: string;
    appointment_name: string;
    appointment_type: boolean;
    week_day: string[];
    duration: number;
    phone_enabled: boolean;
    question_ids: string[];
    enabled: boolean;
    created_at: string;
}

export interface CreateAppointmentFormData {
    appointment_name: string;
    appointment_type: boolean;
    week_day: string[];
    duration: string;
    phone_enabled: boolean;
    question_ids: string[];
    enabled: boolean;
}

export interface EditAppointmentFormData {
    appointment_name: string;
    appointment_type: boolean;
    week_day: string[];
    duration: string;
    phone_enabled: boolean;
    question_ids: string[];
    enabled: boolean;
}

export interface AppointmentFilters {
    name: string;
}

export type PaginatedAppointments = PaginatedData<Appointment>;
export type AppointmentModalState = ModalState<Appointment>;

export interface AppointmentsIndexProps {
    appointments: PaginatedAppointments;
    auth: AuthContext;
    [key: string]: unknown;
}

export interface CreateAppointmentProps {
    onSuccess: () => void;
}

export interface EditAppointmentProps {
    appointment: Appointment;
    onSuccess: () => void;
}

export interface ViewAppointmentProps {
    appointment: Appointment;
    onClose: () => void;
}

export interface AppointmentShowProps {
    appointment: Appointment;
    [key: string]: unknown;
}