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
                const $firstInput = $modal.find('input[type="file"]');
                if ($firstInput.length) {
                    setTimeout(() => $firstInput.focus(), 100);
                }
            }
        },

        closeUploadModal: function() {
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
            }
        },

        uploadImages: function() {
            const $form = $('#uploadForm');
            const $images = $('#images');
            const $title = $('#imageTitle');
            const $albumSelect = $('#albumSelect');
            const $progress = $('#uploadProgress');
            const $preview = $('#uploadPreview');

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
                return allowedTypes.indexOf(file.type) === -1 || file.size > 5 * 1024 * 1024;
            });
            if (invalidFile) {
                AdminPanel.showNotification('Invalid image: ' + invalidFile.name + '. Use JPEG, PNG, GIF, WebP, or HEIC under 5MB.', 'error');
                return;
            }

            $progress.show();
            this.updateProgress(0);

            const formData = new FormData();
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            formData.append('csrf_token', csrfToken);
            formData.append('imageTitle', $title.val());
            formData.append('album_id', $albumSelect.val());

            Array.from($images[0].files).forEach((file) => {
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
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = evt.loaded / evt.total * 100;
                            AdminPanel.updateProgress(percentComplete);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    AdminPanel.updateProgress(100);
                    let responseData = response;
                    if (typeof response === 'string') {
                        try {
                            responseData = JSON.parse(response);
                        } catch (e) {
                            AdminPanel.showNotification('Upload failed: Invalid response format', 'error');
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
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.updateProgress(0);
                    let errorMessage = 'Upload failed';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            errorMessage = errorResponse.message || errorMessage;
                        } catch (e) {
                            errorMessage += ': ' + xhr.responseText;
                        }
                    } else if (error) {
                        errorMessage += ': ' + error;
                    }
                    AdminPanel.showNotification(errorMessage, 'error');
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
                                        'border': '2px solid var(--color-green-medium)'
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
                $dropzone.on('click', function() {
                    $('#images').click();
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
                const albumId = $('#albumId').val();
                if (albumId) {
                    AdminPanel.editAlbum(albumId);
                } else {
                    AdminPanel.createAlbum();
                }
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
