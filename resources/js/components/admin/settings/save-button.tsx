import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { LoaderCircle } from 'lucide-react';

export default function SaveButton({ processing }: { processing: boolean }) {
    const t = useTrans();

    return (
        <Button type="submit" disabled={processing}>
            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
            {t('Save settings')}
        </Button>
    );
}
