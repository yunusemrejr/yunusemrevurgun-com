(function() {
    'use strict';

    var chat = document.getElementById('yunobotChat');
    var messages = document.getElementById('yunobotMessages');
    var form = document.getElementById('yunobotForm');
    var input = document.getElementById('yunobotInput');
    var archBtn = document.getElementById('yunobotArchBtn');
    var archModal = document.getElementById('yunobotArchModal');

    if (!chat || !messages || !form || !input) return;

    var BASE_PATH = window.FULL_BASE_PATH || '/';
    var brain = null;
    var brainReady = false;
    var conversationState = {
        aim: 'balanced',
        goal: null,
        lastRoute: null,
        lastIntent: null
    };

    function initBrain() {
        try {
            if (window.YunoML && typeof window.YunoML.createBrain === 'function') {
                brain = window.YunoML.createBrain(BASE_PATH);
                brainReady = true;
                console.log('YunoBot ML brain initialized');
            } else {
                console.warn('YunoML.createBrain not available');
            }
        } catch (err) {
            console.error('Failed to initialize YunoBot brain:', err);
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function addMessage(text, isUser, isHtml) {
        var msg = document.createElement('div');
        msg.className = 'ui-yunobot-message' + (isUser ? ' ui-yunobot-message-user' : ' ui-yunobot-message-bot');
        var content = isHtml ? text : escapeHtml(text);
        msg.innerHTML = '<span class="ui-yunobot-icon">&gt;</span><span class="ui-yunobot-text">' + content + '</span>';
        messages.appendChild(msg);
        messages.scrollTop = messages.scrollHeight;
        return msg;
    }

    function addTypingIndicator() {
        var msg = document.createElement('div');
        msg.className = 'ui-yunobot-message ui-yunobot-message-bot ui-yunobot-typing';
        msg.innerHTML = '<span class="ui-yunobot-icon">&gt;</span><span class="ui-yunobot-text">Thinking...</span>';
        messages.appendChild(msg);
        messages.scrollTop = messages.scrollHeight;
        return msg;
    }

    function removeTypingIndicator(indicator) {
        if (indicator && indicator.parentNode) {
            indicator.parentNode.removeChild(indicator);
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        var text = input.value.trim();
        if (!text) return;

        addMessage(text, true);
        input.value = '';
        input.focus();

        var typingIndicator = addTypingIndicator();

        setTimeout(function() {
            removeTypingIndicator(typingIndicator);

            var response;
            var hasHtml = false;
            if (brainReady && brain) {
                try {
                    var result = brain.infer(text, conversationState);
                    response = result.response;

                    if (result.toolCall && result.toolCall.args && result.toolCall.args.url) {
                        response += '\n\n<a href="' + result.toolCall.args.url + '" class="ui-yunobot-link">Go to ' + (result.toolCall.args.page || 'page') + '</a>';
                        hasHtml = true;
                    }
                } catch (err) {
                    console.error('ML inference error:', err);
                    response = getFallbackResponse(text);
                }
            } else {
                response = getFallbackResponse(text);
            }

            addMessage(response, false, hasHtml);
        }, 800);
    }

    function getFallbackResponse(text) {
        var lower = text.toLowerCase();
        if (/^(hello|hi|hey|yo|sup)/.test(lower)) {
            return "Hey there! I'm YunoBot. I can help you navigate this site or answer questions about Yunus's work. Try asking about portfolio, blog, or projects.";
        }
        if (/who|yunus|bio|about/.test(lower)) {
            return "Yunus Emre Vurgun is a software developer and IT specialist based in Istanbul. He works at ASP Otomasyon A.Ş. building industrial systems and publishes OSS on GitHub.";
        }
        if (/project|portfolio|work/.test(lower)) {
            return "Yunus has worked on AI/ML projects, operational technology systems, and backend architectures. Check the portfolio for details on his recent projects.";
        }
        if (/blog|post|article/.test(lower)) {
            return "The blog covers technical topics, project updates, and thoughts on software development and technology.";
        }
        if (/help|command/.test(lower)) {
            return "You can ask me about pages on this site, like 'show me your portfolio' or 'what about the blog'. You can also use /go <page> to navigate directly.";
        }
        return "I'm here to help you find information about Yunus Emre Vurgun's work. You can ask about portfolio, projects, blog posts, or use commands like /go portfolio.";
    }

    initBrain();

    form.addEventListener('submit', handleSubmit);

    if (archBtn && archModal) {
        var modalPanel = archModal.querySelector('.ui-modal-panel');
        var firstFocusable = archModal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        
        archBtn.addEventListener('click', function() {
            archModal.classList.add('is-open');
            archModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            // Focus management: move focus to first focusable element in modal
            if (firstFocusable) {
                firstFocusable.focus();
            }
            // Store reference to previously focused element for restoration
            archModal._previousFocus = document.activeElement;
        });

        var closeBtn = archModal.querySelector('.ui-modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeModal();
            });
        }

        var backdrop = archModal.querySelector('.ui-modal-backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', function() {
                closeModal();
            });
        }

        function closeModal() {
            archModal.classList.remove('is-open');
            archModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            // Restore focus to previously focused element
            if (archModal._previousFocus && archModal._previousFocus.focus) {
                archModal._previousFocus.focus();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && archModal.classList.contains('is-open')) {
                closeModal();
            }
            // Focus trap: if modal is open and focus leaves the panel, bring it back
            if (archModal.classList.contains('is-open') && modalPanel && !modalPanel.contains(document.activeElement) && !archModal.querySelector('.ui-modal-close').contains(document.activeElement)) {
                if (firstFocusable) {
                    firstFocusable.focus();
                }
            }
        });
    }
})();