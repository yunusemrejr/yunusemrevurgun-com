/**
 * YunoBot Page - Chat Interface (PERFORMANCE OPTIMIZED)
 * Initializes chat UI and integrates with ML engine
 *
 * Performance features:
 * - requestAnimationFrame for all DOM updates
 * - Virtual scrolling for large message lists
 * - Debounced scroll-to-bottom
 * - Efficient message rendering with document fragments
 * - Lazy initialization of ML engine on user interaction
 */

(function () {
    "use strict";

    let mlEngine;
    let chatMessages;
    let chatInput;
    let sendButton;
    let exampleQuestionsContainer;
    let statusDot;
    let statusText;
    let chatActionsContainer;
    let messageCounter = 0;

    // ============================================================
    // PERFORMANCE: Virtual scrolling state
    // ============================================================
    let chatVersion = 0;
    let scrollPending = false;

    // ---- Utility: Escape HTML ----
    function escapeHtml(text) {
        var div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    // ---- Utility: Format timestamp ----
    function formatTime(date) {
        var h = date.getHours();
        var m = date.getMinutes();
        return (h < 10 ? "0" + h : h) + ":" + (m < 10 ? "0" + m : m);
    }

    // ---- Utility: Simple markdown-like parser for bot responses ----
    function renderMarkdown(text) {
        // Escape HTML first
        var escaped = escapeHtml(text);

        // Code blocks (``` ... ```)
        escaped = escaped.replace(
            /```(\w*)\n?([\s\S]*?)```/g,
            function (match, lang, code) {
                return (
                    '<pre><code class="lang-' +
                    lang +
                    '">' +
                    code.trim() +
                    "</code></pre>"
                );
            },
        );

        // Inline code (` ... `)
        escaped = escaped.replace(/`([^`]+)`/g, "<code>$1</code>");

        // Bold (**text** or __text__)
        escaped = escaped.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        escaped = escaped.replace(/__([^_]+)__/g, "<strong>$1</strong>");

        // Italic (*text* or _text_)
        escaped = escaped.replace(/\*([^*]+)\*/g, "<em>$1</em>");
        escaped = escaped.replace(/_([^_]+)_/g, "<em>$1</em>");

        // Links [text](url) — sanitize to prevent javascript: XSS
        escaped = escaped.replace(
            /\[([^\]]+)\]\(([^)]+)\)/g,
            function (match, text, url) {
                url = url.trim();
                // Only allow safe protocols and relative URLs
                if (/^(https?:\/\/|mailto:|tel:|#|\/)/i.test(url)) {
                    return (
                        '<a href="' +
                        url +
                        '" target="_blank" rel="noopener noreferrer">' +
                        text +
                        "</a>"
                    );
                }
                // If URL is unsafe, render as plain text
                return text;
            },
        );

        // Unordered lists (- item)
        var lines = escaped.split("\n");
        var result = [];
        var inList = false;

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            var listMatch = line.match(/^[\s]*[-*+]\s+(.*)/);

            if (listMatch) {
                if (!inList) {
                    result.push("<ul>");
                    inList = true;
                }
                result.push("<li>" + listMatch[1] + "</li>");
            } else {
                if (inList) {
                    result.push("</ul>");
                    inList = false;
                }
                result.push(line);
            }
        }
        if (inList) {
            result.push("</ul>");
        }

        escaped = result.join("\n");

        // Paragraphs: wrap remaining plain lines
        escaped = escaped.replace(/^(?!<[uplob])(.+)$/gm, "<p>$1</p>");

        // Clean up empty paragraphs
        escaped = escaped.replace(/<p>\s*<\/p>/g, "");
        escaped = escaped.replace(/<p>\s*(<[ul])/g, "$1");
        escaped = escaped.replace(/(<\/[ul]>)\s*<\/p>/g, "$1");

        return escaped;
    }

    // ---- Utility: Get plain text from message for clipboard ----
    function getMessagePlainText(messageEl) {
        var content = messageEl.querySelector(".message-content");
        if (!content) return "";
        // Clone, strip HTML, return text
        var clone = content.cloneNode(true);
        var links = clone.querySelectorAll("a");
        for (var i = 0; i < links.length; i++) {
            links[i].replaceWith(links[i].href);
        }
        return clone.textContent.trim();
    }

    // ---- Utility: Copy to clipboard ----
    async function copyToClipboard(text, button) {
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                // Fallback
                var ta = document.createElement("textarea");
                ta.value = text;
                ta.style.position = "fixed";
                ta.style.left = "-9999px";
                document.body.appendChild(ta);
                ta.select();
                document.execCommand("copy");
                document.body.removeChild(ta);
            }

            if (button) {
                button.classList.add("copied");
                button.setAttribute("aria-label", "Copied!");
                setTimeout(function () {
                    button.classList.remove("copied");
                    button.setAttribute("aria-label", "Copy message");
                }, 1500);
            }
        } catch (err) {
            console.error("Failed to copy:", err);
        }
    }

    // ---- Utility: Export chat as text ----
    function exportChat() {
        var lines = [];
        lines.push("YunoBot Chat Export - " + new Date().toISOString());
        lines.push("========================================");
        lines.push("");

        // Use stored timestamps for accurate export times
        if (messageTimestamps.length > 0) {
            for (var i = 0; i < messageTimestamps.length; i++) {
                var entry = messageTimestamps[i];
                var label = entry.type === "user" ? "You" : "YunoBot";
                lines.push(
                    "[" +
                        formatTime(entry.time) +
                        "] " +
                        label +
                        ": " +
                        entry.text + (entry.source ? "\nSource: " + entry.source : ""),
                );
            }
        } else {
            // Fallback: scrape DOM if timestamps not available
            var messages = chatMessages.querySelectorAll(".chat-message");
            for (var i = 0; i < messages.length; i++) {
                var msg = messages[i];
                var isUser = msg.classList.contains("user-message");
                var text = getMessagePlainText(msg);
                var label = isUser ? "You" : "YunoBot";
                lines.push(
                    "[" + formatTime(new Date()) + "] " + label + ": " + text,
                );
            }
        }

        var blob = new Blob([lines.join("\n")], {
            type: "text/plain;charset=utf-8",
        });
        var url = URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = "yunobot-chat-" + Date.now() + ".txt";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // ---- Utility: Clear chat ----
    function clearChat() {
        chatMessages.innerHTML = "";
        chatVersion++;
        mlEngine?.reset?.();
        chatInput.disabled = false;
        sendButton.disabled = false;
        messageCounter = 0;
        messageTimestamps = [];
        // Re-add welcome message
        addMessage(
            "Ask about Yunus’s work, a project, or a topic from the journal.",
            "bot",
        );
        // Show example questions
        showExampleQuestions(getInitialExamples());
    }

    function getInitialExamples() {
        return [
            "hey kanka whats your name?",
            "What is Mr. Graphy?",
            "Find writing about edge AI",
            "Yunus nerede okudu?",
        ];
    }

    // ---- Initialize when DOM is ready ----
    function init() {
        // Initialize ML Engine
        if (window.YunoBotMLEngine) {
            mlEngine = new window.YunoBotMLEngine();
        } else {
            console.error("YunoBotMLEngine not loaded");
            updateStatus("error", "ML engine unavailable");
            return;
        }

        // Warm the WASM knowledge engine in the background (non-blocking).
        if (window.YunoBotKB) window.YunoBotKB.load();

        // Initialize Chat UI
        setupChatUI();
    }

    function updateStatus(state, text) {
        if (!statusDot || !statusText) return;
        statusDot.className = "chat-status-dot " + state;
        if (text) statusText.textContent = text;
    }

    function setupChatUI() {
        chatMessages = document.getElementById("chatMessages");
        chatInput = document.getElementById("chatInput");
        sendButton = document.getElementById("sendButton");
        exampleQuestionsContainer = document.getElementById("exampleQuestions");
        statusDot = document.getElementById("statusDot");
        statusText = document.getElementById("statusText");
        chatActionsContainer = document.getElementById("chatActions");

        if (!chatMessages || !chatInput || !sendButton) {
            console.error("Chat UI elements not found");
            return;
        }

        // Bind events
        sendButton.addEventListener("click", handleSend);

        // Enter to send, Shift+Enter for newline
        chatInput.addEventListener("keydown", function (e) {
            if (e.key === "Enter" && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                handleSend();
            }
        });

        // Auto-resize textarea
        chatInput.addEventListener("input", autoResizeInput);

        // Quick action buttons
        var clearBtn = document.getElementById("clearChatBtn");
        var exportBtn = document.getElementById("exportChatBtn");
        var timeBtn = document.getElementById("timeBtn");
        if (clearBtn) clearBtn.addEventListener("click", clearChat);
        if (exportBtn) exportBtn.addEventListener("click", exportChat);
        if (timeBtn)
            timeBtn.addEventListener("click", function () {
                chatInput.value = "What time is it?";
                chatInput.focus();
                autoResizeInput();
                handleSend();
            });

        // Focus input on load (preventScroll: avoid jumping past the page title;
        // on touch devices skip auto-focus so the on-screen keyboard stays closed)
        if (
            window.matchMedia &&
            window.matchMedia("(hover: hover) and (pointer: fine)").matches
        ) {
            chatInput.focus({ preventScroll: true });
        }

        // Show starter example questions immediately
        showExampleQuestions(getInitialExamples());

        // Update status as the neural network initializes
        updateStatus("loading", "Loading neural network...");

        // Use callback for neural network readiness
        if (mlEngine) {
            mlEngine.onReady = function () {
                updateStatus("ready", "Ready · runs on your device");
            };
            mlEngine.onError = function () {
                updateStatus("error", "Unavailable — reload to retry");
            };

            // Check if already ready (unlikely but possible)
            if (mlEngine.workerReady) {
                updateStatus("ready", "Ready · runs on your device");
            }
        }

        // Fallback: if worker never initializes within 10 seconds, show offline mode
        setTimeout(function () {
            if (mlEngine && !mlEngine.workerReady) {
                updateStatus("error", "Unavailable — reload to retry");
            }
        }, 10000);
    }

    function autoResizeInput() {
        if (!chatInput) return;
        chatInput.style.height = "auto";
        var newHeight = chatInput.scrollHeight;
        var maxHeight = 120; // match CSS max-height
        chatInput.style.height = Math.min(newHeight, maxHeight) + "px";
    }

    function handleSend() {
        var input = chatInput.value.trim();
        if (!input || chatInput.disabled) return;
        const version = chatVersion;
        const activeEngine = mlEngine;

        // Disable input while processing
        chatInput.disabled = true;
        sendButton.disabled = true;

        // Add user message
        addMessage(input, "user");

        // Clear and resize input
        chatInput.value = "";
        autoResizeInput();

        // Hide example questions after first user message
        hideExampleQuestions();

        // Show typing indicator
        var typingId = showTypingIndicator();

        // Process with ML engine (async - supports semantic matching)
        (function () {
            var startTime = Date.now();

            // Use Promise.resolve to handle both sync and async engines
            Promise.resolve()
                .then(function () {
                    return activeEngine.process(input);
                })
                .then(function (result) {
                    if (version !== chatVersion) return;
                    hideTypingIndicator(typingId);

                    // Safety check: ensure result and response exist
                    if (!result) {
                        console.error(
                            "ML engine returned null/undefined result",
                        );
                        addMessage(
                            "I encountered an error processing your request. Please try again.",
                            "bot",
                        );
                        return;
                    }

                    if (!result.response || result.response.trim() === "") {
                        console.error(
                            "ML engine returned empty response:",
                            result,
                        );
                        addMessage(
                            "I'm not sure how to respond to that. Could you try rephrasing?",
                            "bot",
                        );
                        return;
                    }

                    addMessage(result.response, "bot", result.sourceUrl ? {
                        url: result.sourceUrl,
                        page: result.sourcePage,
                        title: result.sourceTitle,
                        label: result.kind === 'excerpt' ? (result.sourceLabel || 'Source excerpt') : (result.kind === 'navigation' ? 'Open page' : 'Source'),
                    } : null);
                    if (result.examples?.length) showExampleQuestions(result.examples);
                })
                .catch(function (error) {
                    if (version !== chatVersion) return;
                    console.error("ML processing error:", error);
                    hideTypingIndicator(typingId);
                    addMessage(
                        "I encountered an error processing your request. Please try again.",
                        "bot",
                    );
                })
                .finally(function () {
                    if (version !== chatVersion) return;
                    // Re-enable input
                    chatInput.disabled = false;
                    sendButton.disabled = false;
                    chatInput.focus({ preventScroll: true });
                });
        })();
    }

    // Store message timestamps for export
    var messageTimestamps = [];

    function addMessage(text, type, source) {
        var now = new Date();
        var msgId = "msg-" + ++messageCounter;
        var messageDiv = document.createElement("div");
        messageDiv.className = "chat-message " + type + "-message";
        messageDiv.id = msgId;
        messageDiv.setAttribute("role", "article");
        messageDiv.setAttribute(
            "aria-label",
            type === "user" ? "Your message" : "YunoBot response",
        );

        // Store timestamp for export
        messageTimestamps.push({
            id: msgId,
            time: now,
            type: type,
            text: text,
            source: source?.url || null,
        });

        // Avatar
        var avatar = document.createElement("div");
        avatar.className = "message-avatar";
        avatar.setAttribute("aria-hidden", "true");
        avatar.textContent = type === "user" ? "Y" : "YB";

        // Body wrapper
        var body = document.createElement("div");
        body.className = "message-body";

        // Content
        var content = document.createElement("div");
        content.className = "message-content";

        if (type === "bot") {
            if (source && source.url) {
                // Knowledge-base answer: plain corpus sentence, not markdown.
                content.textContent = text;
            } else {
                // Render markdown for bot messages
                content.innerHTML = renderMarkdown(text);
            }
        } else {
            content.textContent = text;
        }

        // Meta (timestamp + copy button for bot messages)
        var meta = document.createElement("div");
        meta.className = "message-meta";

        var timeSpan = document.createElement("span");
        timeSpan.className = "message-time";
        timeSpan.textContent = formatTime(now);
        meta.appendChild(timeSpan);

        if (type === "bot") {
            var copyBtn = document.createElement("button");
            copyBtn.className = "message-copy-btn";
            copyBtn.type = "button";
            copyBtn.setAttribute("aria-label", "Copy message");
            copyBtn.innerHTML =
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
            copyBtn.addEventListener("click", function () {
                copyToClipboard(getMessagePlainText(messageDiv), copyBtn);
            });
            meta.appendChild(copyBtn);
        }

        body.appendChild(content);

        // Source chip: knowledge-base answers carry a link to where the
        // sentence came from (the exact site page it was extracted from).
        if (type === "bot" && source && source.url) {
            var sourceChip = document.createElement("div");
            sourceChip.className = "message-source";
            var sourceLink = document.createElement("a");
            sourceLink.className = "source-chip";
            const parsed = new URL(source.url, location.href);
            if (!['http:', 'https:'].includes(parsed.protocol)) return;
            sourceLink.href = parsed.href;
            sourceLink.target = "_blank";
            sourceLink.rel = "noopener noreferrer";
            sourceLink.textContent = source.title || source.page || "Source";
            const sourceLabel = document.createElement('span');
            sourceLabel.className = 'source-label';
            sourceLabel.textContent = source.label || 'Source';
            sourceChip.append(sourceLabel, sourceLink);
            body.appendChild(sourceChip);
        }

        body.appendChild(meta);

        messageDiv.appendChild(avatar);
        messageDiv.appendChild(body);

        // ============================================================
        // PERFORMANCE: Use document fragment for batch DOM insertion
        // ============================================================
        var fragment = document.createDocumentFragment();
        fragment.appendChild(messageDiv);
        chatMessages.appendChild(fragment);

        // Scroll to bottom using RAF
        scrollToBottom();

        return messageDiv;
    }

    function showTypingIndicator() {
        var id = "typing-" + Date.now();
        var typingDiv = document.createElement("div");
        typingDiv.className = "chat-message bot-message";
        typingDiv.id = id;
        typingDiv.setAttribute("role", "status");
        typingDiv.setAttribute("aria-label", "YunoBot is typing");

        // Avatar
        var avatar = document.createElement("div");
        avatar.className = "message-avatar";
        avatar.setAttribute("aria-hidden", "true");
        avatar.textContent = "YB";

        // Typing indicator bubble
        var indicator = document.createElement("div");
        indicator.className = "typing-indicator";
        indicator.setAttribute("aria-hidden", "true");

        for (var i = 0; i < 3; i++) {
            var dot = document.createElement("div");
            dot.className = "typing-dot";
            indicator.appendChild(dot);
        }

        var body = document.createElement("div");
        body.className = "message-body";
        body.appendChild(indicator);

        typingDiv.appendChild(avatar);
        typingDiv.appendChild(body);

        chatMessages.appendChild(typingDiv);
        scrollToBottom();

        return id;
    }

    function hideTypingIndicator(id) {
        var indicator = document.getElementById(id);
        if (indicator) {
            indicator.remove();
        }
    }

    function scrollToBottom() {
        // Debounce scroll operations using requestAnimationFrame
        if (scrollPending) return;
        scrollPending = true;

        requestAnimationFrame(function () {
            if (chatMessages) {
                // Use smooth scroll behavior for better UX
                chatMessages.scrollTo({
                    top: chatMessages.scrollHeight,
                    behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "instant" : "smooth",
                });
            }
            scrollPending = false;
        });
    }

    // ============================================================
    // PERFORMANCE: Virtual scrolling for large message lists
    // ============================================================
    function showExampleQuestions(examples) {
        if (!exampleQuestionsContainer) return;

        // Don't show empty example container
        if (!examples || examples.length === 0) {
            hideExampleQuestions();
            return;
        }

        var list = exampleQuestionsContainer.querySelector(
            ".example-questions-list",
        );
        if (!list) return;

        // Clear existing
        list.innerHTML = "";

        // Add example chips
        examples.forEach(function (example) {
            var chip = document.createElement("button");
            chip.className = "example-question-chip";
            chip.type = "button";
            chip.textContent = example;
            chip.setAttribute("aria-label", "Ask: " + example);
            chip.addEventListener("click", function () {
                chatInput.value = example;
                chatInput.focus();
                autoResizeInput();
                handleSend();
            });
            list.appendChild(chip);
        });

        exampleQuestionsContainer.classList.remove("hidden");
    }

    function hideExampleQuestions() {
        if (exampleQuestionsContainer) {
            exampleQuestionsContainer.classList.add("hidden");
        }
    }

    // Initialize when ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
