/** Form validation and explicit, local-only recovery. Publishing requires Save. */
(function ($) {
  'use strict';
  $.extend(AdminPanel, {
    autoSaveTimeout: null,
    initForms: function () {
      this.setupCSRFTokens();
      this.initSlugGeneration();
      this.draftForm = document.querySelector('.admin-form:has([name="content"]), .blog-admin-form:has([name="content"])');
      this.draftDirty = false;
      if (this.draftForm) {
        this.restoreLocalDraft();
        this.saveStatus = document.createElement('p');
        this.saveStatus.className = 'admin-save-status';
        this.saveStatus.setAttribute('role', 'status');
        this.draftForm.append(this.saveStatus);
        $(this.draftForm).on('input change', () => {
          this.draftDirty = true;
          this.scheduleAutoSave();
        });
        window.addEventListener('beforeunload', event => {
          if (!this.draftDirty) return;
          this.saveLocalDraft();
          event.preventDefault();
          event.returnValue = '';
        });
      }
      $('.admin-form, .blog-admin-form').on('submit', function (event) {
        if (typeof AdminPanel.syncEditorContent === 'function') AdminPanel.syncEditorContent();
        if (!AdminPanel.validateForm($(this))) { event.preventDefault(); return; }
        // Other handlers can still reject this submission (AJAX forms included).
        queueMicrotask(() => {
          if (event.isDefaultPrevented()) return;
          clearTimeout(AdminPanel.autoSaveTimeout);
          AdminPanel.saveLocalDraft();
          AdminPanel.draftDirty = false;
        });
      });
    },
    setupCSRFTokens: function () {
      const token = $('meta[name="csrf-token"]').attr('content');
      if (token) $.ajaxSetup({ beforeSend: xhr => xhr.setRequestHeader('X-CSRF-Token', token) });
    },
    validateForm: function ($form) {
      const form = $form[0];
      $form.find('[required]').each(function () { $(this).toggleClass('is-invalid', !this.checkValidity()); });
      if (!form.checkValidity()) { form.reportValidity(); return false; }
      return true;
    },
    scheduleAutoSave: function () {
      if (!this.draftForm || !this.draftDirty) return;
      clearTimeout(this.autoSaveTimeout);
      this.autoSaveTimeout = setTimeout(() => this.performAutoSave(), 1200);
    },
    performAutoSave: function () {
      if (!this.draftDirty) return;
      this.saveLocalDraft();
    },
    autoSave: function () { this.performAutoSave(); },
    initSlugGeneration: function () {
      const $title = $('#title'), $slug = $('#slug');
      // Existing URLs stay stable when an article title is edited.
      if ($slug.val()) $slug.data('manualEdit', true);
      $slug.on('input', function () { $(this).data('manualEdit', true); });
      $title.on('input', function () {
        if (!$slug.data('manualEdit')) $slug.val(AdminPanel.generateSlug($(this).val()));
      });
    },
    generateSlug: function (text) {
      return text.toLowerCase().replace(/ı/g, 'i').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .trim().replace(/[^a-z0-9\s-]/g, '').replace(/[\s_-]+/g, '-').replace(/^-+|-+$/g, '');
    },
    getDraftKey: function () { return 'admin-draft:' + location.pathname + location.search; },
    saveLocalDraft: function () {
      if (!this.draftForm || !this.draftDirty) return;
      const fields = {};
      this.draftForm.querySelectorAll('input[type="text"], input[type="date"], textarea, select').forEach(field => {
        if (field.name) fields[field.name] = field.value;
      });
      const editor = this.draftForm.querySelector('[contenteditable="true"]');
      if (editor) fields.content = editor.innerHTML;
      try {
        localStorage.setItem(this.getDraftKey(), JSON.stringify({savedAt: Date.now(), fields}));
        if (this.saveStatus) this.saveStatus.textContent = 'Draft saved in this browser. Use Save to update the website.';
      } catch (_) {
        if (this.saveStatus) this.saveStatus.textContent = 'Browser draft storage is unavailable. Keep this page open until you save.';
      }
    },
    restoreLocalDraft: function () {
      let saved;
      try { saved = JSON.parse(localStorage.getItem(this.getDraftKey()) || 'null'); } catch (_) { return; }
      if (!saved?.fields || Date.now() - saved.savedAt > 86400000) return;
      // A successful save needs no recovery prompt.
      const differs = Object.entries(saved.fields).some(([name, value]) => {
        const field = Array.from(this.draftForm.elements).find(el => el.name === name);
        return field && field.value !== value;
      });
      if (!differs) return;
      const notice = document.createElement('div');
      notice.className = 'admin-draft-notice';
      const message = document.createElement('p');
      message.textContent = 'A browser draft from ' + new Date(saved.savedAt).toLocaleString() + ' is available.';
      const restore = document.createElement('button');
      restore.type = 'button'; restore.className = 'admin-btn admin-btn-secondary'; restore.textContent = 'Restore draft';
      const discard = document.createElement('button');
      discard.type = 'button'; discard.className = 'admin-btn admin-btn-secondary'; discard.textContent = 'Discard draft';
      restore.addEventListener('click', () => {
        for (const [name, value] of Object.entries(saved.fields)) {
          const field = Array.from(this.draftForm.elements).find(el => el.name === name);
          if (field && typeof value === 'string') {
            field.value = value;
            field.dispatchEvent(new Event('input', {bubbles: true}));
          }
        }
        const editor = this.draftForm.querySelector('[contenteditable="true"]');
        if (editor && typeof saved.fields.content === 'string') editor.innerHTML = saved.fields.content;
        this.draftDirty = true;
        if (typeof this.syncEditorContent === 'function') this.syncEditorContent();
        notice.remove();
      });
      discard.addEventListener('click', () => {
        try { localStorage.removeItem(this.getDraftKey()); } catch (_) {}
        notice.remove();
      });
      notice.append(message, restore, discard);
      this.draftForm.before(notice);
    }
  });
  $(function () { AdminPanel.initForms(); });
})(jQuery);
