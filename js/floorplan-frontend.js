/**
 * CenterShop Floorplan Frontend JavaScript
 */

(function($) {
    'use strict';
    
    const FloorplanFrontend = {
        
        $wrapper: null,
        $search: null,
        $results: null,
        $areas: null,
        $info: null,
        searchTimeout: null,
        
        init: function() {
            this.$wrapper = $('.centershop-floorplan-wrapper');
            
            if (!this.$wrapper.length) {
                return;
            }
            
            this.$search = this.$wrapper.find('.floorplan-search-input');
            this.$results = this.$wrapper.find('.floorplan-search-results');
            this.$areas = this.$wrapper.find('.floorplan-area-front');
            this.$info = this.$wrapper.find('.floorplan-info');
            
            this.bindEvents();
            this.checkPreselected();
            this.initTooltips();
        },
        
        bindEvents: function() {
            // Search input
            this.$search.on('input', this.handleSearch.bind(this));
            this.$search.on('focus', this.handleSearchFocus.bind(this));
            
            // Click outside to close search results
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.floorplan-search').length) {
                    this.$results.removeClass('active');
                }
            });
            
            // Search result click
            $(document).on('click', '.search-result-item', this.handleResultClick.bind(this));
            
            // Area click
            this.$areas.on('click', this.handleAreaClick.bind(this));
            
            // Area hover for tooltip
            this.$areas.on('mouseenter', this.showTooltip.bind(this));
            this.$areas.on('mouseleave', this.hideTooltip.bind(this));
            this.$areas.on('mousemove', this.moveTooltip.bind(this));
        },
        
        checkPreselected: function() {
            const selectedShopId = this.$wrapper.data('selected-shop');
            
            if (selectedShopId) {
                this.highlightShop(selectedShopId);
            }
        },
        
        handleSearch: function(e) {
            clearTimeout(this.searchTimeout);
            
            const query = $(e.target).val().trim();
            
            if (query.length < 2) {
                this.$results.removeClass('active').empty();
                return;
            }
            
            this.searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 300);
        },
        
        handleSearchFocus: function() {
            if (this.$results.children().length > 0) {
                this.$results.addClass('active');
            }
        },
        
        performSearch: function(query) {
            $.ajax({
                url: centershopFloorplanFrontend.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'search_shops',
                    s: query
                },
                success: (response) => {
                    if (response.success && response.data.length > 0) {
                        this.renderSearchResults(response.data);
                    } else {
                        this.showNoResults();
                    }
                },
                error: () => {
                    this.showNoResults();
                }
            });
        },
        
        renderSearchResults: function(shops) {
            this.$results.empty();
            
            shops.forEach(shop => {
                const $item = $(`
                    <div class="search-result-item" data-shop-id="${shop.id}">
                        ${shop.title}
                    </div>
                `);
                this.$results.append($item);
            });
            
            this.$results.addClass('active');
        },
        
        showNoResults: function() {
            this.$results.html(`
                <div class="no-results">
                    ${centershopFloorplanFrontend.strings.noResults}
                </div>
            `).addClass('active');
        },
        
        handleResultClick: function(e) {
            const shopId = $(e.currentTarget).data('shop-id');
            const shopName = $(e.currentTarget).text();
            
            this.highlightShop(shopId, shopName);
            this.$results.removeClass('active');
            this.$search.val('').blur();
        },
        
        handleAreaClick: function(e) {
            const shopId = $(e.currentTarget).data('shop-id');
            const shopName = $(e.currentTarget).data('shop-name');
            
            if (shopId) {
                this.highlightShop(shopId, shopName);
            }
        },
        
        highlightShop: function(shopId, shopName) {
            // Remove previous selection
            this.$areas.removeClass('selected');
            
            // Find and highlight the area
            const $area = this.$areas.filter(`[data-shop-id="${shopId}"]`);
            
            if ($area.length) {
                $area.addClass('selected');
                
                // Scroll to area
                const areaPos = $area[0].getBoundingClientRect();
                const containerPos = $area.closest('.floorplan-display')[0].getBoundingClientRect();
                
                // Show info
                if (!shopName) {
                    shopName = $area.data('shop-name');
                }
                
                this.showShopInfo(shopId, shopName);
                
                // Scroll area into view
                this.scrollToArea($area);
            }
        },
        
        showShopInfo: function(shopId, shopName) {
            if (!shopName) return;
            
            const shopUrl = this.getShopUrl(shopId);
            
            this.$info.find('.shop-name').text(shopName);
            this.$info.find('.shop-link').attr('href', shopUrl);
            this.$info.slideDown();
        },
        
        getShopUrl: function(shopId) {
            // This could be enhanced to get the actual URL via AJAX if needed
            return `/?p=${shopId}`;
        },
        
        scrollToArea: function($area) {
            const $display = this.$wrapper.find('.floorplan-display');
            
            if (!$display.length || !$area.length) return;
            
            // Get the bounding boxes
            const areaBBox = $area[0].getBBox();
            const displayRect = $display[0].getBoundingClientRect();
            const svgRect = $display.find('.floorplan-svg')[0].getBoundingClientRect();
            
            // Calculate position
            const scaleX = svgRect.width / 100; // Since we use percentage
            const scaleY = svgRect.height / 100;
            
            // Get center of area (approximate from points)
            const points = $area.attr('points').split(' ');
            let centerX = 0, centerY = 0;
            
            points.forEach(point => {
                const [x, y] = point.replace('%', '').split(',');
                centerX += parseFloat(x);
                centerY += parseFloat(y);
            });
            
            centerX = (centerX / points.length) * scaleX;
            centerY = (centerY / points.length) * scaleY;
            
            // Scroll to center
            $display.animate({
                scrollLeft: centerX - displayRect.width / 2,
                scrollTop: centerY - displayRect.height / 2
            }, 500);
        },
        
        initTooltips: function() {
            // Create tooltip element
            this.$tooltip = $('<div class="floorplan-tooltip"></div>');
            this.$wrapper.append(this.$tooltip);
        },
        
        showTooltip: function(e) {
            const shopName = $(e.currentTarget).data('shop-name');
            
            if (shopName) {
                this.$tooltip.text(shopName).addClass('active');
                this.moveTooltip(e);
            }
        },
        
        hideTooltip: function() {
            this.$tooltip.removeClass('active');
        },
        
        moveTooltip: function(e) {
            if (!this.$tooltip.hasClass('active')) return;
            
            const offset = this.$wrapper.offset();
            const x = e.pageX - offset.left + 15;
            const y = e.pageY - offset.top + 15;
            
            this.$tooltip.css({
                left: x + 'px',
                top: y + 'px'
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(() => {
        FloorplanFrontend.init();
    });
    
    // Re-initialize after AJAX loads (for dynamic content)
    $(document).on('floorplan:refresh', () => {
        FloorplanFrontend.init();
    });
    
})(jQuery);
