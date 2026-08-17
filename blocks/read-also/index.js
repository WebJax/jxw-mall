(function (blocks, element, blockEditor, components, data) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InpectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var TextControl = components.TextControl;
	var Button = components.Button;
	var Spinner = components.Spinner;
	var Placeholder = components.Placeholder;
	var useSelect = data.useSelect;
	var useState = element.useState;
	var RawHTML = element.RawHTML;

	// Post types the user can search across. The label is shown next to each result.
	var SEARCH_TYPES = [
		{ slug: 'post', label: 'Artikel' },
		{ slug: 'page', label: 'Side' },
		{ slug: 'butiksside', label: 'Butik' },
		{ slug: 'event', label: 'Begivenhed' },
		{ slug: 'tribe_events', label: 'Begivenhed' }
	];

	// Danish labels for known post type slugs.
	function typeLabel(slug) {
		var found = SEARCH_TYPES.find(function (t) {
			return t.slug === slug;
		});
		return found ? found.label : slug;
	}

	// Strip HTML tags from a string for plain text display.
	function stripHtml(html) {
		if (!html) {
			return '';
		}
		var tmp = document.createElement('div');
		tmp.innerHTML = html;
		return tmp.textContent || tmp.innerText || '';
	}

	// Truncate text to a number of words.
	function truncate(text, words) {
		var clean = stripHtml(text).trim();
		if (!clean) {
			return '';
		}
		var parts = clean.split(/\s+/);
		if (parts.length <= words) {
			return clean;
		}
		return parts.slice(0, words).join(' ') + '…';
	}

	// Format an ISO date string (e.g. 2024-01-31T12:00:00) into a readable date.
	function formatDate(iso) {
		if (!iso) {
			return '';
		}
		var d = new Date(iso);
		if (isNaN(d.getTime())) {
			return '';
		}
		return d.toLocaleDateString('da-DK', {
			day: 'numeric',
			month: 'long',
			year: 'numeric'
		});
	}

	blocks.registerBlockType('centershop/read-also', {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var postId = attributes.postId;
			var postType = attributes.postType;
			var excerptLength = attributes.excerptLength;
			var blockProps = useBlockProps({
				className: 'centershop-read-also-block'
			});

			var search = useState('');
			var searchTerm = search[0];
			var setSearchTerm = search[1];

			// Load the selected post with embedded featured media + meta.
			var selectedPost = useSelect(function (select) {
				if (!postId) {
					return null;
				}
				var slug = postType || 'post';
				return select('core').getEntityRecord('postType', slug, postId, {
					_embed: true,
					context: 'edit'
				});
			}, [postId, postType]);

			// Fetch search results across all configured post types.
			var results = useSelect(function (select) {
				var term = searchTerm.trim();
				if (term.length < 2) {
					return null;
				}
				var all = [];
				SEARCH_TYPES.forEach(function (type) {
					var records = select('core').getEntityRecords('postType', type.slug, {
						per_page: 10,
						search: term,
						status: 'publish',
						_embed: true,
						context: 'edit'
					});
					if (records) {
						records.forEach(function (record) {
							all.push({ record: record, type: type.slug });
						});
					}
				});
				return all;
			}, [searchTerm]);

			// Detect whether the selected post is an event type.
			function isEventType(slug) {
				return slug === 'event' || slug === 'tribe_events';
			}

			// Build the live preview card mirroring the PHP render output.
			function renderPreviewCard(post, slug) {
				if (!post) {
					return el(Spinner);
				}

				var featuredImage =
					post._embedded &&
					post._embedded['wp:featuredmedia'] &&
					post._embedded['wp:featuredmedia'][0]
						? post._embedded['wp:featuredmedia'][0].source_url
						: null;

				var meta = post.meta || {};

				var body = [];

				body.push(
					el(
						'h3',
						{ className: 'centershop-read-also-card__title', key: 'title' },
						el(RawHTML, {}, post.title.rendered)
					)
				);

				if (isEventType(slug)) {
					// Event: show event date, start time and place.
					var eventDate =
						meta._event_date || meta._EventStartDate || meta.event_date || '';
					var startTime =
						meta._event_start_time || meta._EventStartTime || meta.event_start_time || '';
					var address =
						meta._event_address ||
						meta._EventVenue ||
						meta.event_address ||
						meta._VenueAddress ||
						'';

					if (eventDate) {
						var dateText = formatDate(eventDate);
						if (startTime) {
							dateText += ' kl. ' + startTime;
						}
						body.push(
							el(
								'p',
								{ className: 'centershop-read-also-card__meta', key: 'eventdate' },
								'Begivenhed: ' + dateText
							)
						);
					}
					if (address) {
						body.push(
							el(
								'p',
								{ className: 'centershop-read-also-card__meta', key: 'address' },
								'Sted: ' + stripHtml(address)
							)
						);
					}
				} else {
					// Article / page / shop: show publish date and first lines.
					body.push(
						el(
							'p',
							{ className: 'centershop-read-also-card__meta', key: 'date' },
							'Udgivet: ' + formatDate(post.date)
						)
					);

					var excerpt = truncate(post.excerpt && post.excerpt.rendered ? post.excerpt.rendered : post.content && post.content.rendered, excerptLength);
					if (excerpt) {
						body.push(
							el(
								'p',
								{ className: 'centershop-read-also-card__excerpt', key: 'excerpt' },
								excerpt
							)
						);
					}
				}

				var image = featuredImage
					? el('img', {
							className: 'centershop-read-also-card__image',
							src: featuredImage,
							alt: stripHtml(post.title.rendered)
					  })
					: null;

				return el(
					'a',
					{
						className: 'centershop-read-also-card',
						href: post.link,
						key: 'card'
					},
					image,
					el('div', { className: 'centershop-read-also-card__body' }, body)
				);
			}

			// Sidebar controls.
			var inspectorControls = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: 'Indstillinger', initialOpen: true },
					el(RangeControl, {
						label: 'Antal ord i uddrag',
						value: excerptLength,
						min: 5,
						max: 60,
						onChange: function (value) {
							setAttributes({ excerptLength: value });
						}
					}),
					postId
						? el(
								Button,
								{
									isDestructive: true,
									variant: 'secondary',
									onClick: function () {
										setAttributes({ postId: 0, postType: '' });
									}
								},
								'Fravælg valgt indhold'
						  )
						: null
				)
			);

			// Search UI shown when nothing is selected yet.
			function renderSearch() {
				var searchResults = [];

				if (results && results.length === 0) {
					searchResults.push(
						el(
							'p',
							{ className: 'centershop-read-also-search__item', key: 'none' },
							'Ingen resultater.'
						)
					);
				} else if (results) {
					results.forEach(function (item) {
						searchResults.push(
							el(
								'button',
								{
									type: 'button',
									className: 'centershop-read-also-search__item',
									key: item.type + '-' + item.record.id,
									onClick: function () {
										setAttributes({ postId: item.record.id, postType: item.type });
										setSearchTerm('');
									}
								},
								el(
									'span',
									{ className: 'centershop-read-also-search__type' },
									typeLabel(item.type)
								),
								stripHtml(item.record.title.rendered)
							)
						);
					});
				}

				return el(
					'div',
					{ className: 'centershop-read-also-search' },
					el(TextControl, {
						label: 'Søg efter artikel, begivenhed eller side',
						placeholder: 'Skriv mindst 2 tegn…',
						value: searchTerm,
						onChange: setSearchTerm
					}),
					searchTerm.trim().length >= 2 && !results
						? el(Spinner)
						: el(
								'div',
								{ className: 'centershop-read-also-search__results' },
								searchResults
						  )
				);
			}

			var inner;
			if (postId && !selectedPost) {
				inner = el(Spinner);
			} else if (postId && selectedPost) {
				inner = renderPreviewCard(selectedPost, postType || 'post');
			} else {
				inner = el(
					Placeholder,
					{
						icon: 'megaphone',
						label: 'Læs også',
						instructions: 'Søg efter indhold for at oprette et klikbart kort.'
					},
					renderSearch()
				);
			}

			return el('div', {}, inspectorControls, el('div', blockProps, inner));
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
	window.wp.data
);
