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
            wp.apiFetch({ path: '/wp/v2/tags' }).then((tags) => {
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

// Initialize listeners on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    console.log("Initializing modal listeners...");
    console.log("hello file");
    initializeModalListeners();
});

function initializeModalListeners() {
    const modalWrappers = document.querySelectorAll('.deaddove-modal-wrapper');

    modalWrappers.forEach((modalWrapper) => {
        const showContentBtn = modalWrapper.querySelector('.deaddove-show-content-btn');
        const hideContentBtn = modalWrapper.querySelector('.deaddove-hide-content-btn');
        const modal = modalWrapper.querySelector('.deaddove-modal');
        const blurredContent = modalWrapper.querySelector('.deaddove-blurred-content');

        if (!showContentBtn || !hideContentBtn || !modal || !blurredContent) {
            console.error("Missing elements in modal wrapper:", { 
                showContentBtn, hideContentBtn, modal, blurredContent 
            });
            return; // Stop execution for this modalWrapper if elements are missing
        }

        console.log("Attaching event listeners...");

        showContentBtn.addEventListener('click', () => {
            console.log("Show content button clicked");
            modal.style.display = 'none'; // Hide modal
            blurredContent.style.display = 'block'; // Show blurred content
        });

        hideContentBtn.addEventListener('click', () => {
            console.log("Hide content button clicked");
            modal.style.display = 'block'; // Show modal
            blurredContent.style.display = 'none'; // Hide blurred content
        });
    });
}
