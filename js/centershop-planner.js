/**
 * CenterShop Planner JavaScript
 */
(function($) {
    'use strict';
    
    var CenterShopPlanner = {
        currentDate: new Date(),
        currentMonth: null,
        currentYear: null,
        posts: [],
        filters: {
            shop: '',
            status: '',
            type: ''
        },
        
        init: function() {
            this.currentMonth = this.currentDate.getMonth();
            this.currentYear = this.currentDate.getFullYear();
            
            this.bindEvents();
            this.populateShopFilter();
            this.loadMonth();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Navigation
            $('#centershop-prev-month').on('click', function() {
                self.previousMonth();
            });
            
            $('#centershop-next-month').on('click', function() {
                self.nextMonth();
            });
            
            // Filters
            $('#centershop-filter-shop').on('change', function() {
                self.filters.shop = $(this).val();
                self.renderCalendar();
            });
            
            $('#centershop-filter-status').on('change', function() {
                self.filters.status = $(this).val();
                self.renderCalendar();
            });
            
            $('#centershop-filter-type').on('change', function() {
                self.filters.type = $(this).val();
                self.renderCalendar();
            });
            
            // Post card click
            $(document).on('click', '.centershop-post-card', function() {
                var postId = $(this).data('post-id');
                self.openPostModal(postId);
            });
            
            // Add post button
            $(document).on('click', '.centershop-add-post-day', function(e) {
                e.stopPropagation();
                var date = $(this).data('date');
                self.createNewPost(date);
            });
            
            // Modal close
            $('.centershop-modal-close').on('click', function() {
                self.closeModal();
            });
            
            $(document).on('click', '.centershop-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // Shop upload handlers
            this.initUploadHandlers();
        },
        
        populateShopFilter: function() {
            var $select = $('#centershop-filter-shop');
            if (!$select.length) return;
            
            $.each(centershopPlanner.shops, function(i, shop) {
                $select.append('<option value="' + shop.id + '">' + shop.name + '</option>');
            });
        },
        
        loadMonth: function() {
            var self = this;
            var startDate = new Date(this.currentYear, this.currentMonth, 1);
            var endDate = new Date(this.currentYear, this.currentMonth + 1, 0);
            
            // Format dates
            var start = this.formatDate(startDate);
            var end = this.formatDate(endDate);
            
            // Update header
            $('#centershop-current-month').text(
                centershopPlanner.strings.months[this.currentMonth] + ' ' + this.currentYear
            );
            
            // Fetch posts
            wp.apiFetch({
                path: centershopPlanner.apiUrl + '/posts?start_date=' + start + '&end_date=' + end,
                headers: {
                    'X-WP-Nonce': centershopPlanner.nonce
                }
            }).then(function(posts) {
                self.posts = posts;
                self.renderCalendar();
            }).catch(function(error) {
                console.error('Error loading posts:', error);
            });
        },
        
        renderCalendar: function() {
            var $grid = $('#centershop-calendar-grid');
            $grid.empty();
            
            var firstDay = new Date(this.currentYear, this.currentMonth, 1);
            var lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
            
            // Get first day of week (Monday = 0)
            var startDayOfWeek = firstDay.getDay();
            startDayOfWeek = startDayOfWeek === 0 ? 6 : startDayOfWeek - 1;
            
            // Days from previous month
            var prevMonth = new Date(this.currentYear, this.currentMonth, 0);
            var prevMonthDays = prevMonth.getDate();
            
            // Total cells needed (6 rows)
            var totalCells = 42;
            
            for (var i = 0; i < totalCells; i++) {
                var dayNumber;
                var dateObj;
                var isOtherMonth = false;
                var isToday = false;
                var isWeekend = false;
                
                if (i < startDayOfWeek) {
                    // Previous month
                    dayNumber = prevMonthDays - startDayOfWeek + i + 1;
                    dateObj = new Date(this.currentYear, this.currentMonth - 1, dayNumber);
                    isOtherMonth = true;
                } else if (i >= startDayOfWeek + lastDay.getDate()) {
                    // Next month
                    dayNumber = i - startDayOfWeek - lastDay.getDate() + 1;
                    dateObj = new Date(this.currentYear, this.currentMonth + 1, dayNumber);
                    isOtherMonth = true;
                } else {
                    // Current month
                    dayNumber = i - startDayOfWeek + 1;
                    dateObj = new Date(this.currentYear, this.currentMonth, dayNumber);
                }
                
                // Check if today
                var today = new Date();
                if (dateObj.toDateString() === today.toDateString()) {
                    isToday = true;
                }
                
                // Check if weekend
                var dayOfWeek = dateObj.getDay();
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    isWeekend = true;
                }
                
                var dateStr = this.formatDate(dateObj);
                var dayPosts = this.getPostsForDate(dateStr);
                
                var classes = ['centershop-calendar-day'];
                if (isOtherMonth) classes.push('other-month');
                if (isToday) classes.push('today');
                if (isWeekend) classes.push('weekend');
                
                var $day = $('<div class="' + classes.join(' ') + '">');
                $day.append('<span class="centershop-day-number">' + dayNumber + '</span>');
                
                // Add post button (only for admins)
                if (!centershopPlanner.isShopManager) {
                    $day.append('<button type="button" class="centershop-add-post-day" data-date="' + dateStr + '">+</button>');
                }
                
                // Add posts
                if (dayPosts.length > 0) {
                    var $posts = $('<div class="centershop-day-posts">');
                    $.each(dayPosts, function(j, post) {
                        var $card = $('<div class="centershop-post-card status-' + post.status + '" data-post-id="' + post.id + '">');
                        $card.append('<div class="centershop-post-title">' + (post.title || post.shop_name || centershopPlanner.strings.no_shop) + '</div>');
                        $card.append('<div class="centershop-post-meta"><span class="centershop-post-type-badge">' + (centershopPlanner.strings[post.post_type] || post.post_type) + '</span></div>');
                        $posts.append($card);
                    });
                    $day.append($posts);
                }
                
                $grid.append($day);
            }
        },
        
        getPostsForDate: function(dateStr) {
            var self = this;
            return this.posts.filter(function(post) {
                if (post.scheduled_date !== dateStr) return false;
                
                // Apply filters
                if (self.filters.shop && post.shop_id != self.filters.shop) return false;
                if (self.filters.status && post.status !== self.filters.status) return false;
                if (self.filters.type && post.post_type !== self.filters.type) return false;
                
                return true;
            });
        },
        
        previousMonth: function() {
            this.currentMonth--;
            if (this.currentMonth < 0) {
                this.currentMonth = 11;
                this.currentYear--;
            }
            this.loadMonth();
        },
        
        nextMonth: function() {
            this.currentMonth++;
            if (this.currentMonth > 11) {
                this.currentMonth = 0;
                this.currentYear++;
            }
            this.loadMonth();
        },
        
        formatDate: function(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        },
        
        openPostModal: function(postId) {
            var post = this.posts.find(function(p) { return p.id === postId; });
            if (!post) return;
            
            var $modal = $('#centershop-post-modal');
            var $body = $modal.find('.centershop-modal-body');
            
            var html = '<h2>' + (post.title || centershopPlanner.strings.no_shop) + '</h2>';
            html += '<p><strong>Status:</strong> ' + (centershopPlanner.strings[post.status] || post.status) + '</p>';
            html += '<p><strong>Type:</strong> ' + (centershopPlanner.strings[post.post_type] || post.post_type) + '</p>';
            if (post.shop_name) {
                html += '<p><strong>Butik:</strong> ' + post.shop_name + '</p>';
            }
            html += '<p><strong>Dato:</strong> ' + post.scheduled_date + '</p>';
            
            if (post.caption) {
                html += '<hr><p>' + post.caption + '</p>';
            }
            
            // Media
            if (post.media && post.media.length > 0) {
                html += '<div class="centershop-modal-media">';
                $.each(post.media, function(i, media) {
                    if (media.type === 'video') {
                        html += '<video src="' + media.url + '" controls style="max-width:100%;max-height:200px;"></video>';
                    } else {
                        html += '<img src="' + (media.thumbnail || media.url) + '" style="max-width:100%;max-height:200px;">';
                    }
                });
                html += '</div>';
            }
            
            html += '<hr><p><a href="' + centershopPlanner.adminUrl + 'post.php?post=' + post.id + '&action=edit" class="button button-primary">Rediger</a></p>';
            
            $body.html(html);
            $modal.show();
        },
        
        closeModal: function() {
            $('#centershop-post-modal').hide();
        },
        
        createNewPost: function(date) {
            window.location.href = centershopPlanner.adminUrl + 'post-new.php?post_type=centershop_post&scheduled_date=' + date;
        },
        
        // Shop upload handlers
        initUploadHandlers: function() {
            var self = this;
            var $dropzone = $('#centershop-upload-dropzone');
            var $fileInput = $('#centershop-file-input');
            var $preview = $('#centershop-upload-preview');
            var $submitBtn = $('#centershop-submit-upload');
            var selectedFiles = [];
            
            if (!$dropzone.length) return;
            
            // Click to select
            $dropzone.on('click', function() {
                $fileInput.trigger('click');
            });
            
            // File input change
            $fileInput.on('change', function() {
                self.handleFiles(this.files, $preview, $submitBtn, selectedFiles);
            });
            
            // Drag and drop
            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                $(this).addClass('dragging');
            });
            
            $dropzone.on('dragleave drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragging');
            });
            
            $dropzone.on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                self.handleFiles(files, $preview, $submitBtn, selectedFiles);
            });
            
            // Remove preview item
            $(document).on('click', '.centershop-upload-preview-item .remove', function() {
                var index = $(this).closest('.centershop-upload-preview-item').index();
                selectedFiles.splice(index, 1);
                $(this).closest('.centershop-upload-preview-item').remove();
                $submitBtn.prop('disabled', selectedFiles.length === 0);
            });
            
            // Submit upload
            $submitBtn.on('click', function() {
                self.submitUpload(selectedFiles, $preview, $submitBtn);
            });
        },
        
        handleFiles: function(files, $preview, $submitBtn, selectedFiles) {
            $.each(files, function(i, file) {
                if (!file.type.match('image.*') && !file.type.match('video.*')) {
                    return;
                }
                
                selectedFiles.push(file);
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    var $item = $('<div class="centershop-upload-preview-item">');
                    if (file.type.match('video.*')) {
                        $item.append('<video src="' + e.target.result + '"></video>');
                    } else {
                        $item.append('<img src="' + e.target.result + '">');
                    }
                    $item.append('<button type="button" class="remove">&times;</button>');
                    $preview.append($item);
                };
                reader.readAsDataURL(file);
            });
            
            $submitBtn.prop('disabled', selectedFiles.length === 0);
        },
        
        submitUpload: function(files, $preview, $submitBtn) {
            if (files.length === 0) return;
            
            var self = this;
            var description = $('#centershop-upload-desc').val();
            
            $submitBtn.prop('disabled', true).text('Uploader...');
            
            // Upload files first
            var uploadPromises = files.map(function(file) {
                var formData = new FormData();
                formData.append('file', file);
                
                return $.ajax({
                    url: centershopPlanner.adminUrl + 'async-upload.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-WP-Nonce': centershopPlanner.nonce
                    }
                });
            });
            
            // When all uploads complete, create post
            $.when.apply($, uploadPromises).then(function() {
                var responses = arguments;
                var mediaIds = [];
                
                if (uploadPromises.length === 1) {
                    responses = [responses];
                }
                
                $.each(responses, function(i, response) {
                    if (response[0] && response[0].id) {
                        mediaIds.push(response[0].id);
                    }
                });
                
                // Create planner post
                wp.apiFetch({
                    path: centershopPlanner.apiUrl + '/posts',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': centershopPlanner.nonce
                    },
                    data: {
                        title: 'Upload fra butik',
                        caption: description,
                        shop_id: centershopPlanner.userShopId,
                        status: 'awaiting_content',
                        media_ids: mediaIds
                    }
                }).then(function() {
                    // Success - reload page
                    location.reload();
                }).catch(function(error) {
                    console.error('Error creating post:', error);
                    alert('Der opstod en fejl. Prøv igen.');
                    $submitBtn.prop('disabled', false).text('Send til centret');
                });
                
            }).fail(function() {
                alert('Der opstod en fejl ved upload. Prøv igen.');
                $submitBtn.prop('disabled', false).text('Send til centret');
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        if ($('.centershop-planner-wrap').length) {
            CenterShopPlanner.init();
        }
    });
    
})(jQuery);
