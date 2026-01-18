(function(blocks, element, blockEditor, components, data) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var RangeControl = components.RangeControl;
    var useSelect = data.useSelect;

    registerBlockType('centershop/shop-logo-carousel', {
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            // Fetch all shops
            var shops = useSelect(function(select) {
                return select('core').getEntityRecords('postType', 'butiksside', {
                    per_page: -1,
                    status: 'publish',
                    orderby: 'title',
                    order: 'asc'
                });
            }, []);

            return el('div', { className: 'centershop-logo-ticker-editor' },
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Ticker Indstillinger' },
                        el(RangeControl, {
                            label: 'Logo højde (pixels)',
                            value: attributes.logoHeight,
                            onChange: function(value) {
                                setAttributes({ logoHeight: value });
                            },
                            min: 40,
                            max: 200,
                            step: 10
                        }),
                        el(RangeControl, {
                            label: 'Hastighed (sekunder)',
                            help: 'Tid det tager at scrolle igennem alle logoer',
                            value: attributes.animationSpeed,
                            onChange: function(value) {
                                setAttributes({ animationSpeed: value });
                            },
                            min: 10,
                            max: 60,
                            step: 5
                        })
                    )
                ),
                el('div', { className: 'components-placeholder' },
                    el('div', { className: 'components-placeholder__label' },
                        '🎪 Butiks Logo Ticker'
                    ),
                    el('div', { className: 'components-placeholder__instructions' },
                        shops
                            ? 'Viser ' + shops.length + ' butikker i kontinuerlig rotation'
                            : 'Indlæser butikker...'
                    ),
                    el('div', { style: { marginTop: '15px', fontSize: '14px', color: '#666' } },
                        'Logo højde: ' + attributes.logoHeight + 'px • Hastighed: ' + attributes.animationSpeed + 's'
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
