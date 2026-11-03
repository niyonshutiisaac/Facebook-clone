import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Icon } from '@/components/atoms/Icon';
import { Button } from '@/components/atoms/Button';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils';

const schema = z.object({
  identity: z.string().min(1, 'Enter your email, phone or username.'),
  password: z.string().min(8, 'Your password is at least 8 characters.'),
  remember: z.boolean().optional(),
});

type Fields = z.infer<typeof schema>;

export function LoginPage() {
  const navigate = useNavigate();
  const { login, status } = useAuthStore();
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<Fields>({
    resolver: zodResolver(schema),
    defaultValues: { identity: '', password: '', remember: true },
  });

  if (status === 'authenticated') return <Navigate to="/" replace />;

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null);

    try {
      await login(values.identity, values.password);
      navigate('/', { replace: true });
    } catch (error) {
      setFormError(error instanceof Error ? error.message : 'Unable to sign in.');
    }
  });

  return (
    <div className="grid min-h-screen grid-cols-1 items-center bg-canvas px-4 py-8 lg:grid-cols-[1.2fr_1fr] lg:gap-8 lg:px-12">
      {/* ------------------------------------------------------------- brand */}
      <div className="mx-auto max-w-md text-center lg:max-w-none lg:text-left">
        <Icon name="facebook" size={64} className="mx-auto text-facebook lg:mx-0" />

        <h1 className="mt-3 text-3xl font-bold leading-tight lg:text-[3.25rem] lg:leading-[1.05]">
          Connect with friends and the world around you on Avimoney.
        </h1>

        <p className="mt-4 hidden max-w-md text-lg text-secondary lg:block">
          Share photos, follow conversations, and message the people who matter — all in one place.
        </p>
      </div>

      {/* --------------------------------------------------------------- form */}
      <div className="mx-auto w-full max-w-sm">
        <form onSubmit={onSubmit} className="card space-y-3 p-4">
          <input
            {...register('identity')}
            type="text"
            autoComplete="username"
            placeholder="Email address, phone or username"
            className={cn('input', errors.identity && 'border-facebook-red')}
            aria-invalid={Boolean(errors.identity)}
          />

          {errors.identity && <p className="text-sm text-facebook-red">{errors.identity.message}</p>}

          <input
            {...register('password')}
            type="password"
            autoComplete="current-password"
            placeholder="Password"
            className={cn('input', errors.password && 'border-facebook-red')}
            aria-invalid={Boolean(errors.password)}
          />

          {errors.password && <p className="text-sm text-facebook-red">{errors.password.message}</p>}

          {formError && (
            <p role="alert" className="rounded-control bg-facebook-red/10 px-3 py-2 text-base text-facebook-red">
              {formError}
            </p>
          )}

          <Button type="submit" variant="primary" size="lg" fullWidth loading={status === 'loading'}>
            Log in
          </Button>

          <div className="text-center">
            <Link to="/forgot-password" className="text-base text-accent hover:underline">
              Forgotten password?
            </Link>
          </div>

          <hr className="my-2 border-divider" />

          <div className="text-center">
            <Button type="button" variant="success" size="lg" onClick={() => navigate('/register')}>
              Create new account
            </Button>
          </div>
        </form>

        <p className="mt-6 text-center text-sm text-secondary">
          <span className="font-semibold text-primary">Demo mode.</span> Any email and a password of
          eight characters or more will sign you in.
        </p>
      </div>
    </div>
  );
}
