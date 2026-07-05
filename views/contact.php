<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Contact.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/rate_limiter.php';
require_once dirname(__DIR__) . '/includes/double_submit_protection.php';
require_once __DIR__ . '/includes/ui.php';

$success = false;
$error = null;
$captchaError = null;

function generateContactCaptcha() {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $operation = rand(0, 1);
    
    if ($operation === 0) {
        $answer = $num1 + $num2;
        $question = "$num1 + $num2";
    } else {
        if ($num1 < $num2) {
            $temp = $num1;
            $num1 = $num2;
            $num2 = $temp;
        }
        $answer = $num1 - $num2;
        $question = "$num1 - $num2";
    }
    
    if (!isset($_SESSION['contact_captcha_secret'])) {
        $_SESSION['contact_captcha_secret'] = bin2hex(random_bytes(16));
    }
    $hash = hash_hmac('sha256', (string)$answer, $_SESSION['contact_captcha_secret']);
    
    return [
        'question' => $question,
        'answer' => $answer,
        'hash' => $hash
    ];
}

function verifyContactCaptcha($userAnswer, $answerHash) {
    if (!isset($_SESSION['contact_captcha_secret']) || !isset($_SESSION['contact_captcha_answer_hash'])) {
        return false;
    }
    
    if (!hash_equals($_SESSION['contact_captcha_answer_hash'], $answerHash)) {
        return false;
    }
    
    $userAnswerInt = (int)trim($userAnswer);
    $userAnswerHash = hash_hmac('sha256', (string)$userAnswerInt, $_SESSION['contact_captcha_secret']);
    
    return hash_equals($answerHash, $userAnswerHash);
}

if (!isset($_SESSION['contact_captcha_question']) || isset($_GET['refresh_captcha'])) {
    $captcha = generateContactCaptcha();
    $_SESSION['contact_captcha_question'] = $captcha['question'];
    $_SESSION['contact_captcha_answer_hash'] = $captcha['hash'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $honeypot = trim($_POST['website'] ?? '');

    if (!CSRFProtection::validateToken($csrfToken)) {
        $error = 'Security token expired. Please refresh and try again.';
    } elseif (!empty($honeypot)) {
        $success = true;
    } elseif (RateLimiter::isRateLimited()) {
        $error = 'Too many attempts. Please wait before sending another message.';
    } elseif (!DoubleSubmitProtection::canSubmit('contact')) {
        $error = 'Please wait a few seconds before submitting again.';
    }

    $captchaValid = false;
    $userAnswer = trim($_POST['captcha_answer'] ?? '');
    $answerHash = $_POST['captcha_hash'] ?? '';
    
    if (empty($userAnswer)) {
        $captchaError = 'Please complete the security verification.';
    } elseif (!isset($_SESSION['contact_captcha_answer_hash']) || !isset($_POST['captcha_hash'])) {
        $captchaError = 'Security verification expired. Please refresh the page.';
        $captcha = generateContactCaptcha();
        $_SESSION['contact_captcha_question'] = $captcha['question'];
        $_SESSION['contact_captcha_answer_hash'] = $captcha['hash'];
    } elseif (!verifyContactCaptcha($userAnswer, $answerHash)) {
        $captchaError = 'Incorrect answer. Please try again.';
        $captcha = generateContactCaptcha();
        $_SESSION['contact_captcha_question'] = $captcha['question'];
        $_SESSION['contact_captcha_answer_hash'] = $captcha['hash'];
    } else {
        $captchaValid = true;
    }

    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'message' => trim($_POST['message'] ?? '')
    ];

    $fieldErrors = [];
    if (empty($data['name'])) {
        $fieldErrors['name'] = 'Name is required.';
    } elseif (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 80) {
        $fieldErrors['name'] = 'Name must be between 2 and 80 characters.';
    }
    
    if (empty($data['email'])) {
        $fieldErrors['email'] = 'Email is required.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Please enter a valid email address.';
    }
    
    if (empty($data['subject'])) {
        $fieldErrors['subject'] = 'Subject is required.';
    } elseif (mb_strlen($data['subject']) < 3 || mb_strlen($data['subject']) > 140) {
        $fieldErrors['subject'] = 'Subject must be between 3 and 140 characters.';
    }
    
    if (empty($data['message'])) {
        $fieldErrors['message'] = 'Message is required.';
    } elseif (mb_strlen($data['message']) < 10 || mb_strlen($data['message']) > 2000) {
        $fieldErrors['message'] = 'Message must be between 10 and 2000 characters.';
    }

    if ($error === null && !empty($fieldErrors)) {
        $error = 'Please fix the errors below.';
    } elseif ($error === null && $captchaError) {
        $error = $captchaError;
    } elseif ($error === null && $error === null && $captchaValid && empty($fieldErrors)) {
        try {
            $contact = new Contact();
            if ($contact->sendEmail($data)) {
                $success = true;
                RateLimiter::recordAttempt();
                DoubleSubmitProtection::recordSubmission('contact');
                $captcha = generateContactCaptcha();
                $_SESSION['contact_captcha_question'] = $captcha['question'];
                $_SESSION['contact_captcha_answer_hash'] = $captcha['hash'];
            } else {
                $error = 'Failed to send message. Please try again later.';
                RateLimiter::recordAttempt();
            }
        } catch (Exception $e) {
            error_log('Contact form error: ' . $e->getMessage());
            $error = 'An internal error occurred. Please try again later.';
        }
    }
}

ui_render_head(
    'Contact | Connect',
    'Send a message to Yunus Emre Vurgun for projects, collaborations, or technical consulting.'
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('Contact'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Connect</p>
            <h1 class="ui-section-title">Get in Touch</h1>
            <p class="ui-section-text">For projects, collaborations, or technical consulting.</p>
        </section>

        <section class="ui-section">
            <div class="ui-contact-grid">
                <div class="ui-glass-panel">
                    <?php if ($success): ?>
                        <div style="margin-bottom: 1rem; padding: 1rem; background: linear-gradient(135deg, rgba(168,34,30,0.2) 0%, rgba(246,241,232,0.06) 100%); border: 1px solid var(--color-accent); border-radius: var(--radius-lg);">
                            <p style="color: var(--color-text-primary);">Thank you. Your message has been sent.</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div style="margin-bottom: 1rem; padding: 1rem; background: linear-gradient(135deg, rgba(239,68,68,0.15) 0%, rgba(246,241,232,0.06) 100%); border: 1px solid rgba(239,68,68,0.3); border-radius: var(--radius-lg);">
                            <p style="color: var(--color-text-primary);"><?= htmlspecialchars($error) ?></p>
                        </div>
                    <?php endif; ?>

                    <form class="ui-form" method="post" action="<?= FULL_BASE_PATH ?>contact" id="contactForm" novalidate>
                        <?= CSRFProtection::getHiddenInput() ?>
                        <input type="hidden" name="submission_id" id="submissionId" value="">
                        <input type="text" name="website" id="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;">

                        <div>
                            <label class="ui-field-label" for="name">Name</label>
                            <input class="ui-input<?= isset($fieldErrors['name']) ? ' ui-input-error' : '' ?>" type="text" name="name" id="name" maxlength="80" required autocomplete="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            <?php if (isset($fieldErrors['name'])): ?>
                                <span class="ui-field-error"><?= htmlspecialchars($fieldErrors['name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="ui-field-label" for="email">Email</label>
                            <input class="ui-input<?= isset($fieldErrors['email']) ? ' ui-input-error' : '' ?>" type="email" name="email" id="email" maxlength="254" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <?php if (isset($fieldErrors['email'])): ?>
                                <span class="ui-field-error"><?= htmlspecialchars($fieldErrors['email']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="ui-field-label" for="subject">Subject</label>
                            <input class="ui-input<?= isset($fieldErrors['subject']) ? ' ui-input-error' : '' ?>" type="text" name="subject" id="subject" maxlength="140" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                            <?php if (isset($fieldErrors['subject'])): ?>
                                <span class="ui-field-error"><?= htmlspecialchars($fieldErrors['subject']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="ui-field-label" for="message">Message</label>
                            <textarea class="ui-input<?= isset($fieldErrors['message']) ? ' ui-input-error' : '' ?>" name="message" id="message" maxlength="2000" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <?php if (isset($fieldErrors['message'])): ?>
                                <span class="ui-field-error"><?= htmlspecialchars($fieldErrors['message']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="ui-captcha-section">
                            <label class="ui-field-label">Security Verification</label>
                            <div class="ui-captcha-container">
                                <span class="ui-captcha-question"><?= htmlspecialchars($_SESSION['contact_captcha_question'] ?? '? + ?') ?> = ?</span>
                                <button type="button" class="ui-captcha-refresh" id="refresh-captcha" title="Get a new question" aria-label="Refresh CAPTCHA">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
                                </button>
                            </div>
                            <input type="number" class="ui-input" id="captcha_answer" name="captcha_answer" placeholder="Enter answer" required autocomplete="off" min="0" max="20" style="max-width: 160px; margin-top: 0.5rem;">
                            <input type="hidden" name="captcha_hash" value="<?= htmlspecialchars($_SESSION['contact_captcha_answer_hash'] ?? '') ?>">
                            <?php if ($captchaError): ?>
                                <span class="ui-field-error"><?= htmlspecialchars($captchaError) ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <button class="ui-btn ui-btn-primary" type="submit">Send Message</button>
                        </div>
                    </form>
                </div>

                <div>
                    <div class="ui-glass-panel" style="margin-bottom: 1.5rem;">
                        <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">Response Time</p>
                        <p style="font-size: var(--text-sm); color: var(--color-text-secondary);">I typically respond within 24-48 hours on weekdays.</p>
                    </div>
                    <div class="ui-glass-panel" style="margin-bottom: 1.5rem;">
                        <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">YouTube</p>
                        <p style="font-size: var(--text-sm);"><a href="https://www.youtube.com/@yunusemrevurgun1" target="_blank" rel="noopener noreferrer" style="color: var(--color-text-primary);">@yunusemrevurgun1</a></p>
                    </div>
                    <div class="ui-glass-panel" style="margin-bottom: 1.5rem;">
                        <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">GitHub</p>
                        <p style="font-size: var(--text-sm);"><a href="https://github.com/yunusemrejr" target="_blank" rel="noopener noreferrer" style="color: var(--color-text-primary);">@yunusemrejr</a></p>
                    </div>
                    <div class="ui-glass-panel" style="margin-bottom: 1.5rem;">
                        <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">LinkedIn</p>
                        <p style="font-size: var(--text-sm);"><a href="https://linkedin.com/in/yunus-emre-vurgun-49ba9a177" target="_blank" rel="noopener noreferrer" style="color: var(--color-text-primary);">Profile</a></p>
                    </div>
                    <div class="ui-glass-panel" style="margin-bottom: 1rem;">
                        <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">Bluesky</p>
                        <p style="font-size: var(--text-sm);"><a href="https://bsky.app/profile/yunusemrevurgun.bsky.social" target="_blank" rel="noopener noreferrer" style="color: var(--color-text-primary);">@yunusemrevurgun</a></p>
                    </div>
                    <div class="ui-glass-panel">
                        <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">X / Twitter</p>
                        <p style="font-size: var(--text-sm);"><a href="https://x.com/yemrevu" target="_blank" rel="noopener noreferrer" style="color: var(--color-text-primary);">@yemrevu</a></p>
                    </div>
                    <div class="ui-glass-panel">
                        <p class="ui-eyebrow" style="margin-bottom: 0.25rem;">Instagram</p>
                        <p style="font-size: var(--text-sm);"><a href="https://instagram.com/yemrevu" target="_blank" rel="noopener noreferrer" style="color: var(--color-text-primary);">@yemrevu</a></p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/contact.js"></script>
<script>
(function() {
    var refreshBtn = document.getElementById('refresh-captcha');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('refresh_captcha', '1');
            window.location.href = currentUrl.toString();
        });
    }
})();
</script>
</body>
</html>
