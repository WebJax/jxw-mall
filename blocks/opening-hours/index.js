const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, CheckboxControl } = wp.components;
const { createElement: el } = wp.element;

registerBlockType('centershop/opening-hours', {
    edit: function(props) {
        const { attributes, setAttributes } = props;
        const { showWeek, showCollected, showToday, showExtraText } = attributes;

        return el(
            'div',
            { className: 'centershop-opening-hours-editor' },
            [
                el(
                    InspectorControls,
                    { key: 'inspector' },
                    el(
                        PanelBody,
                        { 
                            title: 'Vælg visningsformat',
                            initialOpen: true 
                        },
                        [
                            el(CheckboxControl, {
                                key: 'week',
                                label: 'Åbningstider for hele ugen',
                                help: 'Vis åbningstider for hver enkelt dag',
                                checked: showWeek,
                                onChange: function(value) {
                                    setAttributes({ showWeek: value });
                                }
                            }),
                            el(CheckboxControl, {
                                key: 'collected',
                                label: 'Samlet åbningstider',
                                help: 'Vis samlede åbningstider (dage med samme tider grupperes)',
                                checked: showCollected,
                                onChange: function(value) {
                                    setAttributes({ showCollected: value });
                                }
                            }),
                            el(CheckboxControl, {
                                key: 'today',
                                label: 'Åbningstider i dag',
                                help: 'Vis kun dagens åbningstider',
                                checked: showToday,
                                onChange: function(value) {
                                    setAttributes({ showToday: value });
                                }
                            }),
                            el(CheckboxControl, {
                                key: 'extra',
                                label: 'Ekstra tekst',
                                help: 'Vis ekstra information om åbningstider',
                                checked: showExtraText,
                                onChange: function(value) {
                                    setAttributes({ showExtraText: value });
                                }
                            })
                        ]
                    )
                ),
                el(
                    'div',
                    { 
                        key: 'placeholder',
                        className: 'components-placeholder',
                        style: { 
                            padding: '40px',
                            textAlign: 'center',
                            border: '1px dashed #ccc',
                            backgroundColor: '#f0f0f0'
                        }
                    },
                    [
                        el(
                            'div',
                            { 
                                key: 'icon',
                                className: 'components-placeholder__label',
                                style: { marginBottom: '10px' }
                            },
                            el('span', { 
                                className: 'dashicons dashicons-clock',
                                style: { fontSize: '40px' }
                            })
                        ),
                        el(
                            'h3',
                            { 
                                key: 'title',
                                style: { marginBottom: '15px' }
                            },
                            'Center Åbningstider'
                        ),
                        el(
                            'div',
                            { 
                                key: 'info',
                                style: { color: '#666' }
                            },
                            [
                                el('div', { key: 'selected-title', style: { marginBottom: '10px', fontWeight: 'bold' } }, 'Valgte formater:'),
                                showWeek && el('div', { key: 'week' }, '✓ Åbningstider for hele ugen'),
                                showCollected && el('div', { key: 'collected' }, '✓ Samlet åbningstider'),
                                showToday && el('div', { key: 'today' }, '✓ Åbningstider i dag'),
                                showExtraText && el('div', { key: 'extra' }, '✓ Ekstra tekst'),
                                !showWeek && !showCollected && !showToday && !showExtraText && 
                                    el('div', { key: 'none', style: { fontStyle: 'italic', color: '#999' } }, 'Vælg mindst ét format i højre panel')
                            ]
                        )
                    ]
                )
            ]
        );
    },

    save: function() {
        return null; // Dynamic block, rendered in PHP
    }
});
