import { Head, Link, router } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { dashboard, login, logout, register } from '@/routes';
import invitations from '@/routes/invitations';
import { send } from '@/routes/verification';

type Status =
    | 'invalid'
    | 'accepted'
    | 'expired'
    | 'guest'
    | 'wrong_account'
    | 'unverified'
    | 'ready';

type Props = {
    status: Status;
    token: string;
    invitation: {
        organization: string;
        role: string;
        invited_by: string | null;
        email: string;
    } | null;
    errors?: { invitation?: string };
};

export default function ShowInvitation({
    status,
    token,
    invitation,
    errors,
}: Props) {
    const summary = invitation && (
        <p className="text-center text-sm">
            {invitation.invited_by ? (
                <>
                    <strong>{invitation.invited_by}</strong> invited{' '}
                </>
            ) : (
                'You were invited '
            )}
            <strong>{invitation.email}</strong> to join{' '}
            <strong>{invitation.organization}</strong> as{' '}
            <strong>{invitation.role}</strong>.
        </p>
    );

    return (
        <>
            <Head title="Invitation" />
            <div className="flex flex-col gap-6">
                {status === 'invalid' && (
                    <Message>
                        This invitation link is not valid. It may have been
                        cancelled or replaced by a newer one.
                    </Message>
                )}

                {status === 'accepted' && (
                    <Message>This invitation has already been used.</Message>
                )}

                {status === 'expired' && (
                    <>
                        {summary}
                        <Message>
                            This invitation has expired. Ask the person who
                            invited you to send a new one.
                        </Message>
                    </>
                )}

                {status === 'guest' && (
                    <>
                        {summary}
                        <Button asChild className="w-full">
                            <Link href={register()}>
                                Create an account to accept
                            </Link>
                        </Button>
                        <p className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()}>Log in</TextLink>
                        </p>
                    </>
                )}

                {status === 'wrong_account' && invitation && (
                    <>
                        {summary}
                        <Message>
                            You're signed in with a different email address. Log
                            in as {invitation.email} to accept.
                        </Message>
                        <Button
                            variant="secondary"
                            className="w-full"
                            onClick={() =>
                                router.post(
                                    logout.url(),
                                    {},
                                    {
                                        onSuccess: () =>
                                            router.visit(
                                                invitations.show.url(token),
                                            ),
                                    },
                                )
                            }
                        >
                            Log out and switch account
                        </Button>
                    </>
                )}

                {status === 'unverified' && (
                    <>
                        {summary}
                        <Message>
                            Verify your email address to accept this invitation.
                            Check your inbox for the verification link.
                        </Message>
                        <Button asChild variant="secondary" className="w-full">
                            <Link href={send()} method="post" as="button">
                                Resend verification email
                            </Link>
                        </Button>
                    </>
                )}

                {status === 'ready' && (
                    <>
                        {summary}
                        {errors?.invitation && (
                            <Message>{errors.invitation}</Message>
                        )}
                        <Button
                            className="w-full"
                            data-test="accept-invitation-button"
                            onClick={() =>
                                router.post(invitations.accept.url(token))
                            }
                        >
                            Accept invitation
                        </Button>
                        <Button
                            variant="ghost"
                            className="w-full"
                            onClick={() =>
                                router.post(invitations.decline.url(token))
                            }
                        >
                            Decline
                        </Button>
                    </>
                )}

                {status !== 'guest' && status !== 'ready' && (
                    <p className="text-center text-sm">
                        <TextLink href={dashboard()}>Go to dashboard</TextLink>
                    </p>
                )}
            </div>
        </>
    );
}

function Message({ children }: { children: React.ReactNode }) {
    return (
        <p className="rounded-md border bg-muted/50 p-4 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

ShowInvitation.layout = {
    title: 'Organization invitation',
    description: '',
};
