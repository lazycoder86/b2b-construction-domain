import { Head, router } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { restore } from '@/routes/organizations';

type Props = {
    organization: { name: string; slug: string; purge_at: string | null };
};

export default function PendingDeletion({ organization }: Props) {
    const purgeOn = organization.purge_at
        ? new Date(organization.purge_at).toLocaleDateString(undefined, {
              dateStyle: 'long',
          })
        : 'soon';

    return (
        <>
            <Head title="Scheduled for deletion" />
            <div className="flex flex-col gap-6 text-center">
                <p className="text-sm">
                    <strong>{organization.name}</strong> and all of its data
                    will be permanently deleted on <strong>{purgeOn}</strong>.
                    Members can't access it, and its subscription was cancelled.
                </p>
                <Button
                    className="w-full"
                    onClick={() => router.post(restore.url(organization.slug))}
                >
                    Restore {organization.name}
                </Button>
                <TextLink href={dashboard()} className="text-sm">
                    Go to another organization
                </TextLink>
            </div>
        </>
    );
}

PendingDeletion.layout = {
    title: 'Scheduled for deletion',
    description: '',
};
