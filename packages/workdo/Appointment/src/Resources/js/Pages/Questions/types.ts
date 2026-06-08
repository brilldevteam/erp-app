import { PaginatedData, ModalState, AuthContext } from '@/types/common';



export interface Question {
    id: number;
    question_name: string;
    question_type: boolean;
    available_answers: any;
    required_answer: boolean;
    enabled: boolean;
    created_at: string;
}

export interface CreateQuestionFormData {
    question_name: string;
    question_type: boolean;
    available_answers: any;
    required_answer: boolean;
    enabled: boolean;
}

export interface EditQuestionFormData {
    question_name: string;
    question_type: boolean;
    available_answers: any;
    required_answer: boolean;
    enabled: boolean;
}

export interface QuestionFilters {
    question_name: string;
    question_type: string;
    required_answer: string;
    enabled: string;
}

export type PaginatedQuestions = PaginatedData<Question>;
export type QuestionModalState = ModalState<Question>;

export interface QuestionsIndexProps {
    questions: PaginatedQuestions;
    auth: AuthContext;
    [key: string]: unknown;
}

export interface CreateQuestionProps {
    onSuccess: () => void;
}

export interface EditQuestionProps {
    question: Question;
    onSuccess: () => void;
}

export interface QuestionShowProps {
    question: Question;
    [key: string]: unknown;
}