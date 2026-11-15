import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { store } from '@/routes/organizations';

type Props = {
    isFirstOrganization: boolean;
};

export default function CreateOrganization({ isFirstOrganization }: Props) {
    return (
        <>
            <Head title="Create organization" />
            <Form
                {...store.form()}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Organization name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="organization"
                                    placeholder="Acme Inc."
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">
                                    URL{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    type="text"
                                    placeholder="acme-inc"
                                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                    minLength={3}
                                    maxLength={50}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Lowercase letters, numbers and hyphens.
                                    Generated from the name if left empty.
                                </p>
                                <InputError message={errors.slug} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                data-test="create-organization-button"
                            >
                                {processing && <Spinner />}
                                Create organization
                            </Button>
                        </div>

                        {!isFirstOrganization && (
                            <div className="text-center text-sm text-muted-foreground">
                                <TextLink href={dashboard()}>
                                    Back to dashboard
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>
        </>
    );
}

CreateOrganization.layout = {
    title: 'Create your organization',
    description:
        'Organizations hold your team, data and billing. You can invite teammates next.',
};
