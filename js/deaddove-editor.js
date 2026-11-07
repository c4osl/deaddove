(function () {
	const { registerFormatType, getTextContent, slice, insert, remove } =
		wp.richText;
	const { RichTextToolbarButton } = wp.blockEditor;
	const { CheckboxControl, Popover, Button } = wp.components;
	const { useState, useEffect, createElement, Fragment } = wp.element;
	const { registerPlugin } = wp.plugins;
	const { useSelect, useDispatch } = wp.data;
	const PluginDocumentSettingPanel =
		(wp.editor && wp.editor.PluginDocumentSettingPanel) ||
		wp.editPost.PluginDocumentSettingPanel;

	function getSelectionRect() {
		const selection = window.getSelection();
		return selection.rangeCount
			? selection.getRangeAt(0).getBoundingClientRect()
			: null;
	}

	const WRAPPED_SHORTCODE =
		/^\[content_warning tags="([^"]*)"\]([\s\S]*)\[\/content_warning\]$/;

	async function fetchTerms() {
		const terms = [];
		let page = 1;
		let totalPages = 1;
		while (page <= totalPages) {
			const response = await wp.apiFetch({
				path: `/wp/v2/content_warning?per_page=100&page=${page}`,
				parse: false,
			});
			terms.push(...(await response.json()));
			totalPages = Number(response.headers.get('X-WP-TotalPages'));
			page += 1;
		}
		return terms;
	}

	const CLOSING_TAG = '[/content_warning]';

	let termsRequest;

	function getTerms() {
		if (!termsRequest) {
			termsRequest = fetchTerms().catch((error) => {
				termsRequest = undefined;
				throw error;
			});
		}
		return termsRequest;
	}

	function ContentWarningToolbarButton({ value, onChange, contentRef }) {
		const [isOpen, setIsOpen] = useState(false);
		const [snapshot, setSnapshot] = useState(null);
		const [terms, setTerms] = useState([]);
		const [selected, setSelected] = useState([]);

		useEffect(() => {
			getTerms()
				.then(setTerms)
				.catch((error) => {
					console.error(
						'Error fetching content warning terms:',
						error
					);
				});
		}, []);

		const text = getTextContent(slice(value));
		const wrapped = text.match(WRAPPED_SHORTCODE);

		const openLength = wrapped ? text.indexOf(']') + 1 : 0;

		const unwrap = (richText) => {
			if (!wrapped) {
				return richText;
			}
			const withoutClosing = remove(
				richText,
				richText.end - CLOSING_TAG.length,
				richText.end
			);
			return remove(
				withoutClosing,
				richText.start,
				richText.start + openLength
			);
		};

		const open = () => {
			setSnapshot(value);
			setSelected(wrapped ? wrapped[1].split(',').filter(Boolean) : []);
			setIsOpen(true);
		};

		const toggleTerm = (slug) => {
			setSelected(
				selected.includes(slug)
					? selected.filter((item) => item !== slug)
					: [...selected, slug]
			);
		};

		const apply = () => {
			const start = snapshot.start;
			const end = wrapped
				? snapshot.end - openLength - CLOSING_TAG.length
				: snapshot.end;
			const opening = `[content_warning tags="${selected.join(',')}"]`;
			onChange(
				insert(
					insert(unwrap(snapshot), CLOSING_TAG, end, end),
					opening,
					start,
					start
				)
			);
			setIsOpen(false);
		};

		const removeWarning = () => {
			onChange(unwrap(snapshot));
			setIsOpen(false);
		};

		return createElement(
			Fragment,
			null,
			createElement(RichTextToolbarButton, {
				icon: 'warning',
				title: 'Content warning',
				onClick: open,
				isActive: Boolean(wrapped),
				isDisabled: value.start === value.end,
			}),
			isOpen &&
				createElement(
					Popover,
					{
						...(contentRef
								? { anchor: contentRef.current }
								: { getAnchorRect: getSelectionRect }),
						onClose: () => setIsOpen(false),
						focusOnMount: 'firstElement',
					},
					createElement(
						'div',
						{ style: { padding: '12px', minWidth: '220px' } },
						terms.map((term) =>
							createElement(CheckboxControl, {
								key: term.id,
								label: term.name,
								checked: selected.includes(term.slug),
								onChange: () => toggleTerm(term.slug),
							})
						),
						createElement(
							'div',
							{
								style: {
									display: 'flex',
									gap: '8px',
									marginTop: '12px',
								},
							},
							createElement(
								Button,
								{
									variant: 'primary', className: 'is-primary',
									onClick: apply,
									disabled: selected.length === 0,
								},
								'Apply'
							),
							wrapped &&
								createElement(
									Button,
									{
										variant: 'secondary', className: 'is-secondary',
										onClick: removeWarning,
									},
									'Remove'
								)
						)
					)
				)
		);
	}

	registerFormatType('deaddove/content-warning', {
		title: 'Content warning',
		tagName: 'span',
		className: 'deaddove-content-warning-format',
		edit: ContentWarningToolbarButton,
	});

	function FeaturedImageBlurPanel() {
		const meta = useSelect(
			(select) => select('core/editor').getEditedPostAttribute('meta'),
			[]
		);
		const { editPost } = useDispatch('core/editor');

		if (!meta) {
			return null;
		}

		return createElement(
			PluginDocumentSettingPanel,
			{ name: 'deaddove-featured-image', title: 'Content warning' },
			createElement(CheckboxControl, {
				label: 'Content warning on featured image',
				checked: meta._blured_featured_image === 1,
				onChange: (checked) =>
					editPost({
						meta: { _blured_featured_image: checked ? 1 : 0 },
					}),
			})
		);
	}

	function DeaddoveDocumentPanels() {
		const postType = useSelect(
			(select) => select('core/editor').getCurrentPostType(),
			[]
		);

		if (postType !== 'post') {
			return null;
		}
		return createElement(FeaturedImageBlurPanel);
	}

	registerPlugin('deaddove-document-panels', {
		render: DeaddoveDocumentPanels,
	});
})();
