function myPluginAddExtraProps(extraProps, blockType, attributes) {
    if (blockType.name === 'core/button') {
        return {
            ...extraProps,
            'data-a11y-dialog-show': attributes.modalId,
        };
    }

    return extraProps;
}
addFilter('blocks.getSaveContent.extraProps', 'my-plugin/add-extra-props', myPluginAddExtraProps);