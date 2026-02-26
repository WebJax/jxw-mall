/**
 * CenterShop Floorplan Admin JavaScript
 */

(function($) {
    'use strict';
    
    let areas = [];
    let currentArea = null;
    let drawingPoints = [];
    let isDrawing = false;
    let svgUrl = '';
    
    const FloorplanAdmin = {
        
        init: function() {
            this.bindEvents();
            this.loadData();
        },
        
        bindEvents: function() {
            // SVG upload
            $('#upload-svg-btn').on('click', this.uploadSvg.bind(this));
            
            // Add area
            $('#add-area-btn').on('click', this.startDrawing.bind(this));
            
            // Save floorplan
            $('#save-floorplan').on('click', this.saveFloorplan.bind(this));
            
            // Area list click
            $(document).on('click', '.area-item', this.selectArea.bind(this));
            
            // Delete area
            $(document).on('click', '.delete-area', this.deleteArea.bind(this));
            
            // Update area
            $(document).on('click', '.update-area', this.updateArea.bind(this));
            
            // Area form changes
            $(document).on('input', '.area-label, .area-color', this.onAreaChange.bind(this));
            $(document).on('change', '.area-shop', this.onAreaChange.bind(this));
            
            // Canvas clicks for drawing
            $(document).on('click', '#floorplan-canvas', this.handleCanvasClick.bind(this));
            
            // Polygon clicks
            $(document).on('click', '.floorplan-area', this.handlePolygonClick.bind(this));
        },
        
        uploadSvg: function(e) {
            e.preventDefault();
            
            const mediaUploader = wp.media({
                title: 'Vælg grundplan billede',
                button: {
                    text: 'Brug dette billede'
                },
                library: {
                    type: 'image'
                },
                multiple: false
            });
            
            mediaUploader.on('select', () => {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                
                $.ajax({
                    url: centershopFloorplan.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'save_floorplan_svg',
                        nonce: centershopFloorplan.nonce,
                        attachment_id: attachment.id
                    },
                    success: (response) => {
                        if (response.success) {
                            svgUrl = response.data.url;
                            this.renderSvg();
                            this.showMessage(centershopFloorplan.strings.saved);
                        } else {
                            this.showMessage(centershopFloorplan.strings.error, 'error');
                        }
                    }
                });
            });
            
            mediaUploader.open();
        },
        
        renderSvg: function() {
            const $wrapper = $('.floorplan-canvas-wrapper');
            $wrapper.html(`
                <div id="floorplan-canvas" data-mode="edit">
                    <img src="${svgUrl}" alt="Grundplan" id="floorplan-svg">
                    <svg id="floorplan-overlay"></svg>
                </div>
            `);
            
            this.renderAreas();
        },
        
        renderAreas: function() {
            const $overlay = $('#floorplan-overlay');
            $overlay.empty();
            
            areas.forEach((area, index) => {
                const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                polygon.setAttribute('class', 'floorplan-area');
                polygon.setAttribute('data-index', index);
                polygon.setAttribute('points', area.points);
                polygon.setAttribute('fill', area.color || 'rgba(52, 152, 219, 0.3)');
                polygon.setAttribute('stroke', '#3498db');
                polygon.setAttribute('stroke-width', '2');
                
                if (area.shop_id) {
                    polygon.setAttribute('data-shop-id', area.shop_id);
                }
                
                $overlay.append(polygon);
            });
            
            this.updateAreasList();
        },
        
        updateAreasList: function() {
            const $list = $('#areas-list');
            $list.empty();
            
            if (areas.length === 0) {
                $list.html('<p class="no-areas">Ingen områder defineret endnu. Klik på "Tilføj område" for at starte.</p>');
                return;
            }
            
            areas.forEach((area, index) => {
                let shopName = '';
                if (area.shop_id) {
                    const $option = $(`option[value="${area.shop_id}"]`).first();
                    shopName = $option.length ? ` <span class="shop-name">(${$option.text()})</span>` : '';
                }
                
                $list.append(`
                    <div class="area-item" data-index="${index}">
                        <div>
                            <strong>${area.label || 'Område ' + (index + 1)}</strong>
                            ${shopName}
                        </div>
                        <button type="button" class="delete-area" data-index="${index}">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                `);
            });
        },
        
        startDrawing: function(e) {
            e.preventDefault();
            
            if (!svgUrl) {
                alert('Upload først et grundplan billede');
                return;
            }
            
            isDrawing = true;
            drawingPoints = [];
            $('#floorplan-canvas').addClass('drawing');
            
            this.showMessage('Klik for at placere punkter. Dobbeltklik for at afslutte.', 'info');
        },
        
        handleCanvasClick: function(e) {
            if (!isDrawing) return;
            if (!$(e.target).is('#floorplan-svg')) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            // Get relative coordinates
            const $svg = $('#floorplan-svg');
            const offset = $svg.offset();
            const x = ((e.pageX - offset.left) / $svg.width() * 100).toFixed(2);
            const y = ((e.pageY - offset.top) / $svg.height() * 100).toFixed(2);
            
            drawingPoints.push({ x, y });
            
            this.renderDrawing();
            
            // Double click detection
            if (e.originalEvent.detail === 2 && drawingPoints.length >= 3) {
                this.finishDrawing();
            }
        },
        
        renderDrawing: function() {
            const $overlay = $('#floorplan-overlay');
            
            // Remove existing drawing elements
            $overlay.find('.drawing-point, .drawing-line, .temp-polygon').remove();
            
            // Draw points
            drawingPoints.forEach(point => {
                const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('class', 'drawing-point');
                circle.setAttribute('cx', point.x + '%');
                circle.setAttribute('cy', point.y + '%');
                circle.setAttribute('r', '5');
                $overlay.append(circle);
            });
            
            // Draw lines
            if (drawingPoints.length > 1) {
                for (let i = 0; i < drawingPoints.length - 1; i++) {
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('class', 'drawing-line');
                    line.setAttribute('x1', drawingPoints[i].x + '%');
                    line.setAttribute('y1', drawingPoints[i].y + '%');
                    line.setAttribute('x2', drawingPoints[i + 1].x + '%');
                    line.setAttribute('y2', drawingPoints[i + 1].y + '%');
                    $overlay.append(line);
                }
            }
            
            // Draw temp polygon
            if (drawingPoints.length >= 3) {
                const points = drawingPoints.map(p => `${p.x}%,${p.y}%`).join(' ');
                const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                polygon.setAttribute('class', 'temp-polygon');
                polygon.setAttribute('points', points);
                $overlay.prepend(polygon);
            }
        },
        
        finishDrawing: function() {
            if (drawingPoints.length < 3) {
                alert('Du skal mindst have 3 punkter for at lave et område');
                return;
            }
            
            const points = drawingPoints.map(p => `${p.x}%,${p.y}%`).join(' ');
            
            const newArea = {
                label: 'Område ' + (areas.length + 1),
                points: points,
                shop_id: '',
                color: 'rgba(52, 152, 219, 0.3)'
            };
            
            areas.push(newArea);
            
            isDrawing = false;
            drawingPoints = [];
            $('#floorplan-canvas').removeClass('drawing');
            
            this.renderAreas();
            this.selectAreaByIndex(areas.length - 1);
        },
        
        handlePolygonClick: function(e) {
            if (isDrawing) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            const index = parseInt($(e.currentTarget).data('index'));
            this.selectAreaByIndex(index);
        },
        
        selectAreaByIndex: function(index) {
            currentArea = index;
            
            // Update UI
            $('.area-item').removeClass('active');
            $(`.area-item[data-index="${index}"]`).addClass('active');
            
            $('.floorplan-area').removeClass('active');
            $(`.floorplan-area[data-index="${index}"]`).addClass('active');
            
            // Show editor
            this.showAreaEditor(areas[index]);
        },
        
        selectArea: function(e) {
            const index = parseInt($(e.currentTarget).data('index'));
            this.selectAreaByIndex(index);
        },
        
        showAreaEditor: function(area) {
            const template = $('#area-editor-template').html();
            let html = template
                .replace(/\{\{label\}\}/g, area.label || '')
                .replace(/\{\{color\}\}/g, area.color || '#3498db');
            
            // Replace selected option
            if (area.shop_id) {
                html = html.replace(`{{selected_${area.shop_id}}}`, 'selected');
            }
            html = html.replace(/\{\{selected_\d+\}\}/g, '');
            
            $('#area-editor').html(html);
        },
        
        onAreaChange: function() {
            if (currentArea === null) return;
            
            const label = $('.area-label').val();
            const shopId = $('.area-shop').val();
            const color = $('.area-color').val();
            
            areas[currentArea].label = label;
            areas[currentArea].shop_id = shopId;
            areas[currentArea].color = color;
            
            // Update visual
            $(`.floorplan-area[data-index="${currentArea}"]`).attr('fill', color);
            this.updateAreasList();
        },
        
        updateArea: function(e) {
            e.preventDefault();
            this.showMessage('Område opdateret');
        },
        
        deleteArea: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const index = parseInt($(e.currentTarget).data('index'));
            
            if (!confirm(centershopFloorplan.strings.confirmDelete)) {
                return;
            }
            
            areas.splice(index, 1);
            currentArea = null;
            
            this.renderAreas();
            $('#area-editor').html('<p class="description">Klik på et område i grundplanen for at redigere det.</p>');
        },
        
        saveFloorplan: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: centershopFloorplan.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'save_floorplan_areas',
                    nonce: centershopFloorplan.nonce,
                    areas: JSON.stringify(areas)
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(centershopFloorplan.strings.saved);
                    } else {
                        this.showMessage(centershopFloorplan.strings.error, 'error');
                    }
                },
                error: () => {
                    this.showMessage(centershopFloorplan.strings.error, 'error');
                }
            });
        },
        
        loadData: function() {
            $.ajax({
                url: centershopFloorplan.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_floorplan_data',
                    nonce: centershopFloorplan.nonce
                },
                success: (response) => {
                    if (response.success) {
                        svgUrl = response.data.svg || '';
                        areas = response.data.areas || [];
                        
                        if (svgUrl) {
                            this.renderAreas();
                        }
                    }
                }
            });
        },
        
        showMessage: function(message, type = 'success') {
            const $message = $(`<div class="floorplan-message ${type}">${message}</div>`);
            $('body').append($message);
            
            setTimeout(() => {
                $message.fadeOut(() => $message.remove());
            }, 3000);
        }
    };
    
    $(document).ready(() => {
        if ($('.centershop-floorplan-admin').length) {
            FloorplanAdmin.init();
        }
    });
    
})(jQuery);
