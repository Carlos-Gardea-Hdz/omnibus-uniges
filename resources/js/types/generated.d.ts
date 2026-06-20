export type AssignJuryData = {
president_professor_id: number;
secretary_professor_id: number;
vocal_professor_id: number;
substitute_professor_id: number | null;
};
export type DemoLoginData = {
preset: DemoPreset;
};
export enum DemoPreset { Sustentante1 = 'sustentante_1', Sustentante2 = 'sustentante_2', Sustentante3 = 'sustentante_3', Sustentante4 = 'sustentante_4', Personal = 'personal', Admin = 'admin' };
export type DepartmentData = {
code: string;
name: string;
};
export enum DocumentStatus { Pending = 'pending', Uploaded = 'uploaded', Approved = 'approved', Rejected = 'rejected' };
export enum GraduationStatus { FormBPending = 'form_b_pending', FormBReview = 'form_b_review', FormBRejected = 'form_b_rejected', AnnexesPending = 'annexes_pending', AnnexIiiPending = 'annex_iii_pending', PaymentPending = 'payment_pending', JuryAssigned = 'jury_assigned', CeremonyScheduled = 'ceremony_scheduled', Graduated = 'graduated' };
export type GraduationTypeData = {
code: string;
name: string;
requires_advisor: boolean;
required_document_ids: Array<any>;
};
export enum JuryRole { President = 'president', Secretary = 'secretary', Vocal = 'vocal', Substitute = 'substitute' };
export type LoginData = {
email: string;
password: string;
remember: boolean;
};
export type ProfessorData = {
first_name: string;
last_name: string;
email: string;
mother_last_name: string | null;
};
export type ProgramData = {
code: string;
name: string;
department_id: number;
};
export type RequiredDocumentData = {
name: string;
allowed_mimes: string;
max_size_kb: number;
description: string | null;
};
export type ReviewDocumentData = {
rejection_reason: string;
};
export type ReviewFormBData = {
observations: string;
};
export type ScheduleCeremonyData = {
ceremony_date: string;
ceremony_location: string;
};
export type StudyPlanData = {
code: string;
name: string;
program_id: number;
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
export type SubmitPaymentData = {
payment_reference: string;
};
export type UploadDocumentData = {
required_document_id: number;
file: any;
};
export enum UserRole { Student = 'student', Admin = 'admin', SuperAdmin = 'super_admin', Secretary = 'secretary', AssistantSecretary = 'assistant_secretary', SchoolServices = 'school_services' };
