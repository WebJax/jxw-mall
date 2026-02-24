const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, RangeControl, SelectControl, ToggleControl } = wp.components;
const { useSelect } = wp.data;
const { __ } = wp.i18n;
const { ServerSideRender } = wp.serverSideRender || wp.components;

registerBlockType('centershop/facebook-feed', {
    edit: ({ attributes, setAttributes }) => {
        const { count, shopId, layout, columns, showDate, showShop, excerptLength } = attributes;
        
        const shops = useSelect((select) => {
            return select('core').getEntityRecords('postType', 'butiksside', {
                per_page: -1,
                status: 'publish'
            });
        }, []);
        
        const shopOptions = [
            { label: __('Alle butikker', 'centershop_txtdomain'), value: 0 }
        ];
        
        if (shops) {
            shops.forEach(shop => {
                shopOptions.push({
                    label: shop.title.rendered,
                    value: shop.id
                });
            });
        }
        
        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Feed Indstillinger', 'centershop_txtdomain')}>
                        <SelectControl
                            label={__('Butik', 'centershop_txtdomain')}
                            value={shopId}
                            options={shopOptions}
                            onChange={(value) => setAttributes({ shopId: parseInt(value) })}
                        />
                        
                        <RangeControl
                            label={__('Antal posts', 'centershop_txtdomain')}
                            value={count}
                            onChange={(value) => setAttributes({ count: value })}
                            min={1}
                            max={50}
                        />
                        
                        <SelectControl
                            label={__('Layout', 'centershop_txtdomain')}
                            value={layout}
                            options={[
                                { label: __('Grid', 'centershop_txtdomain'), value: 'grid' },
                                { label: __('Liste', 'centershop_txtdomain'), value: 'list' }
                            ]}
                            onChange={(value) => setAttributes({ layout: value })}
                        />
                        
                        {layout === 'grid' && (
                            <RangeControl
                                label={__('Antal kolonner', 'centershop_txtdomain')}
                                value={columns}
                                onChange={(value) => setAttributes({ columns: value })}
                                min={1}
                                max={6}
                            />
                        )}
                        
                        <ToggleControl
                            label={__('Vis dato', 'centershop_txtdomain')}
                            checked={showDate}
                            onChange={(value) => setAttributes({ showDate: value })}
                        />
                        
                        <ToggleControl
                            label={__('Vis butiksnavn', 'centershop_txtdomain')}
                            checked={showShop}
                            onChange={(value) => setAttributes({ showShop: value })}
                        />
                        
                        <RangeControl
                            label={__('Tekst længde', 'centershop_txtdomain')}
                            value={excerptLength}
                            onChange={(value) => setAttributes({ excerptLength: value })}
                            min={50}
                            max={500}
                        />
                    </PanelBody>
                </InspectorControls>
                
                <div className="centershop-block-preview">
                    <ServerSideRender
                        block="centershop/facebook-feed"
                        attributes={attributes}
                    />
                </div>
            </>
        );
    },
    
    save: () => {
        return null; // Server-side rendered
    }
});
