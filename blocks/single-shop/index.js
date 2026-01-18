(function(blocks, element, blockEditor, components, data) {
    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var Spinner = components.Spinner;
    var Placeholder = components.Placeholder;
    var useSelect = data.useSelect;
    var RawHTML = element.RawHTML;

    blocks.registerBlockType('centershop/single-shop', {
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var shopId = attributes.shopId;

            var blockProps = useBlockProps({
                className: 'centershop-single-block'
            });

            // Fetch all shops for dropdown
            var shops = useSelect(function(select) {
                return select('core').getEntityRecords('postType', 'butiksside', {
                    per_page: -1,
                    status: 'publish',
                    orderby: 'title',
                    order: 'asc'
                });
            }, []);

            // Fetch selected shop
            var selectedShop = useSelect(function(select) {
                if (!shopId) return null;
                return select('core').getEntityRecord('postType', 'butiksside', shopId, {
                    _embed: true
                });
            }, [shopId]);

            if (!shops) {
                return el('div', blockProps,
                    el(Spinner)
                );
            }

            // Create options for select control
            var shopOptions = [{ label: 'Vælg en butik...', value: 0 }].concat(
                shops.map(function(shop) {
                    return {
                        label: shop.title.rendered,
                        value: shop.id
                    };
                })
            );

            // Inspector controls (sidebar)
            var inspectorControls = el(InspectorControls, {},
                el(PanelBody, { title: 'Butik Indstillinger', initialOpen: true },
                    el(SelectControl, {
                        label: 'Vælg butik',
                        value: shopId,
                        options: shopOptions,
                        onChange: function(newShopId) {
                            setAttributes({ shopId: parseInt(newShopId) });
                        }
                    })
                )
            );

            // If no shop selected, show placeholder
            if (!shopId) {
                return el('div', {},
                    inspectorControls,
                    el('div', blockProps,
                        el(Placeholder, {
                            icon: 'store',
                            label: 'Enkelt Butik',
                            instructions: 'Vælg en butik fra højre sidebar for at vise den.'
                        })
                    )
                );
            }

            // If shop is loading
            if (!selectedShop) {
                return el('div', {},
                    inspectorControls,
                    el('div', blockProps,
                        el(Spinner)
                    )
                );
            }

            // Render selected shop
            var featuredImage = selectedShop._embedded && selectedShop._embedded['wp:featuredmedia'] 
                ? selectedShop._embedded['wp:featuredmedia'][0].source_url 
                : null;
            
            var logoId = selectedShop.meta && selectedShop.meta['allround-cpt_logo_id'];
            
            var elements = [];
            
            // Featured image
            if (featuredImage) {
                elements.push(
                    el('div', { className: 'centershop-featured-image', key: 'featured' },
                        el('img', { src: featuredImage, alt: selectedShop.title.rendered })
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
                    el(RawHTML, {}, selectedShop.title.rendered)
                )
            );
            
            // Info section
            var infoElements = [];
            
            // Address
            if (selectedShop.meta && selectedShop.meta.butik_payed_adress) {
                var addressText = selectedShop.meta.butik_payed_adress;
                if (selectedShop.meta.butik_payed_postal) addressText += ', ' + selectedShop.meta.butik_payed_postal;
                if (selectedShop.meta.butik_payed_city) addressText += ' ' + selectedShop.meta.butik_payed_city;
                
                infoElements.push(
                    el('p', { className: 'centershop-address', key: 'address' },
                        el('span', { className: 'dashicons dashicons-location' }),
                        addressText
                    )
                );
            }
            
            // Email
            if (selectedShop.meta && selectedShop.meta.butik_payed_mail) {
                infoElements.push(
                    el('p', { className: 'centershop-email', key: 'email' },
                        el('span', { className: 'dashicons dashicons-email' }),
                        el('a', { href: 'mailto:' + selectedShop.meta.butik_payed_mail },
                            selectedShop.meta.butik_payed_mail
                        )
                    )
                );
            }
            
            // Website
            if (selectedShop.meta && selectedShop.meta.butik_payed_web) {
                infoElements.push(
                    el('p', { className: 'centershop-website', key: 'web' },
                        el('span', { className: 'dashicons dashicons-admin-site' }),
                        el('a', { 
                            href: selectedShop.meta.butik_payed_web, 
                            target: '_blank', 
                            rel: 'noopener noreferrer' 
                        }, 'Besøg hjemmeside')
                    )
                );
            }
            
            // Social media
            var socialElements = [];
            if (selectedShop.meta && selectedShop.meta.butik_payed_fb) {
                socialElements.push(
                    el('a', { 
                        key: 'fb',
                        href: selectedShop.meta.butik_payed_fb, 
                        target: '_blank', 
                        rel: 'noopener noreferrer',
                        className: 'centershop-fb',
                        title: 'Facebook'
                    },
                        el('span', { className: 'dashicons dashicons-facebook' })
                    )
                );
            }
            
            if (selectedShop.meta && selectedShop.meta.butik_payed_insta) {
                socialElements.push(
                    el('a', { 
                        key: 'insta',
                        href: selectedShop.meta.butik_payed_insta, 
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

            return el('div', {},
                inspectorControls,
                el('div', blockProps,
                    el('div', { className: 'centershop-item' }, elements)
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
