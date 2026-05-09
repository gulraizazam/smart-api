@extends('layouts.app')
@section('title', 'Sign in')
@section('content')

<style>
    :root {
        --allura-bg: #f5f5f7;
        --allura-fg: #0a0a0a;
        --allura-muted: #6b7280;
        --allura-line: #e5e7eb;
        --allura-line-strong: #d4d4d8;
        --allura-card: #ffffff;
        --allura-brand-bg: #000000;
        --allura-brand-fg: #ffffff;
        --allura-error: #b42318;
        --allura-warn: #b45309;
    }

    body#kt_body.bg-body { background: var(--allura-bg); }

    .allura-auth {
        min-height: 100dvh;
        display: grid;
        grid-template-columns: 1fr;
        background: var(--allura-bg);
        font-family: 'Poppins', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        color: var(--allura-fg);
    }

    .allura-auth__brand {
        display: none;
        position: relative;
        overflow: hidden;
        background: var(--allura-brand-bg);
        color: var(--allura-brand-fg);
        padding-block: 3rem;
        padding-inline: 3rem;
    }

    .allura-auth__brand-inner {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        gap: 3rem;
    }

    .allura-auth__brand-mark {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        color: inherit;
        text-decoration: none;
    }

    .allura-auth__brand-mark .mark {
        display: inline-grid;
        place-items: center;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #fff;
        color: #000;
        font-weight: 800;
        font-size: 1.1rem;
        letter-spacing: 0;
    }

    .allura-auth__brand-quote {
        font-size: clamp(1.4rem, 2vw + 0.5rem, 2rem);
        line-height: 1.3;
        font-weight: 500;
        letter-spacing: -0.01em;
        max-width: 28ch;
    }

    .allura-auth__brand-quote em {
        font-style: normal;
        opacity: 0.55;
    }

    .allura-auth__brand-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        font-size: 0.85rem;
        opacity: 0.7;
    }

    .allura-auth__brand-meta a {
        color: inherit;
        text-decoration: none;
        border-bottom: 1px solid rgba(255,255,255,.25);
        padding-block-end: 2px;
    }

    .allura-auth__brand-glow {
        position: absolute;
        inset-block-start: -20%;
        inset-inline-end: -20%;
        width: 70%;
        aspect-ratio: 1;
        background: radial-gradient(closest-side, rgba(255,255,255,.18), transparent 70%);
        filter: blur(30px);
        pointer-events: none;
        z-index: 0;
    }

    .allura-auth__brand-grid {
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
        background-size: 48px 48px;
        mask-image: linear-gradient(180deg, transparent, #000 30%, #000 70%, transparent);
        pointer-events: none;
        z-index: 0;
    }

    .allura-auth__panel {
        display: grid;
        place-items: center;
        padding-block: 1.5rem;
        padding-inline: 1.25rem;
        container-type: inline-size;
    }

    .allura-auth__card {
        width: 100%;
        max-width: 440px;
        background: var(--allura-card);
        border: 1px solid var(--allura-line);
        border-radius: 16px;
        padding-block: 1.75rem;
        padding-inline: 1.5rem;
        box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.06);
    }

    .allura-auth__mobile-mark {
        display: flex;
        justify-content: center;
        margin-block-end: 1.25rem;
    }
    .allura-auth__mobile-mark img {
        height: 40px;
        width: auto;
        display: block;
    }

    .allura-auth__heading h1 {
        font-size: 1.625rem;
        font-weight: 700;
        margin: 0 0 0.4rem;
        letter-spacing: -0.01em;
        color: var(--allura-fg);
    }
    .allura-auth__heading p {
        color: var(--allura-muted);
        margin: 0 0 1.5rem;
        font-size: 0.95rem;
    }

    .allura-field { margin-block-end: 1.1rem; }
    .allura-field__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-block-end: 0.45rem;
    }
    .allura-field__label {
        display: inline-block;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--allura-fg);
        margin: 0 0 0.45rem;
    }
    .allura-field__row .allura-field__label { margin: 0; }
    .allura-field__link {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--allura-fg);
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-thickness: 1px;
    }
    .allura-field__link:hover { color: #000; text-decoration-thickness: 2px; }
    .allura-field__link:focus-visible {
        outline: 2px solid var(--allura-fg);
        outline-offset: 3px;
        border-radius: 2px;
    }

    .allura-field__inputwrap { position: relative; }

    .allura-auth .allura-field__input.form-control,
    .allura-auth .allura-field__input {
        display: block;
        width: 100%;
        min-height: 48px;
        padding-block: 0.75rem;
        padding-inline: 1rem;
        font-size: 1rem;
        line-height: 1.4;
        color: var(--allura-fg);
        background: #fff;
        border: 1px solid var(--allura-line-strong);
        border-radius: 10px;
        transition: border-color .15s ease, box-shadow .15s ease;
        appearance: none;
        -webkit-appearance: none;
    }
    .allura-auth .allura-field__input::placeholder { color: #9ca3af; }
    .allura-auth .allura-field__input:hover { border-color: #a1a1aa; }
    .allura-auth .allura-field__input:focus,
    .allura-auth .allura-field__input:focus-visible {
        outline: none;
        border-color: var(--allura-fg);
        box-shadow: 0 0 0 3px rgba(0,0,0,.12);
    }
    .allura-auth .allura-field__input[aria-invalid="true"] { border-color: var(--allura-error); }
    .allura-auth .allura-field__input[aria-invalid="true"]:focus {
        box-shadow: 0 0 0 3px rgba(180,35,24,.18);
    }

    .allura-field__inputwrap .allura-field__input { padding-inline-end: 3rem; }
    .allura-field__toggle {
        position: absolute;
        inset-block: 4px;
        inset-inline-end: 4px;
        width: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: 0;
        color: var(--allura-muted);
        border-radius: 8px;
        cursor: pointer;
    }
    .allura-field__toggle:hover { color: var(--allura-fg); }
    .allura-field__toggle:focus-visible {
        outline: 2px solid var(--allura-fg);
        outline-offset: 2px;
    }
    .allura-field__toggle svg { width: 20px; height: 20px; display: block; }

    .allura-field__error,
    .allura-field__warning {
        margin: 0.45rem 0 0;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--allura-error);
        display: flex;
        align-items: flex-start;
        gap: 0.35rem;
    }
    .allura-field__warning { color: var(--allura-warn); }
    .allura-field__error svg,
    .allura-field__warning svg { flex: 0 0 auto; margin-block-start: 2px; }

    .allura-btn {
        appearance: none;
        -webkit-appearance: none;
        width: 100%;
        min-height: 48px;
        padding-block: 0.85rem;
        padding-inline: 1rem;
        border: 1px solid var(--allura-fg);
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease, transform .05s ease, box-shadow .15s ease;
        font-family: inherit;
    }
    .allura-btn--primary {
        background: var(--allura-fg);
        color: #fff;
        margin-block-start: 0.5rem;
    }
    .allura-btn--primary:hover { background: #1a1a1a; }
    .allura-btn--primary:active { transform: translateY(1px); }
    .allura-btn:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(0,0,0,.22);
    }
    .allura-btn[disabled] { opacity: 0.6; cursor: progress; }

    .allura-auth__card .alert {
        border: 1px solid transparent;
        border-radius: 10px;
        padding-block: 0.75rem;
        padding-inline: 1rem;
        font-size: 0.9rem;
        margin-block-end: 1rem;
    }
    .allura-auth__card .alert-danger {
        background: #fef2f2;
        color: #991b1b;
        border-color: #fecaca;
    }
    .allura-auth__card .alert-success {
        background: #ecfdf5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .allura-auth__footer {
        margin-block-start: 1.5rem;
        text-align: center;
        color: var(--allura-muted);
        font-size: 0.8rem;
    }

    @container (min-width: 480px) {
        .allura-auth__card {
            padding-block: 2.25rem;
            padding-inline: 2rem;
        }
        .allura-auth__heading h1 { font-size: 1.875rem; }
    }

    @media (min-width: 992px) {
        .allura-auth { grid-template-columns: 1.05fr 1fr; }
        .allura-auth__brand { display: block; }
        .allura-auth__panel { padding: 3rem; }
        .allura-auth__mobile-mark { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .allura-auth .allura-field__input,
        .allura-btn { transition: none; }
    }

    .allura-auth__sr {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
</style>

<main class="allura-auth" role="main">
    <section class="allura-auth__brand" aria-hidden="true">
        <div class="allura-auth__brand-grid"></div>
        <div class="allura-auth__brand-glow"></div>
        <div class="allura-auth__brand-inner">
            <a href="/" class="allura-auth__brand-mark">
                <span class="mark">A</span>
                <span>Allura</span>
            </a>

            <div>
                <p class="allura-auth__brand-quote">
                    Built for clinics that take care of people.
                    <em>Bookings, billing, inventory and care plans &mdash; in one place.</em>
                </p>
            </div>

            <div class="allura-auth__brand-meta">
                <span>&copy; {{ date('Y') }} Allura Aesthetics</span>
                <a href="https://www.alluraesthetics.pk" target="_blank" rel="noopener noreferrer">alluraesthetics.pk</a>
            </div>
        </div>
    </section>

    <section class="allura-auth__panel">
        <div class="allura-auth__card">
            <div class="allura-auth__mobile-mark">
                <img src="{{ asset('logo_final.png') }}" alt="Allura" width="160" height="40" fetchpriority="high" />
            </div>

            <header class="allura-auth__heading">
                <h1>Sign in</h1>
                <p>Welcome back. Please enter your details.</p>
            </header>

            @include('admin.partials.messages', ['message' => true])

            <form id="kt_sign_in_form" class="allura-auth__form" method="POST" action="{{ route('login') }}" novalidate="novalidate" autocomplete="on">
                @csrf

                <div class="fv-row allura-field">
                    <label for="allura-email" class="allura-field__label">Email address</label>
                    <input
                        id="allura-email"
                        name="email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        autocapitalize="off"
                        spellcheck="false"
                        class="allura-field__input form-control"
                        value="{{ old('email') }}"
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        required
                        autofocus
                    />
                    @error('email')
                        <p id="email-error" class="allura-field__error" role="alert">
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <div class="fv-row allura-field">
                    <div class="allura-field__row">
                        <label for="allura-password" class="allura-field__label">Password</label>
                        <a href="{{ route('auth.password.reset') }}" class="allura-field__link toggle-form">Forgot password?</a>
                    </div>
                    <div class="allura-field__inputwrap">
                        <input
                            id="allura-password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            class="allura-field__input form-control"
                            @error('password') aria-invalid="true" aria-describedby="caps-lock-warning password-error" @else aria-describedby="caps-lock-warning" @enderror
                            required
                        />
                        <button
                            type="button"
                            class="allura-field__toggle"
                            id="allura_password_toggle"
                            aria-label="Show password"
                            aria-pressed="false"
                            aria-controls="allura-password"
                        >
                            <svg data-icon="eye" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-icon="eye-off" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12A3 3 0 0 1 9.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            <span class="allura-auth__sr">Show password</span>
                        </button>
                    </div>
                    <p id="caps-lock-warning" class="allura-field__warning" role="status" hidden>
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 9h-5v6h-6v-6H4l8-9z"/></svg>
                        <span>Caps Lock is on</span>
                    </p>
                    @error('password')
                        <p id="password-error" class="allura-field__error" role="alert">
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <button id="kt_sign_in_submit" type="submit" class="allura-btn allura-btn--primary">
                    <span class="indicator-label" data-label>Sign in</span>
                </button>
            </form>

            <p class="allura-auth__footer">
                Need help? <a href="mailto:support@alluraesthetics.pk" style="color:inherit;text-decoration:underline;text-underline-offset:3px;">Contact support</a>
            </p>
        </div>
    </section>
</main>

<script>
    (function () {
        var pwd = document.getElementById('allura-password');
        var toggle = document.getElementById('allura_password_toggle');
        var caps = document.getElementById('caps-lock-warning');
        var form = document.getElementById('kt_sign_in_form');
        var submit = document.getElementById('kt_sign_in_submit');

        if (toggle && pwd) {
            toggle.addEventListener('click', function () {
                var showing = pwd.getAttribute('type') === 'text';
                pwd.setAttribute('type', showing ? 'password' : 'text');
                toggle.setAttribute('aria-pressed', String(!showing));
                toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                var eye = toggle.querySelector('[data-icon="eye"]');
                var eyeOff = toggle.querySelector('[data-icon="eye-off"]');
                if (eye) eye.hidden = !showing;
                if (eyeOff) eyeOff.hidden = showing;
                pwd.focus({ preventScroll: true });
            });
        }

        function checkCaps(e) {
            if (!caps) return;
            var on = e && typeof e.getModifierState === 'function' && e.getModifierState('CapsLock');
            caps.hidden = !on;
        }
        if (pwd) {
            pwd.addEventListener('keydown', checkCaps);
            pwd.addEventListener('keyup', checkCaps);
            pwd.addEventListener('blur', function () { if (caps) caps.hidden = true; });
        }

        if (form && submit) {
            form.addEventListener('submit', function () {
                if (submit.disabled) return;
                submit.disabled = true;
                var label = submit.querySelector('[data-label]');
                if (label) label.textContent = 'Signing in…';
            });
        }
    })();
</script>

@endsection
