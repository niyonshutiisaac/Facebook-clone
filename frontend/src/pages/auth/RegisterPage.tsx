import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Icon } from '@/components/atoms/Icon';
import { Button } from '@/components/atoms/Button';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const GENDERS = [
  { value: 'female', label: 'Female' },
  { value: 'male', label: 'Male' },
  { value: 'custom', label: 'Custom' },
];

/**
 * Facebook's signup form: two name fields, contact (email or phone), password,
 * then birthday and gender. Validation is inline and forgiving — Facebook never
 * blocks submission with a summary toast.
 */
export function RegisterPage() {
  const navigate = useNavigate();
  const { register: registerAccount, status } = useAuthStore();

  const [fields, setFields] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    day: '',
    month: '',
    year: '',
    gender: '',
  });

  const [errors, setErrors] = useState<Record<string, string>>({});

  const set = (key: keyof typeof fields) => (event: { target: { value: string } }) => {
    setFields((current) => ({ ...current, [key]: event.target.value }));
    setErrors((current) => ({ ...current, [key]: '' }));
  };

  const onSubmit = async () => {
    const next: Record<string, string> = {};

    if (!fields.first_name.trim()) next.first_name = "What's your first name?";
    if (!fields.last_name.trim()) next.last_name = "What's your last name?";

    if (!fields.email.trim() && !fields.phone.trim()) {
      next.email = 'Enter an email address or mobile number.';
    }

    if (fields.password.length < 8) next.password = 'Use at least 8 characters.';
    if (!fields.day || !fields.month || !fields.year) next.day = 'Enter your date of birth.';
    if (!fields.gender) next.gender = 'Please choose a gender.';

    setErrors(next);

    if (Object.keys(next).length > 0) return;

    await registerAccount({
      first_name: fields.first_name.trim(),
      last_name: fields.last_name.trim(),
      email: fields.email.trim() || undefined,
      phone: fields.phone.trim() || undefined,
      password: fields.password,
    });

    navigate('/', { replace: true });
  };

  const years = Array.from({ length: 100 }, (_, i) => String(new Date().getFullYear() - i));

  return (
    <div className="min-h-screen bg-canvas px-4 py-8">
      <div className="mx-auto max-w-lg">
        <div className="text-center">
          <Icon name="facebook" size={56} className="mx-auto text-facebook" />
          <h1 className="mt-2 text-2xl font-bold">Create a new account</h1>
          <p className="text-base text-secondary">It&apos;s quick and easy.</p>
        </div>

        <form
          className="card mt-4 space-y-3 p-4"
          onSubmit={(event) => {
            event.preventDefault();
            void onSubmit();
          }}
        >
          <div className="flex gap-2">
            <div className="flex-1">
              <input
                value={fields.first_name}
                onChange={set('first_name')}
                placeholder="First name"
                autoComplete="given-name"
                className={cn('input', errors.first_name && 'border-facebook-red')}
              />
              {errors.first_name && <p className="mt-1 text-sm text-facebook-red">{errors.first_name}</p>}
            </div>

            <div className="flex-1">
              <input
                value={fields.last_name}
                onChange={set('last_name')}
                placeholder="Surname"
                autoComplete="family-name"
                className={cn('input', errors.last_name && 'border-facebook-red')}
              />
              {errors.last_name && <p className="mt-1 text-sm text-facebook-red">{errors.last_name}</p>}
            </div>
          </div>

          <div>
            <input
              value={fields.email}
              onChange={set('email')}
              placeholder="Mobile number or email address"
              className={cn('input', errors.email && 'border-facebook-red')}
            />
            {errors.email && <p className="mt-1 text-sm text-facebook-red">{errors.email}</p>}
          </div>

          <div>
            <input
              type="password"
              value={fields.password}
              onChange={set('password')}
              placeholder="New password"
              autoComplete="new-password"
              className={cn('input', errors.password && 'border-facebook-red')}
            />
            {errors.password && <p className="mt-1 text-sm text-facebook-red">{errors.password}</p>}
          </div>

          {/* ------------------------------------------------------------ dob */}
          <fieldset>
            <legend className="mb-1 text-sm text-secondary">Date of birth</legend>

            <div className="flex gap-2">
              <select value={fields.day} onChange={set('day')} className="input" aria-label="Day">
                <option value="">Day</option>
                {Array.from({ length: 31 }, (_, i) => String(i + 1)).map((day) => (
                  <option key={day} value={day}>
                    {day}
                  </option>
                ))}
              </select>

              <select value={fields.month} onChange={set('month')} className="input" aria-label="Month">
                <option value="">Month</option>
                {MONTHS.map((month, index) => (
                  <option key={month} value={String(index + 1)}>
                    {month}
                  </option>
                ))}
              </select>

              <select value={fields.year} onChange={set('year')} className="input" aria-label="Year">
                <option value="">Year</option>
                {years.map((year) => (
                  <option key={year} value={year}>
                    {year}
                  </option>
                ))}
              </select>
            </div>

            {errors.day && <p className="mt-1 text-sm text-facebook-red">{errors.day}</p>}
          </fieldset>

          {/* --------------------------------------------------------- gender */}
          <fieldset>
            <legend className="mb-1 text-sm text-secondary">Gender</legend>

            <div className="flex gap-2">
              {GENDERS.map((gender) => (
                <label
                  key={gender.value}
                  className={cn(
                    'flex flex-1 items-center justify-between rounded-control border border-divider px-3 py-2 text-base',
                    fields.gender === gender.value && 'border-facebook',
                  )}
                >
                  <span>{gender.label}</span>
                  <input
                    type="radio"
                    name="gender"
                    value={gender.value}
                    checked={fields.gender === gender.value}
                    onChange={set('gender')}
                    className="h-4 w-4 accent-facebook"
                  />
                </label>
              ))}
            </div>

            {errors.gender && <p className="mt-1 text-sm text-facebook-red">{errors.gender}</p>}
          </fieldset>

          <p className="text-xs leading-relaxed text-tertiary">
            By clicking Sign Up, you agree to our Terms, Privacy Policy and Cookies Policy. We are 13+
            only.
          </p>

          <div className="text-center">
            <Button type="submit" variant="success" size="lg" loading={status === 'loading'} className="px-12">
              Sign Up
            </Button>
          </div>

          <div className="text-center">
            <Link to="/login" className="text-base text-accent hover:underline">
              Already have an account?
            </Link>
          </div>
        </form>
      </div>
    </div>
  );
}
