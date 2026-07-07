import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InstallerLayout from '@/layouts/installer-layout';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';

interface RequirementsProps {
    requirements: {
        passed: boolean;
        checks: { label: string; passed: boolean; detail: string }[];
    };
}

export default function Requirements({ requirements }: RequirementsProps) {
    return (
        <InstallerLayout step={0}>
            <Head title="Install — Requirements" />
            <Card>
                <CardHeader>
                    <CardTitle>Server requirements</CardTitle>
                    <CardDescription>Everything below must pass before installation can begin.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <ul className="divide-y text-sm">
                        {requirements.checks.map((check) => (
                            <li key={check.label} className="flex items-center justify-between py-2">
                                <span>{check.label}</span>
                                <span className="flex items-center gap-2">
                                    <span className="text-muted-foreground">{check.detail}</span>
                                    {check.passed ? (
                                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                                    ) : (
                                        <XCircle className="text-destructive h-4 w-4" />
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>

                    <div className="flex items-center justify-between">
                        <Button variant="outline" onClick={() => router.reload()}>
                            Re-check
                        </Button>
                        <Button asChild disabled={!requirements.passed}>
                            <Link href="/install/database">Continue</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </InstallerLayout>
    );
}
