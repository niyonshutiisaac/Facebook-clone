import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Modal } from '@/components/molecules/Modal';
import { GroupCard, GroupCardSkeleton, PRIVACY } from '@/components/molecules/GroupCard';
import { useCreateGroup, useGroups, useJoinGroup, useLeaveGroup, useMyGroups } from '@/api/queries';
import { cn } from '@/lib/utils';
import type { Group, GroupPrivacy, GroupType } from '@/types/api';

type Tab = 'yours' | 'discover';

/**
 * The groups index.
 *
 * Two lists, because they answer different questions: the groups you already
 * belong to (with your role) and the ones worth joining. Creating a group is
 * here rather than behind a menu — it is the action this page exists for.
 */
export function GroupsPage() {
  const navigate = useNavigate();
  const [tab, setTab] = useState<Tab>('discover');
  const [creating, setCreating] = useState(false);

  const { data: discover, isLoading: loadingDiscover } = useGroups();
  const { data: mine, isLoading: loadingMine } = useMyGroups();
  const join = useJoinGroup();
  const leave = useLeaveGroup();

  const groups = tab === 'yours' ? (mine ?? []) : (discover?.data ?? []);
  const loading = tab === 'yours' ? loadingMine : loadingDiscover;
  const membershipPending = join.isPending || leave.isPending;

  return (
    <div className="mx-auto w-full max-w-[68rem] px-2 sm:px-0">
      <header className="card mb-4 flex flex-wrap items-center gap-3 p-4">
        <div className="min-w-0">
          <h1 className="text-xl font-bold">Groups</h1>
          <p className="text-sm text-secondary">
            {(mine ?? []).length > 0
              ? `You are in ${(mine ?? []).length} ${(mine ?? []).length === 1 ? 'group' : 'groups'}.`
              : 'Join a group to see its posts in your feed.'}
          </p>
        </div>

        <Button variant="primary" className="ml-auto" onClick={() => setCreating(true)}>
          <Icon name="Plus" size={16} />
          Create group
        </Button>
      </header>

      <div className="mb-4 flex gap-1 border-b border-divider">
        {(
          [
            ['discover', 'Discover'],
            ['yours', `Your groups${(mine ?? []).length > 0 ? ` (${(mine ?? []).length})` : ''}`],
          ] as Array<[Tab, string]>
        ).map(([value, label]) => (
          <button
            key={value}
            type="button"
            onClick={() => setTab(value)}
            aria-current={tab === value}
            className={cn(
              '-mb-px border-b-2 px-4 py-2.5 text-base font-semibold transition-colors',
              tab === value
                ? 'border-facebook text-facebook'
                : 'border-transparent text-secondary hover:bg-hover/[0.05]',
            )}
          >
            {label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 6 }, (_, i) => (
            <GroupCardSkeleton key={i} />
          ))}
        </div>
      ) : groups.length === 0 ? (
        <div className="card p-10 text-center">
          <p className="text-lg font-semibold">
            {tab === 'yours' ? "You haven't joined any groups yet." : 'No groups to suggest right now.'}
          </p>
          <p className="mt-1 text-base text-secondary">
            {tab === 'yours'
              ? 'Browse Discover to find communities worth joining.'
              : 'Check back soon — new groups appear as people create them.'}
          </p>

          {tab === 'yours' && (
            <Button variant="secondary" className="mt-4" onClick={() => setTab('discover')}>
              Browse groups
            </Button>
          )}
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {groups.map((group) => (
            <GroupCard
              key={group.id}
              group={group}
              joining={membershipPending}
              onJoin={(target) => join.mutate(target.id)}
              onLeave={(target) => leave.mutate(target.id)}
            />
          ))}
        </div>
      )}

      <CreateGroupModal
        open={creating}
        onClose={() => setCreating(false)}
        onCreated={(group) => {
          setCreating(false);
          navigate(`/groups/${group.slug}`);
        }}
      />
    </div>
  );
}

/**
 * Facebook asks for a name, a privacy level and (optionally) a type before it
 * will create anything — the rest of the settings can wait until the group
 * exists, which is why the form is this short.
 */
function CreateGroupModal({
  open,
  onClose,
  onCreated,
}: {
  open: boolean;
  onClose: () => void;
  onCreated: (group: Group) => void;
}) {
  const create = useCreateGroup();

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [privacy, setPrivacy] = useState<GroupPrivacy>('public');
  const [type, setType] = useState<GroupType>('general');
  const [error, setError] = useState('');

  const submit = () => {
    if (name.trim().length < 3) {
      setError('Give your group a name of at least 3 characters.');

      return;
    }

    setError('');

    create.mutate(
      { name: name.trim(), description: description.trim() || undefined, privacy, type },
      { onSuccess: onCreated },
    );
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Create a group"
      footer={
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>

          <Button variant="primary" onClick={submit} loading={create.isPending} disabled={create.isPending}>
            Create
          </Button>
        </div>
      }
    >
      <div className="space-y-4 p-4">
        <label className="block">
          <span className="mb-1 block text-base font-semibold">Group name</span>
          <input
            value={name}
            onChange={(event) => {
              setName(event.target.value);
              setError('');
            }}
            placeholder="What is your group about?"
            className="h-10 w-full rounded-control border border-divider bg-subtle px-3 text-md outline-none focus:border-facebook"
          />
          {error && <span className="mt-1 block text-sm text-facebook-red">{error}</span>}
        </label>

        <label className="block">
          <span className="mb-1 block text-base font-semibold">Description (optional)</span>
          <textarea
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            rows={3}
            placeholder="What is this group for? Who should join?"
            className="w-full rounded-control border border-divider bg-subtle px-3 py-2 text-md outline-none focus:border-facebook"
          />
        </label>

        <fieldset>
          <legend className="mb-1 block text-base font-semibold">Privacy</legend>

          <div className="space-y-2">
            {(Object.keys(PRIVACY) as GroupPrivacy[]).map((value) => (
              <label
                key={value}
                className={cn(
                  'flex cursor-pointer gap-3 rounded-control border p-3 transition-colors',
                  privacy === value ? 'border-facebook bg-facebook/5' : 'border-divider hover:bg-hover/[0.05]',
                )}
              >
                <input
                  type="radio"
                  name="privacy"
                  value={value}
                  checked={privacy === value}
                  onChange={() => setPrivacy(value)}
                  className="mt-1 accent-facebook"
                />

                <span>
                  <span className="flex items-center gap-1.5 font-semibold">
                    <Icon name={PRIVACY[value].icon} size={14} />
                    {PRIVACY[value].label}
                  </span>
                  <span className="block text-sm text-secondary">{PRIVACY[value].blurb}</span>
                </span>
              </label>
            ))}
          </div>
        </fieldset>

        <label className="block">
          <span className="mb-1 block text-base font-semibold">Group type</span>
          <select
            value={type}
            onChange={(event) => setType(event.target.value as GroupType)}
            className="h-10 w-full rounded-control border border-divider bg-subtle px-3 text-md outline-none focus:border-facebook"
          >
            <option value="general">General</option>
            <option value="social_learning">Social learning</option>
            <option value="buy_and_sell">Buy and sell</option>
          </select>
        </label>
      </div>
    </Modal>
  );
}
