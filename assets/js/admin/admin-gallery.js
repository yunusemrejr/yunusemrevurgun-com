(function($) {
    'use strict';

    function extendAdminPanel() {
        if (typeof AdminPanel === 'undefined') {
            setTimeout(extendAdminPanel, 100);
            return;
        }

        $.extend(AdminPanel, {

        initGallery: function() {
            this.initUploadModal();
            this.initImagePreview();
            this.initDragAndDrop();
            this.bindGalleryEvents();
            this.initAlbumModal();
            this.bindAlbumEvents();
        },

        // In-flight upload XHR so Cancel/close actually aborts the request.
        currentUploadXhr: null,

        setUploadStatus: function(text, isError) {
            const $status = $('#uploadProgressText');
            if ($status.length) {
                $status.text(text || '').toggleClass('is-error', !!isError);
            }
        },

        abortCurrentUpload: function() {
            if (this.currentUploadXhr) {
                try { this.currentUploadXhr.abort(); } catch (e) { /* already finished */ }
                this.currentUploadXhr = null;
            }
        },

        // Oversized images are compressed in the browser before upload instead
        // of being rejected with a "under 5MB" error. HEIC/other decode
        // failures and GIFs fall back to the original file; the server
        // re-compresses those (and GIFs are exempt there too).
        MAX_UPLOAD_BYTES: 5 * 1024 * 1024,
        MAX_IMAGE_EDGE: 2560,

        compressImageFile: function(file) {
            if (file.size <= AdminPanel.MAX_UPLOAD_BYTES || file.type === 'image/gif') {
                return Promise.resolve(file);
            }
            return new Promise(function(resolve) {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = function() {
                    URL.revokeObjectURL(url);
                    const base = (file.name || 'image').replace(/\.[^.]+$/, '');
                    const scale = Math.min(1, AdminPanel.MAX_IMAGE_EDGE / Math.max(img.naturalWidth || 1, img.naturalHeight || 1));
                    const w = Math.max(1, Math.round((img.naturalWidth || 1) * scale));
                    const h = Math.max(1, Math.round((img.naturalHeight || 1) * scale));
                    let canvas, ctx;
                    try {
                        canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        ctx = canvas.getContext('2d');
                        // White backdrop: PNG transparency cannot survive JPEG.
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, w, h);
                        ctx.drawImage(img, 0, 0, w, h);
                    } catch (e) {
                        resolve(file);
                        return;
                    }
                    const toJpeg = function(q) {
                        return new Promise(function(res) {
                            canvas.toBlob(function(blob) { res(blob); }, 'image/jpeg', q);
                        });
                    };
                    toJpeg(0.85).then(function(blob) {
                        if (!blob) { resolve(file); return; }
                        if (blob.size > AdminPanel.MAX_UPLOAD_BYTES) {
                            // One retry at lower quality before giving up to the server.
                            toJpeg(0.65).then(function(blob2) {
                                if (!blob2) { resolve(new File([blob], base + '.jpg', { type: 'image/jpeg' })); return; }
                                resolve(new File([blob2.size < blob.size ? blob2 : blob], base + '.jpg', { type: 'image/jpeg' }));
                            });
                            return;
                        }
                        resolve(new File([blob], base + '.jpg', { type: 'image/jpeg' }));
                    });
                };
                img.onerror = function() {
                    URL.revokeObjectURL(url);
                    resolve(file);
                };
                img.src = url;
            });
        },

        initUploadModal: function() {
            window.openUploadModal = this.openUploadModal.bind(this);
            window.closeUploadModal = this.closeUploadModal.bind(this);
            window.uploadImages = this.uploadImages.bind(this);
            window.deleteImage = this.deleteImage.bind(this);
            window.archiveImage = this.archiveImage.bind(this);
            window.restoreImage = this.restoreImage.bind(this);
            window.editImage = this.editImage.bind(this);
        },

        openUploadModal: function() {
            const $modal = $('#uploadModal');
            if ($modal.length) {
                $modal.css('display', 'flex');
                $('body').css('overflow', 'hidden');
                AdminPanel.setUploadStatus('');
                AdminPanel.uploadAborted = false;
                const $firstInput = $modal.find('input[type="file"]');
                if ($firstInput.length) {
                    setTimeout(() => $firstInput.focus(), 100);
                }
            }
        },

        closeUploadModal: function() {
            // If an upload is in flight, Cancel must actually stop it — a
            // hidden request kept the modal's button disabled and the
            // server busy (see abortCurrentUpload). Also cancels the
            // in-browser compression step (see uploadImages).
            AdminPanel.uploadAborted = true;
            AdminPanel.abortCurrentUpload();
            const $modal = $('#uploadModal');
            if ($modal.length) {
                $modal.css('display', 'none');
                $('body').css('overflow', '');
                const $form = $('#uploadForm');
                if ($form.length) {
                    $form[0].reset();
                }
                const $preview = $('#uploadPreview');
                if ($preview.length) {
                    $preview.empty();
                }
                const $progress = $('#uploadProgress');
                if ($progress.length) {
                    $progress.hide();
                }
                AdminPanel.setUploadStatus('');
            }
        },

        uploadImages: async function() {
            const $form = $('#uploadForm');
            const $images = $('#images');
            const $title = $('#imageTitle');
            const $albumSelect = $('#albumSelect');
            const $progress = $('#uploadProgress');

            if (!$images[0].files.length) {
                AdminPanel.showNotification('Please select at least one image', 'error');
                return;
            }
            if ($images[0].files.length > 12) {
                AdminPanel.showNotification('Please upload 12 images or fewer at once', 'error');
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
            const invalidFile = Array.from($images[0].files).find((file) => {
                return allowedTypes.indexOf(file.type) === -1;
            });
            if (invalidFile) {
                AdminPanel.showNotification('Invalid image: ' + invalidFile.name + '. Use JPEG, PNG, GIF, WebP, or HEIC.', 'error');
                return;
            }

            $progress.show();
            this.updateProgress(0);
            $('#startUpload').prop('disabled', true);

            // Oversized images are compressed in-browser first: batches stay
            // under the server's post size and the mobile uplink time drops.
            // Cancel during compression stops everything — no XHR starts after.
            let files = Array.from($images[0].files);
            const oversized = files.filter((f) => f.size > AdminPanel.MAX_UPLOAD_BYTES && f.type !== 'image/gif');
            AdminPanel.uploadAborted = false;
            for (let i = 0; i < oversized.length; i++) {
                if (AdminPanel.uploadAborted) return;
                AdminPanel.setUploadStatus('Compressing large images (' + (i + 1) + '/' + oversized.length + ')…');
                const compressed = await AdminPanel.compressImageFile(oversized[i]);
                if (AdminPanel.uploadAborted) return;
                if (compressed && compressed !== oversized[i]) {
                    files[files.indexOf(oversized[i])] = compressed;
                }
            }
            if (AdminPanel.uploadAborted) return;

            const totalBytes = files.reduce((sum, f) => sum + f.size, 0);
            if (totalBytes > 60 * 1024 * 1024) {
                AdminPanel.showNotification('Batch too large after compression (' + Math.round(totalBytes / 1048576) + 'MB). Please upload in smaller batches.', 'error');
                AdminPanel.setUploadStatus('Batch too large (' + Math.round(totalBytes / 1048576) + 'MB). Please split into smaller batches.', true);
                this.updateProgress(0);
                $('#startUpload').prop('disabled', false);
                return;
            }

            AdminPanel.setUploadStatus('Uploading 0%…');

            const formData = new FormData();
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            formData.append('csrf_token', csrfToken);
            formData.append('imageTitle', $title.val());
            formData.append('album_id', $albumSelect.val());

            files.forEach((file) => {
                formData.append('images[]', file);
            });

            const uploadUrl = window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/upload.php';

            $.ajax({
                url: uploadUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    AdminPanel.currentUploadXhr = xhr;
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = evt.loaded / evt.total * 100;
                            AdminPanel.updateProgress(percentComplete);
                            AdminPanel.setUploadStatus('Uploading ' + Math.round(percentComplete) + '%…');
                        }
                    }, false);
                    // Transfer finished — the server is now validating + processing
                    // the files (HEIC conversion can take a while on shared hosting).
                    xhr.upload.addEventListener("load", function() {
                        AdminPanel.setUploadStatus('Uploading complete — processing images on the server…');
                    }, false);
                    return xhr;
                },
                timeout: 180000,
                success: function(response) {
                    AdminPanel.updateProgress(100);
                    let responseData = response;
                    if (typeof response === 'string') {
                        try {
                            responseData = JSON.parse(response);
                        } catch (e) {
                            AdminPanel.showNotification('Upload failed: Invalid response format', 'error');
                            AdminPanel.setUploadStatus('Upload failed: the server returned an unreadable response. Please try again.', true);
                            $('#startUpload').prop('disabled', false);
                            return;
                        }
                    }
                    if (responseData.success) {
                        const failed = Array.isArray(responseData.errors) ? responseData.errors : [];
                        if (failed.length > 0) {
                            AdminPanel.showNotification('Uploaded with ' + failed.length + ' failed file(s): ' + failed.join('; '), 'error');
                        } else {
                            AdminPanel.showNotification('Images uploaded successfully', 'success');
                        }
                        AdminPanel.closeUploadModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, failed.length > 0 ? 3000 : 1000);
                    } else {
                        AdminPanel.showNotification('Upload failed: ' + (responseData.message || 'Unknown error'), 'error');
                        AdminPanel.setUploadStatus('Upload failed: ' + (responseData.message || 'Unknown error') + ' Your files are still selected — fix the issue and try again.', true);
                        AdminPanel.updateProgress(0);
                        $('#startUpload').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    // A request we aborted ourselves (Cancel/close) is not an error.
                    if (status === 'abort') {
                        AdminPanel.setUploadStatus('Upload cancelled.', true);
                        return;
                    }
                    AdminPanel.updateProgress(0);
                    $('#startUpload').prop('disabled', false);
                    let errorMessage = 'Upload failed';
                    if (xhr.status === 401) {
                        errorMessage = 'Your session expired. Please refresh the page and log in again.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Security check failed (stale page). Please refresh and try again.';
                    } else if (status === 'timeout') {
                        errorMessage = 'Upload timed out after 3 minutes. Try fewer or smaller files.';
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            errorMessage = errorResponse.message || errorMessage;
                        } catch (e) {
                            errorMessage += ': ' + xhr.responseText.slice(0, 200);
                        }
                    } else if (error) {
                        errorMessage += ': ' + error;
                    }
                    AdminPanel.showNotification(errorMessage, 'error');
                    AdminPanel.setUploadStatus(errorMessage + ' Your files are still selected — try again.', true);
                }
            });
        },

        updateProgress: function(percent) {
            const $progressBar = $('#uploadProgress .admin-upload-progress-bar');
            if ($progressBar.length) {
                $progressBar.css('width', percent + '%').attr('aria-valuenow', percent);
                $progressBar.text(Math.round(percent) + '%');
            }
        },

        deleteImage: function(imageId) {
            if (!imageId) return;
            if (confirm('Are you sure you want to permanently delete this image? This cannot be undone.')) {
                $.ajax({
                    url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/delete.php',
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    data: {
                        id: imageId,
                        csrf_token: $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`.admin-gallery-item[data-image-id="${imageId}"]`).fadeOut(300, function() {
                                $(this).remove();
                                AdminPanel.showNotification('Image deleted permanently', 'success');
                            });
                        } else {
                            AdminPanel.showNotification('Failed to delete: ' + (response.message || 'Unknown error'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        AdminPanel.showNotification('Failed to delete: ' + error, 'error');
                    }
                });
            }
        },

        archiveImage: function(imageId) {
            if (!imageId) return;
            if (confirm('Archive this image? It will be hidden from the public gallery but can be restored later.')) {
                $.ajax({
                    url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/archive.php',
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    data: {
                        id: imageId,
                        action: 'archive',
                        csrf_token: $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`.admin-gallery-item[data-image-id="${imageId}"]`).fadeOut(300, function() {
                                $(this).remove();
                                AdminPanel.showNotification('Image archived', 'success');
                            });
                        } else {
                            AdminPanel.showNotification('Failed to archive: ' + (response.message || 'Unknown error'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        AdminPanel.showNotification('Failed to archive: ' + error, 'error');
                    }
                });
            }
        },

        editImage: function(imageId, currentTitle) {
            if (!imageId) return;
            const newTitle = prompt('Edit image title:', currentTitle || '');
            if (newTitle === null || newTitle.trim() === '' || newTitle === currentTitle) return;
            $.ajax({
                url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/update.php',
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: {
                    id: imageId,
                    title: newTitle.trim(),
                    csrf_token: $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Title updated', 'success');
                        setTimeout(function() { window.location.reload(); }, 800);
                    } else {
                        AdminPanel.showNotification('Failed to update: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to update: ' + error, 'error');
                }
            });
        },

        restoreImage: function(imageId) {
            if (!imageId) return;
            $.ajax({
                url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/archive.php',
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: {
                    id: imageId,
                    action: 'restore',
                    csrf_token: $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        $(`.admin-gallery-item[data-image-id="${imageId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            AdminPanel.showNotification('Image restored to active gallery', 'success');
                        });
                    } else {
                        AdminPanel.showNotification('Failed to restore: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to restore: ' + error, 'error');
                }
            });
        },

        // ==================== ALBUM METHODS ====================

        initAlbumModal: function() {
            window.openAlbumModal = this.openAlbumModal.bind(this);
            window.closeAlbumModal = this.closeAlbumModal.bind(this);
            window.createAlbum = this.createAlbum.bind(this);
            window.editAlbum = this.editAlbum.bind(this);
            window.deleteAlbum = this.deleteAlbum.bind(this);
            window.assignImageToAlbum = this.assignImageToAlbum.bind(this);
        },

        openAlbumModal: function(albumId, albumName, albumDescription) {
            const $modal = $('#albumModal');
            if ($modal.length) {
                $modal.css('display', 'flex');
                $('body').css('overflow', 'hidden');
                
                const $title = $('#albumModalTitle');
                const $id = $('#albumId');
                const $name = $('#albumName');
                const $desc = $('#albumDescription');
                
                if (albumId) {
                    $title.text('Edit Album');
                    $id.val(albumId);
                    $name.val(albumName || '');
                    $desc.val(albumDescription || '');
                } else {
                    $title.text('Create Album');
                    $id.val('');
                    $name.val('');
                    $desc.val('');
                }
                
                setTimeout(() => $name.focus(), 100);
            }
        },

        closeAlbumModal: function() {
            const $modal = $('#albumModal');
            if ($modal.length) {
                $modal.css('display', 'none');
                $('body').css('overflow', '');
                const $form = $('#albumForm');
                if ($form.length) {
                    $form[0].reset();
                }
            }
        },

        createAlbum: function() {
            const name = $('#albumName').val().trim();
            const description = $('#albumDescription').val().trim();
            
            if (!name) {
                AdminPanel.showNotification('Album name is required', 'error');
                $('#saveAlbumBtn').prop('disabled', false);
                return;
            }
            
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'create');
            formData.append('name', name);
            formData.append('description', description);
            
            $.ajax({
                url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/albums.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                complete: function() {
                    $('#saveAlbumBtn').prop('disabled', false);
                },
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Album created successfully', 'success');
                        AdminPanel.closeAlbumModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        AdminPanel.showNotification('Failed to create album: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to create album: ' + error, 'error');
                }
            });
        },

        editAlbum: function(albumId) {
            const name = $('#albumName').val().trim();
            const description = $('#albumDescription').val().trim();
            
            if (!name) {
                AdminPanel.showNotification('Album name is required', 'error');
                $('#saveAlbumBtn').prop('disabled', false);
                return;
            }
            
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'update');
            formData.append('id', albumId);
            formData.append('name', name);
            formData.append('description', description);
            
            $.ajax({
                url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/albums.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                complete: function() {
                    $('#saveAlbumBtn').prop('disabled', false);
                },
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Album updated successfully', 'success');
                        AdminPanel.closeAlbumModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        AdminPanel.showNotification('Failed to update album: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to update album: ' + error, 'error');
                }
            });
        },

        deleteAlbum: function(albumId) {
            if (!albumId) return;
            if (!confirm('Delete this album? Images in this album will become unassigned but will not be deleted.')) {
                return;
            }
            
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'delete');
            formData.append('id', albumId);
            
            $.ajax({
                url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/albums.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Album deleted successfully', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        AdminPanel.showNotification('Failed to delete album: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to delete album: ' + error, 'error');
                }
            });
        },

        assignImageToAlbum: function(imageId, albumId) {
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'assign');
            formData.append('image_id', imageId);
            formData.append('album_id', albumId);
            
            $.ajax({
                url: window.location.origin + window.location.pathname.replace(/\/admin.*$/, '') + '/api/admin/gallery/albums.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Image album assignment updated', 'success');
                    } else {
                        AdminPanel.showNotification('Failed to update assignment: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to update assignment: ' + error, 'error');
                }
            });
        },

        initImagePreview: function() {
            $(document).on('change', '#images', function() {
                const files = this.files;
                const $preview = $('#uploadPreview');
                if ($preview.length) {
                    $preview.empty();
                    Array.from(files).forEach((file) => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const $img = $('<img>')
                                    .attr('src', e.target.result)
                                    .css({
                                        'max-width': '100px',
                                        'max-height': '100px',
                                        'margin': '5px',
                                        'border-radius': '4px',
                                        'border': '2px solid var(--color-border-strong)'
                                    });
                                $preview.append($img);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            });
        },

        initDragAndDrop: function() {
            const $dropzone = $('.admin-upload-dropzone');
            if ($dropzone.length) {
                $dropzone.on('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('dragover');
                });
                $dropzone.on('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('dragover');
                });
                $dropzone.on('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('dragover');
                    const files = e.originalEvent.dataTransfer.files;
                    const $fileInput = $('#images');
                    if ($fileInput.length && files.length > 0) {
                        $fileInput[0].files = files;
                        $fileInput.trigger('change');
                    }
                });
                // Click handler for desktop
                $dropzone.on('click', function(e) {
                    // The file input lives inside the dropzone — ignore clicks
                    // bubbling back from it or .trigger('click') recurses forever.
                    if (e.target && e.target.type === 'file') return;
                    $('#images').trigger('click');
                });
                // Touch handlers for mobile (iPhone/iPad)
                $dropzone.on('touchstart', function(e) {
                    $(this).addClass('dragover');
                });
                $dropzone.on('touchend', function(e) {
                    e.preventDefault();
                    $(this).removeClass('dragover');
                    $('#images').click();
                });
            }
        },

        bindGalleryEvents: function() {
            $(document).on('click', '#uploadModal', function(e) {
                if (e.target === this) {
                    AdminPanel.closeUploadModal();
                }
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#uploadModal').is(':visible')) {
                    AdminPanel.closeUploadModal();
                }
            });

            $(document).on('click', '[data-action="edit"]', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var title = $(this).data('title');
                AdminPanel.editImage(id, title);
            });

            $(document).on('click', '[data-action="archive"]', function(e) {
                e.preventDefault();
                AdminPanel.archiveImage($(this).data('id'));
            });

            $(document).on('click', '[data-action="restore"]', function(e) {
                e.preventDefault();
                AdminPanel.restoreImage($(this).data('id'));
            });

            $(document).on('click', '[data-action="delete"]', function(e) {
                e.preventDefault();
                AdminPanel.deleteImage($(this).data('id'));
            });
            
            // Album select change for images
            $(document).on('change', '.admin-album-select', function() {
                const imageId = $(this).data('image-id');
                const albumId = $(this).val();
                AdminPanel.assignImageToAlbum(imageId, albumId);
            });
        },

        bindAlbumEvents: function() {
            // Create album button
            $(document).on('click', '#createAlbumBtn, #emptyCreateAlbumBtn', function() {
                AdminPanel.openAlbumModal();
            });
            
            // Edit album button
            $(document).on('click', '[data-action="edit-album"]', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const description = $(this).data('description');
                AdminPanel.openAlbumModal(id, name, description);
            });
            
            // Delete album button
            $(document).on('click', '[data-action="delete-album"]', function() {
                const id = $(this).data('id');
                AdminPanel.deleteAlbum(id);
            });
            
            // Close album modal
            $(document).on('click', '#albumModal', function(e) {
                if (e.target === this) {
                    AdminPanel.closeAlbumModal();
                }
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#albumModal').is(':visible')) {
                    AdminPanel.closeAlbumModal();
                }
            });
            
            // Save album button (create or edit)
            $(document).on('click', '#saveAlbumBtn', function() {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                const albumId = $('#albumId').val();
                if (albumId) {
                    AdminPanel.editAlbum(albumId);
                } else {
                    AdminPanel.createAlbum();
                }
            });
            
            // Enter key inside the album form = save (never a GET page reload)
            $(document).on('submit', '#albumForm', function(e) {
                e.preventDefault();
                $('#saveAlbumBtn').trigger('click');
            });
            
            // Cancel album button
            $(document).on('click', '#cancelAlbumBtn, #closeAlbumModalBtn', function() {
                AdminPanel.closeAlbumModal();
            });
        }
        });
    }

    extendAdminPanel();

    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined' && typeof AdminPanel.initGallery === 'function') {
            AdminPanel.initGallery();
        }
        $('#uploadBtn').on('click', function() {
            if (typeof AdminPanel.openUploadModal === 'function') {
                AdminPanel.openUploadModal();
            } else if (typeof window.openUploadModal === 'function') {
                window.openUploadModal();
            }
        });
        $('#closeUploadModalBtn, #cancelUploadBtn').on('click', function() {
            if (typeof AdminPanel.closeUploadModal === 'function') {
                AdminPanel.closeUploadModal();
            } else if (typeof window.closeUploadModal === 'function') {
                window.closeUploadModal();
            }
        });
        $('#emptyUploadBtn').on('click', function() {
            if (typeof AdminPanel.openUploadModal === 'function') {
                AdminPanel.openUploadModal();
            } else if (typeof window.openUploadModal === 'function') {
                window.openUploadModal();
            }
        });
        $('#startUpload').on('click', function(e) {
            e.preventDefault();
            if (typeof AdminPanel.uploadImages === 'function') {
                AdminPanel.uploadImages();
            } else if (typeof window.uploadImages === 'function') {
                window.uploadImages();
            }
        });
        $('#uploadForm').on('submit', function(e) {
            e.preventDefault();
        });
    });

})(jQuery);
