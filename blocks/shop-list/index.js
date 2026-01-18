(function(blocks, element, blockEditor, components, data) {
    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var Spinner = components.Spinner;
    var useSelect = data.useSelect;
    var RawHTML = element.RawHTML;

    blocks.registerBlockType('centershop/shop-list', {
        edit: function(props) {
            var blockProps = useBlockProps({
                className: 'centershop-block-list'
            });

            var shops = useSelect(function(select) {
                return select('core').getEntityRecords('postType', 'butiksside', {
                    per_page: -1,
                    status: 'publish',
                    _embed: true
                });
            }, []);

            if (!shops) {
                return el('div', blockProps,
                    el(Spinner)
                );
            }

            if (shops.length === 0) {
                return el('div', blockProps,
                    el('p', {}, 'Ingen butikker fundet.')
                );
            }

            return el('div', blockProps,
                el('div', { className: 'centershop-grid' },
                    shops.map(function(shop) {
                        var featuredImage = shop._embedded && shop._embedded['wp:featuredmedia'] 
                            ? shop._embedded['wp:featuredmedia'][0].source_url 
                            : null;
                        
                        var logoId = shop.meta && shop.meta['allround-cpt_logo_id'];
                        var logoUrl = logoId ? wp.media.attachment(logoId).get('url') : null;
                        
                        var elements = [];
                        
                        // Featured image
                        if (featuredImage) {
                            elements.push(
                                el('div', { className: 'centershop-featured-image', key: 'featured' },
                                    el('img', { src: featuredImage, alt: shop.title.rendered })
                                )
                            );
                        }
                        
                        // Logo
                        if (logoId) {
                            elements.push(
                                el('div', { className: 'centershop-logo', key: 'logo' },
                                    el('img', { 
                                        src: wp.media.attachment(logoId) ? wp.media.attachment(logoId).get('url') : '', 
                                        alt: 'Logo' 
                                    })
                                )
                            );
                        }
                        
                        // Title
                        elements.push(
                            el('h3', { className: 'centershop-title', key: 'title' },
                                el(RawHTML, {}, shop.title.rendered)
                            )
                        );
                        
                        // Info section
                        var infoElements = [];
                        
                        // Address
                        if (shop.meta && shop.meta.butik_payed_adress) {
                            var addressText = shop.meta.butik_payed_adress;
                            if (shop.meta.butik_payed_postal) addressText += ', ' + shop.meta.butik_payed_postal;
                            if (shop.meta.butik_payed_city) addressText += ' ' + shop.meta.butik_payed_city;
                            
                            infoElements.push(
                                el('p', { className: 'centershop-address', key: 'address' },
                                    el('span', { className: 'dashicons dashicons-location' }),
                                    addressText
                                )
                            );
                        }
                        
                        // Email
                        if (shop.meta && shop.meta.butik_payed_mail) {
                            infoElements.push(
                                el('p', { className: 'centershop-email', key: 'email' },
                                    el('span', { className: 'dashicons dashicons-email' }),
                                    el('a', { href: 'mailto:' + shop.meta.butik_payed_mail },
                                        shop.meta.butik_payed_mail
                                    )
                                )
                            );
                        }
                        
                        // Website
                        if (shop.meta && shop.meta.butik_payed_web) {
                            infoElements.push(
                                el('p', { className: 'centershop-website', key: 'web' },
                                    el('span', { className: 'dashicons dashicons-admin-site' }),
                                    el('a', { 
                                        href: shop.meta.butik_payed_web, 
                                        target: '_blank', 
                                        rel: 'noopener noreferrer' 
                                    }, 'Besøg hjemmeside')
                                )
                            );
                        }
                        
                        // Social media
                        var socialElements = [];
                        if (shop.meta && shop.meta.butik_payed_fb) {
                            socialElements.push(
                                el('a', { 
                                    key: 'fb',
                                    href: shop.meta.butik_payed_fb, 
                                    target: '_blank', 
                                    rel: 'noopener noreferrer',
                                    className: 'centershop-fb',
                                    title: 'Facebook'
                                },
                                    el('span', { className: 'dashicons dashicons-facebook' })
                                )
                            );
                        }
                        
                        if (shop.meta && shop.meta.butik_payed_insta) {
                            socialElements.push(
                                el('a', { 
                                    key: 'insta',
                                    href: shop.meta.butik_payed_insta, 
                                    target: '_blank', 
                                    rel: 'noopener noreferrer',
                                    className: 'centershop-insta',
                                    title: 'Instagram'
                                },
                                    el('span', { className: 'dashicons dashicons-instagram' })
                                )
                            );
                        }
                        
                        if (socialElements.length > 0) {
                            infoElements.push(
                                el('div', { className: 'centershop-social', key: 'social' }, socialElements)
                            );
                        }
                        
                        elements.push(
                            el('div', { className: 'centershop-info', key: 'info' }, infoElements)
                        );
                        
                        return el('div', { className: 'centershop-item', key: shop.id }, elements);
                    })
                )
            );
        },

        save: function() {
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.data
);
