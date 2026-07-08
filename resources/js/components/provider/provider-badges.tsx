import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/lib/i18n';
import { type ProviderApprovalStatus, type ProviderDocumentStatus, type ProviderDocumentType } from '@/types';

export function useApprovalStatusLabels(): Record<ProviderApprovalStatus, string> {
    const t = useTrans();

    return {
        pending: t('Pending review'),
        approved: t('Approved'),
        rejected: t('Rejected'),
        suspended: t('Suspended'),
    };
}

export function useDocumentTypeLabels(): Record<ProviderDocumentType, string> {
    const t = useTrans();

    return {
        id_proof: t('ID proof'),
        address_proof: t('Address proof'),
        certificate: t('Trade certificate'),
        photo: t('Profile photo'),
    };
}

export function useDocumentStatusLabels(): Record<ProviderDocumentStatus, string> {
    const t = useTrans();

    return {
        pending: t('Pending review'),
        approved: t('Approved'),
        rejected: t('Rejected'),
    };
}

const APPROVAL_COLORS: Record<ProviderApprovalStatus, string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    suspended: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

const DOCUMENT_COLORS: Record<ProviderDocumentStatus, string> = {
    pending: APPROVAL_COLORS.pending,
    approved: APPROVAL_COLORS.approved,
    rejected: APPROVAL_COLORS.rejected,
};

export function ApprovalStatusBadge({ status }: { status: ProviderApprovalStatus }) {
    const labels = useApprovalStatusLabels();

    return <Badge className={APPROVAL_COLORS[status]}>{labels[status]}</Badge>;
}

export function DocumentStatusBadge({ status }: { status: ProviderDocumentStatus }) {
    const labels = useDocumentStatusLabels();

    return <Badge className={DOCUMENT_COLORS[status]}>{labels[status]}</Badge>;
}
