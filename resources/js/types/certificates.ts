export type CertificateStatus = 'claimed' | 'claimable' | 'not_eligible';

export type CertificateRequirement = {
    key: 'lessons' | 'assignments' | 'date';
    label: string;
    met: boolean;
};

/** Owner-facing certificate summary (BuildCertificateCard::certificate). */
export type OwnedCertificate = {
    id: number;
    number: string;
    recipient_name: string;
    course_title: string;
    batch_name: string | null;
    issued_at: string;
    pdf_ready: boolean;
    download_url: string;
    verification_url: string;
    verification_code: string;
};

/** "Sertifikat" card on the learning overview. */
export type CertificateCard = {
    enabled: boolean;
    status: CertificateStatus;
    eligible: boolean;
    blocker: string | null;
    reason: string | null;
    requirements: CertificateRequirement[];
    opens_at: string | null;
    default_name: string;
    certificate: OwnedCertificate | null;
};

export type VerificationResult =
    | { status: 'not_found' }
    | {
          status: 'valid' | 'revoked';
          number: string;
          recipient_name: string;
          course_title: string;
          batch_name: string | null;
          issued_at: string;
          revoked_at: string | null;
      };

export type AdminCertificate = {
    id: number;
    number: string;
    verification_code: string;
    verification_url: string;
    recipient_name: string;
    course_title: string;
    batch_name: string | null;
    student: { name: string; email: string } | null;
    issued_at: string;
    is_manual: boolean;
    issued_by: string | null;
    issue_reason: string | null;
    status: 'active' | 'revoked';
    revoked_at: string | null;
    revoked_by: string | null;
    revoke_reason: string | null;
    pdf_ready: boolean;
    download_url: string;
};

export type CertificateSettings = {
    institution_name: string;
    title: string;
    statement: string;
    signer_name: string;
    signer_title: string;
};
