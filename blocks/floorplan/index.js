import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

registerBlockType('centershop/floorplan', {
    edit: (props) => {
        const { attributes, setAttributes } = props;
        const { shopId, showSearch, height } = attributes;
        
        const blockProps = useBlockProps({
            className: 'centershop-floorplan-block-editor'
        });
        
        // Get all shops
        const shops = useSelect((select) => {
            return select('core').getEntityRecords('postType', 'butiksside', {
                per_page: -1,
                orderby: 'title',
                order: 'asc',
                status: 'publish'
            });
        }, []);
        
        const shopOptions = [
            { label: __('Ingen specifik butik', 'centershop_txtdomain'), value: '' }
        ];
        
        if (shops) {
            shops.forEach(shop => {
                shopOptions.push({
                    label: shop.title.rendered,
                    value: shop.id.toString()
                });
            });
        }
        
        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Grundplan indstillinger', 'centershop_txtdomain')}>
                        <SelectControl
                            label={__('Fremhæv butik', 'centershop_txtdomain')}
                            value={shopId}
                            options={shopOptions}
                            onChange={(value) => setAttributes({ shopId: value })}
                            help={__('Vælg en butik der skal være fremhævet som standard', 'centershop_txtdomain')}
                        />
                        
                        <ToggleControl
                            label={__('Vis søgefelt', 'centershop_txtdomain')}
                            checked={showSearch}
                            onChange={(value) => setAttributes({ showSearch: value })}
                            help={__('Tillad besøgende at søge efter butikker', 'centershop_txtdomain')}
                        />
                        
                        <TextControl
                            label={__('Højde', 'centershop_txtdomain')}
                            value={height}
                            onChange={(value) => setAttributes({ height: value })}
                            help={__('F.eks. 600px eller 80vh', 'centershop_txtdomain')}
                        />
                    </PanelBody>
                </InspectorControls>
                
                <div {...blockProps}>
                    <div style={{
                        padding: '40px',
                        background: '#f0f0f0',
                        border: '2px dashed #ccc',
                        borderRadius: '8px',
                        textAlign: 'center'
                    }}>
                        <div style={{
                            fontSize: '48px',
                            marginBottom: '20px'
                        }}>🗺️</div>
                        <h3 style={{ margin: '0 0 10px 0' }}>
                            {__('Center Grundplan', 'centershop_txtdomain')}
                        </h3>
                        <p style={{ margin: 0, color: '#666' }}>
                            {shopId 
                                ? __('Viser grundplan med fremhævet butik', 'centershop_txtdomain')
                                : __('Viser interaktiv grundplan over centret', 'centershop_txtdomain')
                            }
                        </p>
                        {showSearch && (
                            <p style={{ margin: '10px 0 0 0', fontSize: '14px', color: '#999' }}>
                                {__('✓ Søgefelt aktiveret', 'centershop_txtdomain')}
                            </p>
                        )}
                        <p style={{ margin: '10px 0 0 0', fontSize: '14px', color: '#999' }}>
                            {__('Højde:', 'centershop_txtdomain')} {height}
                        </p>
                    </div>
                </div>
            </>
        );
    },
    
    save: () => {
        return null; // Renders via PHP
    }
});
