const { registerBlockType } = wp.blocks;
const { CheckboxControl } = wp.components;
const { useState, useEffect } = wp.element;
const { InnerBlocks } = wp.blockEditor;

registerBlockType('cw/content-warning', {
    title: 'Content Warning',
    icon: 'warning',
    category: 'design',

    attributes: {
        tags: {
            type: 'array',
            default: [],
        },
    },

    edit: function ({ attributes, setAttributes }) {
        const { tags } = attributes;
        const [availableTags, setAvailableTags] = useState([]);

        // Fetch available post tags via REST API.
        useEffect(() => {
            wp.apiFetch({ path: '/wp/v2/content_warning' }).then((tags) => {
                setAvailableTags(tags);
            });
        }, []);

        const tagOptions = availableTags.map((tag) => ({
            value: tag.id,
            label: tag.name,
        }));

        const toggleTag = (tagId) => {
            setAttributes({
                tags: tags.includes(tagId)
                    ? tags.filter((id) => id !== tagId)
                    : [...tags, tagId],
            });
        };

        return wp.element.createElement(
            'div',
            { className: 'deaddove-modal-wrapper editor-only' }, // Add class for editor view only.
            wp.element.createElement('h4', null, 'Select Tags'),
            tagOptions.map((tag) =>
                wp.element.createElement(CheckboxControl, {
                    key: tag.value,
                    label: tag.label,
                    checked: tags.includes(tag.value),
                    onChange: () => toggleTag(tag.value),
                })
            ),
            wp.element.createElement(InnerBlocks) // Enable nested blocks.
        );
    },

    save: () => {
        // Minimal save function to store nested content without issues.
        return wp.element.createElement(InnerBlocks.Content);
    },

    
});

// Initialize listeners when DOM is ready and watch for new content
document.addEventListener('DOMContentLoaded', initializeModalListeners);

// Also initialize when new content is loaded (for dynamic content)
if (typeof wp !== 'undefined' && wp.domReady) {
    wp.domReady(initializeModalListeners);
}

function initializeModalListeners() {
    console.log("Initializing modal listeners...");
    
    // Use jQuery if available, otherwise use vanilla JS
    const $ = window.jQuery;
    
    if ($) {
        // jQuery approach for better compatibility with WordPress
        $(document).off('click.deaddove').on('click.deaddove', '.deaddove-show-content-btn', function(e) {
            e.preventDefault();
            const $wrapper = $(this).closest('.deaddove-modal-wrapper');
            $wrapper.find('.deaddove-modal').hide();
            $wrapper.find('.deaddove-blurred-content').removeClass('deaddove-blur');
        });
        
        $(document).on('click.deaddove', '.deaddove-hide-content-btn', function(e) {
            e.preventDefault();
            const $wrapper = $(this).closest('.deaddove-modal-wrapper');
            $wrapper.find('.deaddove-modal').hide();
        });
    } else {
        // Vanilla JS fallback
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('deaddove-show-content-btn')) {
                e.preventDefault();
                const wrapper = e.target.closest('.deaddove-modal-wrapper');
                if (wrapper) {
                    const modal = wrapper.querySelector('.deaddove-modal');
                    const blurredContent = wrapper.querySelector('.deaddove-blurred-content');
                    if (modal) modal.style.display = 'none';
                    if (blurredContent) blurredContent.classList.remove('deaddove-blur');
                }
            }
            
            if (e.target.classList.contains('deaddove-hide-content-btn')) {
                e.preventDefault();
                const wrapper = e.target.closest('.deaddove-modal-wrapper');
                if (wrapper) {
                    const modal = wrapper.querySelector('.deaddove-modal');
                    if (modal) modal.style.display = 'none';
                }
            }
        });
    }
}
