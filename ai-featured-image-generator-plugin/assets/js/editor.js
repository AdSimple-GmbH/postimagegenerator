(function (plugins, editPost, element, components, i18n) {
    var el = element.createElement;
    var __ = i18n.__;

    var AIFeaturedImageButton = function () {
        return el(
            editPost.PluginDocumentSettingPanel,
            {
                name: 'ai-featured-image-panel',
                title: __( 'AI Featured Image', 'ai-featured-image' ),
                className: 'ai-featured-image-panel',
            },
            el(
                'p',
                {},
                'This is a placeholder for the AI Featured Image generator button.'
            ),
            el(
                components.Button,
                {
                    isPrimary: true,
                    id: 'ai-featured-image-generate-button-gutenberg'
                },
                __( 'AI Beitragsbild festlegen', 'ai-featured-image' )
            )
        );
    };

    plugins.registerPlugin('ai-featured-image', {
        render: AIFeaturedImageButton,
        icon: null,
    });
})(
    window.wp.plugins,
    window.wp.editPost,
    window.wp.element,
    window.wp.components,
    window.wp.i18n
); 