export enum GraduationStatus { FormBPending = 'form_b_pending', FormBReview = 'form_b_review', FormBRejected = 'form_b_rejected', AnnexesPending = 'annexes_pending', AnnexIiiPending = 'annex_iii_pending', PaymentPending = 'payment_pending', JuryAssigned = 'jury_assigned', CeremonyScheduled = 'ceremony_scheduled', Graduated = 'graduated' };
export type LoginData = {
email: string;
password: string;
remember: boolean;
};
export enum UserRole { Student = 'student', Admin = 'admin', SuperAdmin = 'super_admin', Secretary = 'secretary', AssistantSecretary = 'assistant_secretary', SchoolServices = 'school_services' };
