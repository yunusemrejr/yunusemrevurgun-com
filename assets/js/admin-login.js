(function () {
    'use strict';

    var state = {
        submitting: false,
        passwordVisible: false
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
        var username = $('#username');
        var password = $('#password');
        var captcha = $('#captcha_answer');
        var submit = $('.admin-login-submit');
        if (!form || !username || !password || !captcha || !submit) return;

        [username, password, captcha].forEach(function (field) {
            field.addEventListener('input', function () {
                clearFieldError(field);
            });
        });

        form.addEventListener('submit', function (event) {
            if (state.submitting) {
                event.preventDefault();
                return;
            }

            var valid = true;
            valid = validateField(username, 3, 'Username') && valid;
            valid = validateField(password, 6, 'Password') && valid;
            valid = validateCaptcha(captcha) && valid;

            if (!valid) {
                event.preventDefault();
                return;
            }

            state.submitting = true;
            setLoading(submit, true);
        });
    }

    function initFocus() {
        var username = $('#username');
        var captcha = $('#captcha_answer');
        if (username && !username.value) {
            username.focus();
        } else if (captcha) {
            captcha.focus();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initPasswordToggle();
        initCaptchaRefresh();
        initForm();
        initFocus();
    });
})();
