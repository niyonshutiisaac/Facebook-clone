import { useRef, useState } from 'react';
import { Avatar } from '@/components/atoms/Avatar';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Spinner } from '@/components/atoms/Spinner';
import { Menu } from '@/components/molecules/Menu';
import { Modal } from '@/components/molecules/Modal';
import { useCreatePost } from '@/api/queries';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils';
import type { Audience } from '@/types/api';
import { AUDIENCES } from '@/config/audiences';

/**
 * The composer.
 *
 * Two surfaces share one body: the inline "What's on your mind?" row on the
 * feed and the full modal opened by clicking it. Facebook keeps the modal for
 * anything with attachments; the inline row only ever produces text.
 */
export function PostComposer({ onCreated }: { onCreated?: () => void }) {
  const { user } = useAuthStore();
  const [open, setOpen] = useState(false);

  if (!user) return null;

  return (
    <>
      <div className="card p-4">
        <div className="flex gap-2">
          <Avatar src={user.avatar_url} name={user.name} size="md" />

          <button
            type="button"
            onClick={() => setOpen(true)}
            className="flex-1 rounded-pill bg-subtle px-4 py-2.5 text-left text-base text-secondary transition-colors hover:bg-hover/[0.06]"
          >
            What&apos;s on your mind, {user.name.split(' ')[0]}?
          </button>
        </div>

        <hr className="my-3 border-divider" />

        <div className="flex items-center justify-between">
          <button
            type="button"
            onClick={() => setOpen(true)}
            className="flex flex-1 items-center justify-center gap-2 rounded-control py-2 text-base font-semibold text-secondary transition-colors hover:bg-hover/[0.05]"
          >
            <Icon name="Video" size={22} className="text-facebook-red" />
            Live video
          </button>

          <button
            type="button"
            onClick={() => setOpen(true)}
            className="flex flex-1 items-center justify-center gap-2 rounded-control py-2 text-base font-semibold text-secondary transition-colors hover:bg-hover/[0.05]"
          >
            <Icon name="Image" size={22} className="text-facebook-green" />
            Photo/video
          </button>

          <button
            type="button"
            onClick={() => setOpen(true)}
            className="flex flex-1 items-center justify-center gap-2 rounded-control py-2 text-base font-semibold text-secondary transition-colors hover:bg-hover/[0.05]"
          >
            <Icon name="Smile" size={22} className="text-[#F7B125]" />
            Feeling/activity
          </button>
        </div>
      </div>

      <ComposerModal open={open} onClose={() => setOpen(false)} onCreated={onCreated} />
    </>
  );
}

function ComposerModal({
  open,
  onClose,
  onCreated,
}: {
  open: boolean;
  onClose: () => void;
  onCreated?: () => void;
}) {
  const { user } = useAuthStore();
  const create = useCreatePost();

  // The modal is only ever rendered from the composer, which already bails out
  // without a user; this keeps TypeScript honest about it.
  if (!user) return <Modal open={open} onClose={onClose} title="Create post"><p className="p-4 text-md text-secondary">Sign in to post.</p></Modal>;

  const [body, setBody] = useState('');
  const [audience, setAudience] = useState<Audience>('friends');
  const [background, setBackground] = useState<string | null>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);

  const audienceMeta = AUDIENCES.find((a) => a.value === audience)!;

  const submit = async () => {
    if (!body.trim()) return;

    await create.mutateAsync({ body: body.trim(), audience });

    setBody('');
    setBackground(null);
    onCreated?.();
    onClose();
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Create post"
      padding={false}
      footer={
        <Button
          variant="primary"
          size="md"
          fullWidth
          loading={create.isPending}
          disabled={!body.trim()}
          onClick={submit}
        >
          Post
        </Button>
      }
    >
      <div className="flex items-center gap-3 px-4 py-3">
        <Avatar src={user.avatar_url} name={user.name} size="md" />

        <div className="min-w-0">
          <Menu
            align="start"
            items={AUDIENCES.map((option) => ({
              label: option.label,
              icon: option.icon,
              onSelect: () => setAudience(option.value),
              content: (
                <span className="flex w-full items-center gap-3">
                  <Icon name={option.icon} size={20} className="text-secondary" />
                  <span className="flex-1">
                    <span className="block font-semibold">{option.label}</span>
                    <span className="block text-sm text-secondary">{option.description}</span>
                  </span>
                  {option.value === audience && <Icon name="Check" size={18} className="text-facebook" />}
                </span>
              ),
            }))}
            trigger={
              <button
                type="button"
                className="flex items-center gap-1.5 rounded-control bg-elevated px-2.5 py-1 text-sm font-semibold hover:bg-hover/[0.08]"
              >
                <Icon name={audienceMeta.icon} size={14} />
                {audienceMeta.label}
                <Icon name="ChevronDown" size={14} />
              </button>
            }
          />
        </div>
      </div>

      <div
        className={cn(
          'min-h-[140px] px-4 pb-2',
          background && 'm-4 rounded-card bg-gradient-to-br p-6',
        )}
        style={
          background
            ? { backgroundImage: `linear-gradient(135deg, ${background.split(',')[0]}, ${background.split(',')[1]})` }
            : undefined
        }
      >
        <textarea
          ref={inputRef}
          value={body}
          onChange={(event) => setBody(event.target.value)}
          placeholder={`What's on your mind, ${user.name.split(' ')[0]}?`}
          className={cn(
            'w-full resize-none bg-transparent outline-none placeholder:text-tertiary',
            background ? 'text-center text-2xl font-bold text-white' : 'text-xl',
          )}
          style={{ minHeight: 120, maxHeight: 300 }}
        />
      </div>

      <div className="mx-4 mb-4 mt-2 flex items-center justify-between rounded-card border border-divider p-3">
        <span className="text-base font-semibold">Add to your post</span>

        <div className="flex items-center gap-1">
          {[
            { icon: 'Image', color: '#45BD62', label: 'Photo/video' },
            { icon: 'UserPlus', color: '#1877F2', label: 'Tag people' },
            { icon: 'Smile', color: '#F7B125', label: 'Feeling/activity' },
            { icon: 'MapPin', color: '#F5533D', label: 'Check in' },
            { icon: 'Mic', color: '#E471DE', label: 'Voice' },
          ].map((action) => (
            <button
              key={action.label}
              type="button"
              title={action.label}
              aria-label={action.label}
              onClick={() =>
                action.label === 'Feeling/activity' &&
                setBackground(background ? null : '#7B4397,#C06C84')
              }
              className="grid h-9 w-9 place-items-center rounded-full transition-transform hover:scale-110 hover:bg-hover/[0.06]"
            >
              <Icon name={action.icon as never} size={22} style={{ color: action.color }} />
            </button>
          ))}

          <span title="More" className="grid h-9 w-9 place-items-center text-secondary">
            <Icon name="Ellipsis" size={22} />
          </span>
        </div>
      </div>

      {create.isPending && (
        <div className="grid place-items-center pb-4">
          <Spinner size={22} className="text-secondary" />
        </div>
      )}
    </Modal>
  );
}
