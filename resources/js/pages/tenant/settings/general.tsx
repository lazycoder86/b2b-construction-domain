import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import OrganizationSettingsController from '@/actions/App/Http/Controllers/Tenant/OrganizationSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrentOrganization } from '@/hooks/use-current-organization';
import { organizationSlug } from '@/lib/tenant';
import { edit as editSecurity } from '@/routes/security';
import organizationRoutes from '@/routes/tenant/organization';
import { edit } from '@/routes/tenant/settings';
import securityRoutes from '@/routes/tenant/settings/security';
import type { SharedPageProps } from '@/types';

type Props = {
    organization: {
        name: string;
        slug: string;
    };
    canUpdate: boolean;
    canExport: boolean;
    canDelete: boolean;
    deletionGraceDays: number;
    security: {
        require_two_factor: boolean;
        can_manage: boolean;
        user_has_mfa: boolean;
    };
};

export default function OrganizationSettings({
    organization,
    canUpdate,
    canExport,
    canDelete,
    deletionGraceDays,
    security,
}: Props) {
    const current = useCurrentOrganization();
    const [requireTwoFactor, setRequireTwoFactor] = useState(
        security.require_two_factor,
    );

    return (
        <>
            <Head title="Organization settings" />

            <div className="px-4 py-6">
                <Heading
                    title="Organization settings"
                    description="Manage your organization's name and URL"
                />

                <Form
                    {...OrganizationSettingsController.update.form({
                        organization: current.slug,
                    })}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <fieldset disabled={!canUpdate} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={organization.name}
                                    required
                                    autoComplete="organization"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">URL</Label>
                                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                    <span>/o/</span>
                                    <Input
                                        className="text-foreground"
                                        id="slug"
                                        name="slug"
                                        defaultValue={organization.slug}
                                        required
                                        pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                        minLength={3}
                                        maxLength={50}
                                    />
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Changing this breaks existing links to this
                                    organization.
                                </p>
                                <InputError message={errors.slug} />
                            </div>

                            {canUpdate ? (
                                <Button
                                    disabled={processing}
                                    data-test="update-organization-button"
                                >
                                    Save
                                </Button>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Only owners and admins can change these
                                    settings.
                                </p>
                            )}
                        </fieldset>
                    )}
                </Form>

                <div className="mt-12 max-w-xl space-y-6">
                    <Heading
                        variant="small"
                        title="Security"
                        description="Policies that apply to every member"
                    />

                    <Form
                        {...securityRoutes.update.form({
                            organization: current.slug,
                        })}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <fieldset
                                disabled={!security.can_manage}
                                className="space-y-4"
                            >
                                {/* Always submit an explicit 1/0 (unchecked boxes send nothing). */}
                                <input
                                    type="hidden"
                                    name="require_two_factor"
                                    value={requireTwoFactor ? '1' : '0'}
                                />
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="require_two_factor"
                                        checked={requireTwoFactor}
                                        onCheckedChange={(checked) =>
                                            setRequireTwoFactor(
                                                checked === true,
                                            )
                                        }
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="require_two_factor">
                                            Require two-factor authentication
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Members without an authenticator app
                                            or passkey will be asked to set one
                                            up before they can continue.
                                        </p>
                                        {security.can_manage &&
                                            !security.user_has_mfa && (
                                                <p className="text-sm text-muted-foreground">
                                                    You need to{' '}
                                                    <TextLink
                                                        href={editSecurity()}
                                                    >
                                                        secure your own account
                                                    </TextLink>{' '}
                                                    first.
                                                </p>
                                            )}
                                    </div>
                                </div>
                                <InputError
                                    message={errors.require_two_factor}
                                />

                                {security.can_manage ? (
                                    <Button
                                        disabled={processing}
                                        variant="outline"
                                    >
                                        Save security settings
                                    </Button>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Only owners can change security
                                        policies.
                                    </p>
                                )}
                            </fieldset>
                        )}
                    </Form>
                </div>

                {(canExport || canDelete) && (
                    <div className="mt-12 max-w-xl space-y-6">
                        <Heading
                            variant="small"
                            title="Data"
                            description="Export or permanently delete this organization"
                        />

                        {canExport && (
                            <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                                <p className="text-sm text-muted-foreground">
                                    Download everything this organization owns.
                                    We'll email you a link when the archive is
                                    ready.
                                </p>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            organizationRoutes.export.url({
                                                organization: current.slug,
                                            }),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Export data
                                </Button>
                            </div>
                        )}

                        {canDelete && (
                            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                                <div className="space-y-1 text-red-600 dark:text-red-100">
                                    <p className="font-medium">
                                        Delete organization
                                    </p>
                                    <p className="text-sm">
                                        The subscription is cancelled
                                        immediately and members lose access.
                                        Owners can restore it for{' '}
                                        {deletionGraceDays} days; after that all
                                        data is permanently deleted.
                                    </p>
                                </div>
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button variant="destructive">
                                            Delete organization
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>
                                            Delete {current.name}?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Type <strong>{current.slug}</strong>{' '}
                                            and your password to confirm.
                                        </DialogDescription>
                                        <Form
                                            {...organizationRoutes.destroy.form(
                                                {
                                                    organization: current.slug,
                                                },
                                            )}
                                            className="space-y-4"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="confirmation">
                                                            Organization URL
                                                        </Label>
                                                        <Input
                                                            id="confirmation"
                                                            name="confirmation"
                                                            autoComplete="off"
                                                            placeholder={
                                                                current.slug
                                                            }
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.confirmation
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="delete-password">
                                                            Password
                                                        </Label>
                                                        <PasswordInput
                                                            id="delete-password"
                                                            name="password"
                                                            autoComplete="current-password"
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.password
                                                            }
                                                        />
                                                    </div>
                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            variant="destructive"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Delete organization
                                                        </Button>
                                                    </DialogFooter>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

OrganizationSettings.layout = (props: SharedPageProps) => ({
    breadcrumbs: [
        {
            title: 'Organization settings',
            href: edit({ organization: organizationSlug(props) }),
        },
    ],
});
