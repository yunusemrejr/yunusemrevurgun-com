(function () {
    'use strict';

    // Turnstile callbacks (must exist before the widget can succeed).
    // On success the challenge auto-submits so the user lands directly on
    // the credentials step — no manual Verify click needed. The server
    // still validates the token; these callbacks are pure UX.
    window.yevTurnstileSuccess = function () {
        var btn = document.getElementById('captcha-submit-btn');
        if (!btn) return;
        btn.disabled = false;
        btn.click();
    };
    window.yevTurnstileReset = function () {
        var btn = document.getElementById('captcha-submit-btn');
        if (btn) btn.disabled = true;
    };
    // If the widget never loads (blocked network/CSP), don't trap the user —
    // enable the button after a grace period; server-side verification still applies.
    window.setTimeout(function () {
        var btn = document.getElementById('captcha-submit-btn');
        if (btn && btn.disabled && !document.querySelector('.cf-turnstile iframe')) {
            btn.disabled = false;
        }
    }, 8000);

    var state = {
        submitting: false,
        passwordVisible: false,
        isCaptchaStep: true
    };

    function $(selector) {
        return document.querySelector(selector);
    }

    function clearFieldError(field) {
        var group = field.closest('.admin-form-group');
        if (!group) return;
        field.classList.remove('is-invalid');
        var error = group.querySelector('.field-error');
        if (error) error.remove();
    }

    function setFieldError(field, message) {
        var group = field.closest('.admin-form-group');
        if (!group) return;
        clearFieldError(field);
        field.classList.add('is-invalid');
        var error = document.createElement('div');
        error.className = 'field-error';
        error.textContent = message;
        group.appendChild(error);
    }

    function validateField(field, minLength, label) {
        var value = field.value.trim();
        if (!value) {
            setFieldError(field, label + ' is required.');
            return false;
        }
        if (value.length < minLength) {
            setFieldError(field, label + ' is too short.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    function validateCaptcha(field) {
        var value = field.value.trim();
        if (!/^\d{1,3}$/.test(value)) {
            setFieldError(field, 'Enter the security answer.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    function setLoading(button, isLoading) {
        button.classList.toggle('loading', isLoading);
        button.disabled = isLoading;
        button.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function initPasswordToggle() {
        var toggle = $('#password-toggle');
        var password = $('#password');
        if (!toggle || !password) return;

        toggle.addEventListener('click', function () {
            var icon = toggle.querySelector('i');
            state.passwordVisible = !state.passwordVisible;
            password.type = state.passwordVisible ? 'text' : 'password';
            toggle.setAttribute('aria-label', state.passwordVisible ? 'Hide password' : 'Show password');
            if (icon) {
                icon.classList.toggle('bi-eye', !state.passwordVisible);
                icon.classList.toggle('bi-eye-slash', state.passwordVisible);
            }
            password.focus();
        });
    }

    function initCaptchaRefresh() {
        var refresh = $('#refresh-captcha');
        if (!refresh) return;

        refresh.addEventListener('click', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('refresh_captcha', '1');
            window.location.href = url.toString();
        });
    }

    function initForm() {
        var form = $('#admin-login-form');
        if (!form) return;

        // Detect which step we're on
        var captchaStep = $('#captcha-step');
        var credentialsStep = $('#credentials-step');
        state.isCaptchaStep = captchaStep && captchaStep.style.display !== 'none';

        var submit = form.querySelector('.admin-login-submit');
        if (!submit) return;

        if (state.isCaptchaStep) {
            // Step 1: CAPTCHA only
            var captcha = $('#captcha_answer');
            if (captcha) {
                captcha.addEventListener('input', function () {
                    clearFieldError(captcha);
                });
            }

            form.addEventListener('submit', function (event) {
                if (state.submitting) {
                    event.preventDefault();
                    return;
                }

                var valid = true;
                if (captcha) {
                    valid = validateCaptcha(captcha) && valid;
                }

                if (!valid) {
                    event.preventDefault();
                    return;
                }

                state.submitting = true;
                setLoading(submit, true);
            });
        } else {
            // Step 2: Credentials
            var username = $('#username');
            var password = $('#password');
            var captchaAnswerHidden = $('#captcha_answer_final');

            // Copy CAPTCHA answer to hidden field before submit
            if (captchaAnswerHidden) {
                var captchaInput = $('#captcha_answer');
                if (captchaInput) {
                    captchaAnswerHidden.value = captchaInput.value;
                }
            }

            if (username) {
                username.addEventListener('input', function () {
                    clearFieldError(username);
                });
            }
            if (password) {
                password.addEventListener('input', function () {
                    clearFieldError(password);
                });
            }

            form.addEventListener('submit', function (event) {
                if (state.submitting) {
                    event.preventDefault();
                    return;
                }

                var valid = true;
                if (username) valid = validateField(username, 3, 'Username') && valid;
                if (password) valid = validateField(password, 6, 'Password') && valid;

                if (!valid) {
                    event.preventDefault();
                    return;
                }

                state.submitting = true;
                setLoading(submit, true);
            });
        }
    }

    function initFocus() {
        var captcha = $('#captcha_answer');
        var username = $('#username');
        
        if (captcha && !captcha.value) {
            captcha.focus();
        } else if (username && !username.value) {
            username.focus();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initPasswordToggle();
        initCaptchaRefresh();
        initForm();
        initFocus();
    });
})();
