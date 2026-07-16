(function($) {
    'use strict';

    function extendAdminPanel() {
        if (typeof AdminPanel === 'undefined') {
            setTimeout(extendAdminPanel, 100);
            return;
        }

        $.extend(AdminPanel, {

        initTravel: function() {
            this.initLocationModal();
            this.initPhotosModal();
            this.bindTravelEvents();
        },

        // ==================== LOCATION METHODS ====================

        initLocationModal: function() {
            window.openLocationModal = this.openLocationModal.bind(this);
            window.closeLocationModal = this.closeLocationModal.bind(this);
            window.saveLocation = this.saveLocation.bind(this);
            window.deleteLocation = this.deleteLocation.bind(this);
        },

        openLocationModal: function(locationId, country, city, lat, lng, visited) {
            const $modal = $('#locationModal');
            if ($modal.length) {
                $modal.css('display', 'flex');
                $('body').css('overflow', 'hidden');

                const $title = $('#locationModalTitle');
                const $id = $('#locationId');
                const $country = $('#locationCountry');
                const $city = $('#locationCity');
                const $lat = $('#locationLat');
                const $lng = $('#locationLng');
                const $visited = $('#locationVisited');

                if (locationId) {
                    $title.text('Edit Location');
                    $id.val(locationId);
                    $country.val(country || '');
                    $city.val(city || '');
                    $lat.val(lat || '');
                    $lng.val(lng || '');
                    $visited.val(visited || '');
                } else {
                    $title.text('Add Location');
                    $id.val('');
                    $country.val('');
                    $city.val('');
                    $lat.val('');
                    $lng.val('');
                    $visited.val('');
                }

                setTimeout(() => $country.focus(), 100);
            }
        },

        closeLocationModal: function() {
            const $modal = $('#locationModal');
            if ($modal.length) {
                $modal.css('display', 'none');
                $('body').css('overflow', '');
                $('#locationForm')[0].reset();
            }
        },

        saveLocation: function() {
            const $id = $('#locationId');
            const country = $('#locationCountry').val().trim();
            const city = $('#locationCity').val().trim();
            const lat = $('#locationLat').val().trim();
            const lng = $('#locationLng').val().trim();
            const visited = $('#locationVisited').val();

            if (!country || !city || !lat || !lng) {
                AdminPanel.showNotification('Country, city, latitude, and longitude are required', 'error');
                $('#saveLocationBtn').prop('disabled', false);
                return;
            }

            const latNum = parseFloat(lat);
            const lngNum = parseFloat(lng);
            if (isNaN(latNum) || isNaN(lngNum) || Math.abs(latNum) > 90 || Math.abs(lngNum) > 180) {
                AdminPanel.showNotification('Latitude must be -90..90 and longitude -180..180', 'error');
                $('#saveLocationBtn').prop('disabled', false);
                return;
            }

            const action = $id.val() ? 'update' : 'create';
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', action);
            formData.append('country', country);
            formData.append('city', city);
            formData.append('lat', lat);
            formData.append('lng', lng);
            formData.append('visited', visited || '');
            if ($id.val()) {
                formData.append('id', $id.val());
            }

            $.ajax({
                url: window.FULL_BASE_PATH + 'api/admin/travel/locations.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                complete: function() {
                    $('#saveLocationBtn').prop('disabled', false);
                },
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Location saved successfully', 'success');
                        AdminPanel.closeLocationModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        AdminPanel.showNotification('Failed to save: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to save: ' + error, 'error');
                }
            });
        },

        deleteLocation: function(locationId) {
            if (!locationId) return;
            if (!confirm('Delete this location? All associated photos will also be removed from the database (files remain on disk).')) {
                return;
            }

            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'delete');
            formData.append('id', locationId);

            $.ajax({
                url: window.FULL_BASE_PATH + 'api/admin/travel/locations.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Location deleted successfully', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        AdminPanel.showNotification('Failed to delete: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to delete: ' + error, 'error');
                }
            });
        },

        // ==================== PHOTO METHODS ====================

        initPhotosModal: function() {
            window.openPhotosModal = this.openPhotosModal.bind(this);
            window.closePhotosModal = this.closePhotosModal.bind(this);
            window.loadLocationPhotos = this.loadLocationPhotos.bind(this);
            window.uploadTravelPhotos = this.uploadTravelPhotos.bind(this);
            window.deleteTravelPhoto = this.deleteTravelPhoto.bind(this);
        },

        openPhotosModal: function(locationId, country, city) {
            const $modal = $('#photosModal');
            if ($modal.length) {
                $modal.css('display', 'flex');
                $('body').css('overflow', 'hidden');

                $('#photoLocationId').val(locationId);
                $('#photosModalTitle').text('Manage Photos — ' + city + ', ' + country);
                $('#photoGrid').empty();
                $('#uploadPhotosBtn').prop('disabled', true);
                $('#photoUploadProgress').hide();

                this.loadLocationPhotos(locationId);
            }
        },

        closePhotosModal: function() {
            const $modal = $('#photosModal');
            if ($modal.length) {
                $modal.css('display', 'none');
                $('body').css('overflow', '');
                $('#photoFiles')[0].files = new DataTransfer().files;
            }
        },

        loadLocationPhotos: function(locationId) {
            const $grid = $('#photoGrid');
            const imageBase = window.FULL_BASE_PATH + 'uploads/travel/';
            // Photos migrated from the legacy JSON live in the static dir —
            // fall back to it when the file is not in uploads/.
            const staticBase = window.FULL_BASE_PATH + 'assets/images/travel/';

            // For now, we load from the static JSON as a fallback since we don't have a photos API endpoint
            // In a real implementation, we'd fetch from an API
            $.ajax({
                url: window.FULL_BASE_PATH + 'api/admin/travel/photos.php',
                method: 'GET',
                data: { location_id: locationId },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    $grid.empty();
                    if (response.success && response.photos.length > 0) {
                        response.photos.forEach(function(photo) {
                            const $item = $('<div>').addClass('admin-gallery-item').attr('data-photo-id', photo.id);
                            $item.html(
                                '<div class="admin-card">' +
                                '<div class="admin-gallery-image-container">' +
                                '<img class="admin-gallery-image" src="' + imageBase + photo.filename + '" alt="' + photo.title + '" loading="lazy" onerror="this.onerror=null;this.src=\'' + staticBase + photo.filename + '\';">' +
                                '</div>' +
                                '<div class="admin-card-body">' +
                                '<button class="admin-btn admin-btn-danger admin-btn-sm" data-action="delete-photo" data-id="' + photo.id + '"><i class="bi bi-trash"></i> Delete</button>' +
                                '</div>' +
                                '</div>'
                            );
                            $grid.append($item);
                        });
                    } else {
                        $grid.html('<div class="admin-empty-photos">No photos for this location yet.</div>');
                    }
                },
                error: function() {
                    $grid.html('<div class="admin-empty-photos">No photos for this location yet.</div>');
                }
            });
        },

        uploadTravelPhotos: function() {
            const locationId = $('#photoLocationId').val();
            const $files = $('#photoFiles');

            if (!locationId || !$files[0].files.length) {
                AdminPanel.showNotification('Please select at least one photo', 'error');
                return;
            }
            if ($files[0].files.length > 12) {
                AdminPanel.showNotification('Please upload 12 images or fewer at once', 'error');
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
            const invalidFile = Array.from($files[0].files).find((file) => {
                return allowedTypes.indexOf(file.type) === -1 || file.size > 5 * 1024 * 1024;
            });
            if (invalidFile) {
                AdminPanel.showNotification('Invalid image: ' + invalidFile.name + '. Use JPEG, PNG, GIF, WebP, or HEIC under 5MB.', 'error');
                return;
            }

            const $progress = $('#photoUploadProgress');
            $progress.show();
            this.updateTravelProgress(0);
            $('#uploadPhotosBtn').prop('disabled', true);

            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('location_id', locationId);

            Array.from($files[0].files).forEach((file) => {
                formData.append('images[]', file);
            });

            $.ajax({
                url: window.FULL_BASE_PATH + 'api/admin/travel/upload.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            AdminPanel.updateTravelProgress(evt.loaded / evt.total * 100);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    AdminPanel.updateTravelProgress(100);
                    if (response.success) {
                        AdminPanel.showNotification('Photos uploaded successfully', 'success');
                        $('#photoFiles')[0].files = new DataTransfer().files;
                        $('#uploadPhotosBtn').prop('disabled', true);
                        AdminPanel.loadLocationPhotos(locationId);
                    } else {
                        AdminPanel.showNotification('Upload failed: ' + (response.message || 'Unknown error'), 'error');
                        $('#uploadPhotosBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.updateTravelProgress(0);
                    AdminPanel.showNotification('Upload failed: ' + error, 'error');
                    $('#uploadPhotosBtn').prop('disabled', false);
                }
            });
        },

        deleteTravelPhoto: function(imageId) {
            if (!imageId) return;
            if (!confirm('Delete this photo? This cannot be undone.')) return;

            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="csrf_token"]').val();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('id', imageId);

            $.ajax({
                url: window.FULL_BASE_PATH + 'api/admin/travel/delete-image.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.success) {
                        AdminPanel.showNotification('Photo deleted', 'success');
                        const locationId = $('#photoLocationId').val();
                        AdminPanel.loadLocationPhotos(locationId);
                    } else {
                        AdminPanel.showNotification('Failed to delete: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AdminPanel.showNotification('Failed to delete: ' + error, 'error');
                }
            });
        },

        updateTravelProgress: function(percent) {
            const $progressBar = $('#photoUploadProgress .admin-upload-progress-bar');
            if ($progressBar.length) {
                $progressBar.css('width', percent + '%').attr('aria-valuenow', percent);
                $progressBar.text(Math.round(percent) + '%');
            }
        },

        bindTravelEvents: function() {
            // Location modal events
            $(document).on('click', '#locationModal', function(e) {
                if (e.target === this) AdminPanel.closeLocationModal();
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#locationModal').is(':visible')) AdminPanel.closeLocationModal();
            });

            // Photos modal events
            $(document).on('click', '#photosModal', function(e) {
                if (e.target === this) AdminPanel.closePhotosModal();
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#photosModal').is(':visible')) AdminPanel.closePhotosModal();
            });

            // Add location button
            $(document).on('click', '#addLocationBtn, #emptyAddLocationBtn', function() {
                AdminPanel.openLocationModal();
            });

            // Edit location button
            $(document).on('click', '[data-action="edit-location"]', function() {
                const id = $(this).data('id');
                const country = $(this).data('country');
                const city = $(this).data('city');
                const lat = $(this).data('lat');
                const lng = $(this).data('lng');
                const visited = $(this).data('visited');
                AdminPanel.openLocationModal(id, country, city, lat, lng, visited);
            });

            // Delete location button
            $(document).on('click', '[data-action="delete-location"]', function() {
                AdminPanel.deleteLocation($(this).data('id'));
            });

            // Manage photos button
            $(document).on('click', '[data-action="manage-photos"]', function() {
                const id = $(this).data('id');
                const country = $(this).data('country');
                const city = $(this).data('city');
                AdminPanel.openPhotosModal(id, country, city);
            });

            // Save location button
            $(document).on('click', '#saveLocationBtn', function() {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                AdminPanel.saveLocation();
            });

            // Enter key inside the location form = save (never a GET page reload)
            $(document).on('submit', '#locationForm', function(e) {
                e.preventDefault();
                $('#saveLocationBtn').trigger('click');
            });

            // Close location modal
            $(document).on('click', '#closeLocationModalBtn, #cancelLocationBtn', function() {
                AdminPanel.closeLocationModal();
            });

            // Close photos modal
            $(document).on('click', '#closePhotosModalBtn, #closePhotosBtn', function() {
                AdminPanel.closePhotosModal();
            });

            // Photo file preview
            $(document).on('change', '#photoFiles', function() {
                const files = this.files;
                if (files.length > 12) {
                    AdminPanel.showNotification('Please upload 12 images or fewer at once', 'error');
                    this.value = '';
                    $('#uploadPhotosBtn').prop('disabled', true);
                    return;
                }
                $('#uploadPhotosBtn').prop('disabled', files.length === 0);
            });

            // Upload photos button
            $(document).on('click', '#uploadPhotosBtn', function() {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                AdminPanel.uploadTravelPhotos();
            });

            // Delete photo button
            $(document).on('click', '[data-action="delete-photo"]', function() {
                AdminPanel.deleteTravelPhoto($(this).data('id'));
            });

            // Drag & drop for photos
            const $dropzone = $('#photoDropzone');
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
                    const $fileInput = $('#photoFiles');
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
                    $('#photoFiles').trigger('click');
                });
                // Touch handlers for mobile (iPhone/iPad)
                $dropzone.on('touchstart', function(e) {
                    $(this).addClass('dragover');
                });
                $dropzone.on('touchend', function(e) {
                    e.preventDefault();
                    $(this).removeClass('dragover');
                    $('#photoFiles').click();
                });
            }
        }
        });
    }

    extendAdminPanel();

    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined' && typeof AdminPanel.initTravel === 'function') {
            AdminPanel.initTravel();
        }
    });

})(jQuery);
