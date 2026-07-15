import { PaginatedData, ModalState, AuthContext } from '@/types/common';
import { TimeClockDeviceAccess } from '../../Hooks/useTimeClockDeviceAccess';

export interface Employee {
    id: number;
    employee_id: string;
    user?: {
        id: number;
        name: string;
    };
    rate_per_hour?: number;
}

export interface Shift {
    id: number;
    shift_name: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface Attendance {
    id: number;
    employee_id: number;
    shift_id: number;
    date: string;
    clock_in: string;
    clock_out?: string;
    break_hour?: number;
    total_hour?: number;
    overtime_hours?: number;
    overtime_amount?: number;
    status: 'present' | 'half_day' | 'absent';
    notes?: string;
    work_status?: 'working' | 'paused' | 'completed';
    elapsed_seconds?: number;
    unpaid_pause_seconds?: number;
    paid_outside_seconds?: number;
    worked_seconds?: number;
    work_update?: string;
    is_abnormally_long?: boolean;
    is_manual?: boolean;
    intervals?: Array<{ id: number; reason: string; details?: string; counts_as_work: boolean; started_at: string; ended_at?: string }>;
    correction_requests?: any[];
    action_logs?: any[];
    user?: User;
    employee?: Employee;
    shift?: Shift;
    created_at: string;
}

export interface CreateAttendanceFormData {
    employee_id: string;
    date: string;
    clock_in: string;
    clock_out: string;
    break_hour: string;
    notes: string;
}

export interface EditAttendanceFormData {
    employee_id: string;
    date: string;
    clock_in: string;
    clock_out: string;
    break_hour: string;
    notes: string;
}

export interface AttendanceFilters {
    search: string;
    status: string;
    employee_id: string;
    date_from: string;
    date_to: string;
    work_status: string;
    branch_id: string;
    department_id: string;
    abnormal: string;
}

export interface EmployeeAttendanceSummary {
    id: number;
    user_id: number;
    employee_id: string;
    name: string;
    avatar?: string;
    branch?: string;
    department?: string;
    shift?: string;
    record_count: number;
    present_count: number;
    half_day_count: number;
    absent_count: number;
    latest_attendance_date?: string;
    current_clock_state?: 'working' | 'paused' | 'completed';
}

export type PaginatedAttendances = PaginatedData<Attendance>;
export type AttendanceModalState = ModalState<Attendance>;

export interface AttendancesIndexProps {
    attendances: PaginatedAttendances;
    attendanceView?: 'employees' | 'records';
    employeeAttendanceSummaries?: PaginatedData<EmployeeAttendanceSummary> | null;
    auth: AuthContext;
    employees: any[];
    shifts: any[];
    branches?: any[];
    departments?: any[];
    clockStatus?: any;
    timeClockDeviceAccess?: TimeClockDeviceAccess;
    [key: string]: unknown;
}

export interface CreateAttendanceProps {
    onSuccess: () => void;
}

export interface EditAttendanceProps {
    attendance: Attendance;
    onSuccess: () => void;
}

export interface AttendanceShowProps {
    attendance: Attendance;
    [key: string]: unknown;
}
