(function (blocks, element, blockEditor, components, serverSideRender) {
    var el = element.createElement;
    var Fragment = element.Fragment;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var RangeControl = components.RangeControl;

    blocks.registerBlockType('centershop/floorplan', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var focus = attributes.focus || '';
            var showSearch = attributes.showSearch !== false;
            var height = attributes.height || 560;
            var focusZoom = attributes.focusZoom || 150;
            var blockProps = useBlockProps({
                className: 'centershop-floorplan-block'
            });
            var targets = (window.centershopFloorplanBlock && window.centershopFloorplanBlock.targets) || [
                { label: 'Hele kortet', value: '' }
            ];

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: 'Grundplan', initialOpen: true },
                        el(SelectControl, {
                            label: 'Centrer på',
                            value: focus,
                            options: targets,
                            help: 'Hele kortet, eller en butik eller service som kortet åbner zoomet ind på.',
                            onChange: function (value) {
                                setAttributes({ focus: value || '' });
                            }
                        }),
                        el(ToggleControl, {
                            label: 'Vis søgning',
                            checked: showSearch,
                            help: 'Besøgende kan søge efter en butik og hoppe hen til den på kortet.',
                            onChange: function (value) {
                                setAttributes({ showSearch: !!value });
                            }
                        }),
                        el(RangeControl, {
                            label: 'Zoom ved søgning (%)',
                            value: focusZoom,
                            min: 100,
                            max: 250,
                            step: 10,
                            help: 'Hvor tæt kortet zoomer, når en butik eller service bliver fundet. 100 % viser kortet i fuld højde.',
                            onChange: function (value) {
                                setAttributes({ focusZoom: value || 150 });
                            }
                        }),
                        el(RangeControl, {
                            label: 'Højde (px)',
                            value: height,
                            min: 240,
                            max: 900,
                            step: 20,
                            help: 'På en smal skærm bliver højden højst 78% af vinduet, så siden stadig kan scrolles.',
                            onChange: function (value) {
                                setAttributes({ height: value || 560 });
                            }
                        })
                    )
                ),
                el(
                    'div',
                    blockProps,
                    el(serverSideRender, {
                        block: 'centershop/floorplan',
                        attributes: {
                            focus: focus,
                            showSearch: showSearch,
                            height: height,
                            focusZoom: focusZoom
                        }
                    })
                )
            );
        },

        save: function () {
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender
);
