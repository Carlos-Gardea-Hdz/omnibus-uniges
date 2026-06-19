export enum GraduationStatus { FormBPending = 'form_b_pending', FormBReview = 'form_b_review', FormBRejected = 'form_b_rejected', AnnexesPending = 'annexes_pending', AnnexIiiPending = 'annex_iii_pending', PaymentPending = 'payment_pending', JuryAssigned = 'jury_assigned', CeremonyScheduled = 'ceremony_scheduled', Graduated = 'graduated' };
export type LoginData = {
email: string;
password: string;
remember: boolean;
};
export type ReviewFormBData = {
observations: string;
};
export type SubmitFormBData = {
control_number: string;
first_name: string;
last_name: string;
gender: string;
gpa: number;
enrollment_date: string;
program_id: number;
graduation_type_id: number;
study_plan_id: number;
mother_last_name: string | null;
thesis_title: string | null;
thesis_abstract: string | null;
advisor_id: number | null;
phone: string | null;
mobile: string | null;
age: number | null;
address_street: string | null;
address_neighborhood: string | null;
address_ext_number: string | null;
address_int_number: string | null;
address_postal_code: number | null;
};
export enum UserRole { Student = 'student', Admin = 'admin', SuperAdmin = 'super_admin', Secretary = 'secretary', AssistantSecretary = 'assistant_secretary', SchoolServices = 'school_services' };
