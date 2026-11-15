import { Form, Head, router, usePage } from '@inertiajs/react';
import { Crown, UserMinus } from 'lucide-react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useCurrentOrganization } from '@/hooks/use-current-organization';
import { organizationSlug } from '@/lib/tenant';
import invitationRoutes from '@/routes/tenant/invitations';
import memberRoutes from '@/routes/tenant/members';
import membershipRoutes from '@/routes/tenant/membership';
import type { OrganizationRole, SharedPageProps } from '@/types';

type Member = {
    public_id: string;
    name: string;
    email: string;
    role: OrganizationRole;
    joined_at: string | null;
    is_current_user: boolean;
    can_remove: boolean;
    assignable_roles: OrganizationRole[];
};

type PendingInvitation = {
    public_id: string;
    email: string;
    role: OrganizationRole;
    invited_by: string | null;
    expires_at: string;
    is_expired: boolean;
    can_manage: boolean;
};

type Props = {
    members: Member[];
    invitations: PendingInvitation[];
    roles: { value: OrganizationRole; label: string }[];
    invitableRoles: OrganizationRole[];
    canInvite: boolean;
    canTransferOwnership: boolean;
    canLeave: boolean;
    seats: { used: number; limit: number | null };
};

const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

export default function Members({
    members,
    invitations,
    roles,
    invitableRoles,
    canInvite,
    canTransferOwnership,
    canLeave,
    seats,
}: Props) {
    const organization = useCurrentOrganization();
    const { errors } = usePage().props as { errors: Record<string, string> };
    const roleLabel = (role: OrganizationRole) =>
        roles.find((r) => r.value === role)?.label ?? role;
    const slug = organization.slug;

    const changeRole = (member: Member, role: string) =>
        router.patch(
            memberRoutes.update.url({
                organization: slug,
                member: member.public_id,
            }),
            { role },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Members" />

            <div className="max-w-4xl space-y-10 px-4 py-6">
                <Heading
                    title="Members"
                    description={
                        seats.limit === null
                            ? `People in ${organization.name}`
                            : `People in ${organization.name} · ${seats.used} of ${seats.limit} seats used`
                    }
                />

                <InputError message={errors.member} />

                {canInvite && (
                    <section className="space-y-4">
                        <Heading
                            variant="small"
                            title="Invite someone"
                            description="They'll get an email with a link to join."
                        />
                        <Form
                            {...invitationRoutes.store.form({
                                organization: slug,
                            })}
                            options={{ preserveScroll: true }}
                            resetOnSuccess={['email']}
                            className="flex flex-col gap-3 sm:flex-row sm:items-start"
                        >
                            {({ processing, errors: formErrors }) => (
                                <>
                                    <div className="grid flex-1 gap-1">
                                        <Label
                                            htmlFor="email"
                                            className="sr-only"
                                        >
                                            Email address
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            placeholder="colleague@example.com"
                                        />
                                        <InputError
                                            message={formErrors.email}
                                        />
                                    </div>
                                    <div className="grid gap-1 sm:w-40">
                                        <Select
                                            name="role"
                                            defaultValue={
                                                invitableRoles.includes(
                                                    'member',
                                                )
                                                    ? 'member'
                                                    : invitableRoles[0]
                                            }
                                        >
                                            <SelectTrigger aria-label="Role">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {invitableRoles.map((role) => (
                                                    <SelectItem
                                                        key={role}
                                                        value={role}
                                                    >
                                                        {roleLabel(role)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={formErrors.role} />
                                    </div>
                                    <Button
                                        disabled={processing}
                                        data-test="invite-member-button"
                                    >
                                        Send invite
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={`Team (${members.length})`}
                    />
                    <ul className="divide-y rounded-lg border">
                        {members.map((member) => (
                            <li
                                key={member.public_id}
                                className="flex flex-wrap items-center gap-3 p-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        {member.name}
                                        {member.is_current_user && (
                                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                                (you)
                                            </span>
                                        )}
                                    </p>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {member.email}
                                    </p>
                                </div>

                                {member.assignable_roles.length > 0 ? (
                                    <Select
                                        value={member.role}
                                        onValueChange={(role) =>
                                            changeRole(member, role)
                                        }
                                    >
                                        <SelectTrigger
                                            className="w-32"
                                            aria-label={`Role of ${member.name}`}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {/* Keep the current role visible even if it can't be assigned. */}
                                            {!member.assignable_roles.includes(
                                                member.role,
                                            ) && (
                                                <SelectItem
                                                    value={member.role}
                                                    disabled
                                                >
                                                    {roleLabel(member.role)}
                                                </SelectItem>
                                            )}
                                            {member.assignable_roles.map(
                                                (role) => (
                                                    <SelectItem
                                                        key={role}
                                                        value={role}
                                                    >
                                                        {roleLabel(role)}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <Badge variant="secondary">
                                        {member.role === 'owner' && (
                                            <Crown className="mr-1 size-3" />
                                        )}
                                        {roleLabel(member.role)}
                                    </Badge>
                                )}

                                <MemberActions
                                    member={member}
                                    slug={slug}
                                    canTransferOwnership={canTransferOwnership}
                                />
                            </li>
                        ))}
                    </ul>
                </section>

                {canInvite && invitations.length > 0 && (
                    <section className="space-y-4">
                        <Heading
                            variant="small"
                            title={`Pending invitations (${invitations.length})`}
                        />
                        <ul className="divide-y rounded-lg border">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.public_id}
                                    className="flex flex-wrap items-center gap-3 p-4"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {invitation.email}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {roleLabel(invitation.role)}
                                            {invitation.invited_by &&
                                                ` · invited by ${invitation.invited_by}`}
                                            {' · '}
                                            {invitation.is_expired
                                                ? 'expired'
                                                : `expires ${formatDate(invitation.expires_at)}`}
                                        </p>
                                    </div>
                                    {invitation.is_expired && (
                                        <Badge variant="destructive">
                                            Expired
                                        </Badge>
                                    )}
                                    {invitation.can_manage && (
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        invitationRoutes.resend.url(
                                                            {
                                                                organization:
                                                                    slug,
                                                                invitation:
                                                                    invitation.public_id,
                                                            },
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Resend
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    router.delete(
                                                        invitationRoutes.destroy.url(
                                                            {
                                                                organization:
                                                                    slug,
                                                                invitation:
                                                                    invitation.public_id,
                                                            },
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <Separator />

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Leave organization"
                        description={
                            canLeave
                                ? `You will lose access to ${organization.name}.`
                                : 'You are the only owner. Transfer ownership to someone else before leaving.'
                        }
                    />
                    <ConfirmDialog
                        trigger={
                            <Button variant="destructive" disabled={!canLeave}>
                                Leave {organization.name}
                            </Button>
                        }
                        title={`Leave ${organization.name}?`}
                        description="You'll need a new invitation to rejoin."
                        confirmLabel="Leave"
                        onConfirm={() =>
                            router.delete(
                                membershipRoutes.destroy.url({
                                    organization: slug,
                                }),
                            )
                        }
                    />
                </section>
            </div>
        </>
    );
}

function MemberActions({
    member,
    slug,
    canTransferOwnership,
}: {
    member: Member;
    slug: string;
    canTransferOwnership: boolean;
}) {
    const canTransfer =
        canTransferOwnership &&
        !member.is_current_user &&
        member.role !== 'owner';

    if (!member.can_remove && !canTransfer) {
        return <div className="w-9" />;
    }

    const target = { organization: slug, member: member.public_id };

    return (
        <div className="flex">
            {canTransfer && (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label={`Transfer ownership to ${member.name}`}
                        >
                            <Crown className="size-4" />
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Transfer ownership to {member.name}?
                        </DialogTitle>
                        <DialogDescription>
                            {member.name} becomes an owner and you become an
                            admin. Enter your password to confirm.
                        </DialogDescription>
                        <Form
                            {...memberRoutes.transferOwnership.form(target)}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`password-${member.public_id}`}
                                            className="sr-only"
                                        >
                                            Password
                                        </Label>
                                        <PasswordInput
                                            id={`password-${member.public_id}`}
                                            name="password"
                                            placeholder="Password"
                                            autoComplete="current-password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button disabled={processing}>
                                            Transfer ownership
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}

            {member.can_remove && (
                <ConfirmDialog
                    trigger={
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label={`Remove ${member.name}`}
                        >
                            <UserMinus className="size-4" />
                        </Button>
                    }
                    title={`Remove ${member.name}?`}
                    description={`${member.name} will lose access to this organization immediately.`}
                    confirmLabel="Remove"
                    onConfirm={() =>
                        router.delete(memberRoutes.destroy.url(target), {
                            preserveScroll: true,
                        })
                    }
                />
            )}
        </div>
    );
}

Members.layout = (props: SharedPageProps) => ({
    breadcrumbs: [
        {
            title: 'Members',
            href: memberRoutes.index({ organization: organizationSlug(props) }),
        },
    ],
});
