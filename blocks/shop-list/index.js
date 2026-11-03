(function(blocks, element, blockEditor, components, data) {
    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var CheckboxControl = components.CheckboxControl;
    var Spinner = components.Spinner;
    var useSelect = data.useSelect;
    var RawHTML = element.RawHTML;

    function normalizeAvailability(value) {
        return value === 'for_rent' ? 'for_rent' : 'occupied';
    }

    function shopMatchesFilters(shop, availability, categoryIds) {
        var shopAvailability = normalizeAvailability(shop.meta && shop.meta.butik_availability);
        if (availability && availability !== 'all' && shopAvailability !== availability) {
            return false;
        }

        if (categoryIds && categoryIds.length) {
            var shopCats = (shop.categories || []).map(function(id) {
                return parseInt(id, 10);
            });
            var hasMatch = categoryIds.some(function(id) {
                return shopCats.indexOf(parseInt(id, 10)) !== -1;
            });
            if (!hasMatch) {
                return false;
            }
        }

        return true;
    }

    function renderShopPreview(shop) {
        var featuredImage = shop._embedded && shop._embedded['wp:featuredmedia']
            ? shop._embedded['wp:featuredmedia'][0].source_url
            : null;

        var logoId = shop.meta && shop.meta['allround-cpt_logo_id'];
        var availability = normalizeAvailability(shop.meta && shop.meta.butik_availability);
        var elements = [];

        if (featuredImage) {
            elements.push(
                el('div', { className: 'centershop-featured-image', key: 'featured' },
                    el('img', { src: featuredImage, alt: shop.title.rendered })
                )
            );
        }

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

        elements.push(
            el('h3', { className: 'centershop-title', key: 'title' },
                el(RawHTML, {}, shop.title.rendered)
            )
        );

        if (availability === 'for_rent') {
            elements.push(
                el('span', { className: 'centershop-availability-badge', key: 'status' }, 'Til leje / salg')
            );
        }

        var infoElements = [];

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
    }

    blocks.registerBlockType('centershop/shop-list', {
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var availability = attributes.availability || 'all';
            var categoryIds = Array.isArray(attributes.categories)
                ? attributes.categories.map(function(id) {
                    return parseInt(id, 10);
                }).filter(function(id) {
                    return id > 0;
                })
                : [];

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

            var categories = useSelect(function(select) {
                return select('core').getEntityRecords('taxonomy', 'category', {
                    per_page: -1,
                    hide_empty: false
                });
            }, []);

            var inspectorControls = el(InspectorControls, {},
                el(PanelBody, { title: 'Filtre', initialOpen: true },
                    el(SelectControl, {
                        label: 'Status',
                        help: 'Vælg hvilke butikker listen skal vise.',
                        value: availability,
                        options: [
                            { label: 'Alle', value: 'all' },
                            { label: 'Aktiv butik', value: 'occupied' },
                            { label: 'Til leje / salg', value: 'for_rent' }
                        ],
                        onChange: function(value) {
                            setAttributes({ availability: value || 'all' });
                        }
                    }),
                    el('p', { className: 'components-base-control__label' }, 'Kategorier'),
                    el('p', { className: 'components-base-control__help' },
                        'Vælg én eller flere kategorier. Ingen valgt viser alle.'
                    ),
                    !categories
                        ? el(Spinner)
                        : (categories.length === 0
                            ? el('p', {}, 'Ingen kategorier fundet.')
                            : categories.map(function(category) {
                                return el(CheckboxControl, {
                                    key: category.id,
                                    label: category.name,
                                    checked: categoryIds.indexOf(category.id) !== -1,
                                    onChange: function(checked) {
                                        var next = categoryIds.slice();
                                        if (checked) {
                                            if (next.indexOf(category.id) === -1) {
                                                next.push(category.id);
                                            }
                                        } else {
                                            next = next.filter(function(id) {
                                                return id !== category.id;
                                            });
                                        }
                                        setAttributes({ categories: next });
                                    }
                                });
                            })
                        )
                )
            );

            if (!shops) {
                return el('div', {},
                    inspectorControls,
                    el('div', blockProps,
                        el(Spinner)
                    )
                );
            }

            var visibleShops = shops.filter(function(shop) {
                return shopMatchesFilters(shop, availability, categoryIds);
            });

            if (visibleShops.length === 0) {
                return el('div', {},
                    inspectorControls,
                    el('div', blockProps,
                        el('p', {}, shops.length === 0
                            ? 'Ingen butikker fundet.'
                            : 'Ingen butikker matcher de valgte filtre.')
                    )
                );
            }

            return el('div', {},
                inspectorControls,
                el('div', blockProps,
                    el('div', { className: 'centershop-grid' },
                        visibleShops.map(renderShopPreview)
                    )
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
