import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

const Toaster = ({ ...props }: ToasterProps) => {
    const { appearance } = useAppearance();

    return <Sonner theme={appearance} className="toaster group" {...props} />;
};

export { Toaster };
