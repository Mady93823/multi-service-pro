import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InstallerLayout from '@/layouts/installer-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface DatabaseProps {
    defaults: {
        app_name: string;
        app_url: string;
        host: string;
        port: number;
    };
}

export default function Database({ defaults }: DatabaseProps) {
    const { data, setData, post, processing, errors } = useForm({
        app_name: defaults.app_name,
        app_url: defaults.app_url,
        host: defaults.host,
        port: defaults.port,
        database: '',
        username: '',
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/install/database');
    };

    return (
        <InstallerLayout step={1}>
            <Head title="Install — Database" />
            <Card>
                <CardHeader>
                    <CardTitle>Application & database</CardTitle>
                    <CardDescription>
                        Connection details are written to your .env file. The MySQL database must already exist and be reachable.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="app_name">Platform name</Label>
                            <Input id="app_name" value={data.app_name} onChange={(e) => setData('app_name', e.target.value)} required />
                            <InputError message={errors.app_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="app_url">Application URL</Label>
                            <Input id="app_url" type="url" value={data.app_url} onChange={(e) => setData('app_url', e.target.value)} required />
                            <InputError message={errors.app_url} />
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div className="col-span-2 grid gap-2">
                                <Label htmlFor="host">Database host</Label>
                                <Input id="host" value={data.host} onChange={(e) => setData('host', e.target.value)} required />
                                <InputError message={errors.host} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="port">Port</Label>
                                <Input
                                    id="port"
                                    type="number"
                                    value={data.port}
                                    onChange={(e) => setData('port', Number(e.target.value))}
                                    required
                                />
                                <InputError message={errors.port} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="database">Database name</Label>
                            <Input id="database" value={data.database} onChange={(e) => setData('database', e.target.value)} required />
                            <InputError message={errors.database} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="username">Database username</Label>
                            <Input id="username" value={data.username} onChange={(e) => setData('username', e.target.value)} required />
                            <InputError message={errors.username} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Database password</Label>
                            <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                            <InputError message={errors.password} />
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Test connection & save
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </InstallerLayout>
    );
}
