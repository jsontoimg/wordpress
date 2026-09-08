import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	Notice,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const FORMATS = [
	{ label: 'PNG', value: 'png' },
	{ label: 'JPG', value: 'jpg' },
	{ label: 'WebP', value: 'webp' },
	{ label: 'AVIF', value: 'avif' },
];

function useDebouncedValue(value, delay) {
	const [debounced, setDebounced] = useState(value);

	useEffect(() => {
		const id = setTimeout(() => setDebounced(value), delay);
		return () => clearTimeout(id);
	}, [value, delay]);

	return debounced;
}

function layerFields(layer) {
	const attributes = Array.isArray(layer.attributes) ? layer.attributes : [];
	return attributes.filter((attr) => {
		if (!attr || !attr.key) {
			return false;
		}
		return (
			attr.key === 'text' ||
			attr.key === 'image_url' ||
			attr.type === 'text' ||
			attr.type === 'string' ||
			attr.type === 'url'
		);
	});
}

function errorMessage(error, fallback) {
	return (error && error.message) || fallback;
}

function compactLayers(layers) {
	const out = {};
	if (!layers || typeof layers !== 'object') {
		return out;
	}

	Object.entries(layers).forEach(([name, override]) => {
		if (!override || typeof override !== 'object') {
			return;
		}
		const row = {};
		Object.entries(override).forEach(([key, value]) => {
			if (typeof value !== 'string') {
				return;
			}
			const trimmed = value.trim();
			if (!trimmed) {
				return;
			}
			row[key] = trimmed;
		});
		if (Object.keys(row).length > 0) {
			out[name] = row;
		}
	});

	return out;
}

function isAllowedImageUrl(value) {
	const trimmed = String(value || '').trim();
	if (/^\/api\/uploads\/files\/[^/?#]+$/.test(trimmed)) {
		return true;
	}
	try {
		const parsed = new URL(trimmed);
		if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
			return false;
		}
		return /\.(png|jpe?g|webp)$/i.test(parsed.pathname);
	} catch {
		return false;
	}
}

function imageUrlError(layers) {
	const compacted = compactLayers(layers);
	const invalid = Object.values(compacted).some(
		(override) => override.image_url && !isAllowedImageUrl(override.image_url)
	);
	if (!invalid) {
		return '';
	}
	return __(
		'Image URL must be https and end with .png, .jpg, .jpeg, or .webp (or an Assets path /api/uploads/files/{id}). placehold.co without a file extension is rejected; even with .png many placeholder CDNs block the renderer.',
		'jsontoimg'
	);
}

export default function Edit({ attributes, setAttributes }) {
	const { template, format, layers, alt, width, height } = attributes;
	const [templates, setTemplates] = useState([]);
	const [schema, setSchema] = useState(null);
	const [previewUrl, setPreviewUrl] = useState('');
	const [loadingTemplates, setLoadingTemplates] = useState(true);
	const [loadingSchema, setLoadingSchema] = useState(false);
	const [loadingPreview, setLoadingPreview] = useState(false);
	const [error, setError] = useState('');
	const [settingFeatured, setSettingFeatured] = useState(false);
	const [featuredNotice, setFeaturedNotice] = useState('');

	const postId = useSelect((select) => {
		const editor = select('core/editor');
		return editor && editor.getCurrentPostId ? editor.getCurrentPostId() : 0;
	}, []);

	const supportsThumbnail = useSelect(
		(select) => {
			const editor = select('core/editor');
			const postType =
				editor && editor.getCurrentPostType ? editor.getCurrentPostType() : '';
			if (!postType) {
				return false;
			}
			const type = select('core').getPostType(postType);
			return Boolean(type && type.supports && type.supports.thumbnail);
		},
		[]
	);

	const { editPost } = useDispatch('core/editor');

	const debouncedLayers = useDebouncedValue(layers, 400);

	useEffect(() => {
		let cancelled = false;
		setLoadingTemplates(true);

		apiFetch({ path: addQueryArgs('/jsontoimg/v1/templates', { limit: 100 }) })
			.then((data) => {
				if (cancelled) {
					return;
				}
				setTemplates((data && data.templates) || []);
				setError('');
			})
			.catch((err) => {
				if (!cancelled) {
					setError(
						errorMessage(err, __('Could not load templates.', 'jsontoimg'))
					);
				}
			})
			.finally(() => {
				if (!cancelled) {
					setLoadingTemplates(false);
				}
			});

		return () => {
			cancelled = true;
		};
	}, []);

	useEffect(() => {
		if (!template) {
			setSchema(null);
			return undefined;
		}

		let cancelled = false;
		setLoadingSchema(true);

		apiFetch({
			path: `/jsontoimg/v1/templates/${encodeURIComponent(template)}/schema`,
		})
			.then((data) => {
				if (!cancelled) {
					setSchema(data);
				}
			})
			.catch((err) => {
				if (!cancelled) {
					setSchema(null);
					setError(
						errorMessage(
							err,
							__('Could not load template schema.', 'jsontoimg')
						)
					);
				}
			})
			.finally(() => {
				if (!cancelled) {
					setLoadingSchema(false);
				}
			});

		return () => {
			cancelled = true;
		};
	}, [template]);

	useEffect(() => {
		if (!template) {
			setPreviewUrl('');
			return undefined;
		}

		let cancelled = false;
		const compacted = compactLayers(debouncedLayers);
		const localError = imageUrlError(compacted);
		if (localError) {
			setPreviewUrl('');
			setLoadingPreview(false);
			setError(localError);
			return undefined;
		}

		setLoadingPreview(true);

		apiFetch({
			path: '/jsontoimg/v1/sign',
			method: 'POST',
			data: {
				template,
				format,
				layers: compacted,
			},
		})
			.then((data) => {
				if (!cancelled) {
					setPreviewUrl((data && data.url) || '');
					setError('');
				}
			})
			.catch((err) => {
				if (!cancelled) {
					setPreviewUrl('');
					setError(
						errorMessage(err, __('Could not sign image URL.', 'jsontoimg'))
					);
				}
			})
			.finally(() => {
				if (!cancelled) {
					setLoadingPreview(false);
				}
			});

		return () => {
			cancelled = true;
		};
	}, [template, format, debouncedLayers]);

	const editableLayers = useMemo(() => {
		const list = (schema && schema.layers) || [];
		return list.filter((layer) => layerFields(layer).length > 0);
	}, [schema]);

	const templateOptions = [
		{ label: __('Select a template…', 'jsontoimg'), value: '' },
		...templates.map((item) => ({
			label: item.name || item.id,
			value: item.id,
		})),
	];

	const updateLayerValue = (layerName, key, value) => {
		const current = layers || {};
		setAttributes({
			layers: {
				...current,
				[layerName]: {
					...(current[layerName] || {}),
					[key]: value,
				},
			},
		});
	};

	const setAsFeaturedImage = () => {
		if (!template || settingFeatured) {
			return;
		}

		const compacted = compactLayers(layers);
		const localError = imageUrlError(compacted);
		if (localError) {
			setError(localError);
			return;
		}

		if (!postId) {
			setError(
				__('Save the post before setting a featured image.', 'jsontoimg')
			);
			return;
		}

		setSettingFeatured(true);
		setFeaturedNotice('');
		setError('');

		apiFetch({
			path: '/jsontoimg/v1/featured-image',
			method: 'POST',
			data: {
				postId,
				template,
				format,
				layers: compacted,
				alt,
			},
		})
			.then((data) => {
				if (data && data.attachmentId && editPost) {
					editPost({ featured_media: data.attachmentId });
				}
				setFeaturedNotice(
					__('Featured image updated.', 'jsontoimg')
				);
			})
			.catch((err) => {
				setError(
					errorMessage(
						err,
						__('Could not set the featured image.', 'jsontoimg')
					)
				);
			})
			.finally(() => {
				setSettingFeatured(false);
			});
	};

	const featuredDisabled =
		!template ||
		loadingPreview ||
		settingFeatured ||
		Boolean(imageUrlError(layers));

	const featuredButton = (
		<Button
			variant="primary"
			onClick={setAsFeaturedImage}
			disabled={featuredDisabled || !postId || !supportsThumbnail}
		>
			{settingFeatured
				? __('Setting featured image…', 'jsontoimg')
				: __('Set as Featured Image', 'jsontoimg')}
		</Button>
	);

	const blockProps = useBlockProps({ className: 'jsontoimg-block' });

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('jsontoimg', 'jsontoimg')} initialOpen={true}>
					{loadingTemplates ? (
						<Spinner />
					) : (
						<SelectControl
							label={__('Template', 'jsontoimg')}
							value={template}
							options={templateOptions}
							onChange={(value) => setAttributes({ template: value })}
						/>
					)}
					<SelectControl
						label={__('Format', 'jsontoimg')}
						value={format}
						options={FORMATS}
						onChange={(value) => setAttributes({ format: value })}
					/>
					{loadingSchema && <Spinner />}
					{editableLayers.map((layer) =>
						layerFields(layer).map((attr) => {
							const layerName = layer.layerName;
							const current =
								(layers && layers[layerName] && layers[layerName][attr.key]) ||
								'';
							const label = attr.label
								? `${layerName} (${attr.label})`
								: `${layerName}${attr.key === 'text' ? '' : ` (${attr.key})`}`;

							const help =
								attr.key === 'image_url'
									? __(
											'Public https URL ending in .png, .jpg, .jpeg, or .webp. Do not use placehold.co.',
											'jsontoimg'
									  )
									: undefined;

							return (
								<TextControl
									key={`${layer.id || layerName}-${attr.key}`}
									label={label}
									value={current}
									type={attr.key === 'image_url' ? 'url' : 'text'}
									help={help}
									onChange={(value) =>
										updateLayerValue(layerName, attr.key, value)
									}
								/>
							);
						})
					)}
					<TextControl
						label={__('Alt text', 'jsontoimg')}
						value={alt}
						onChange={(value) => setAttributes({ alt: value })}
					/>
					<TextControl
						label={__('Width', 'jsontoimg')}
						value={width}
						onChange={(value) => setAttributes({ width: value })}
					/>
					<TextControl
						label={__('Height', 'jsontoimg')}
						value={height}
						onChange={(value) => setAttributes({ height: value })}
					/>
					<div className="jsontoimg-featured-action">
						{featuredButton}
						{!postId ? (
							<p className="components-base-control__help">
								{__(
									'Save the post first, then set it as the featured image.',
									'jsontoimg'
								)}
							</p>
						) : null}
						{postId && !supportsThumbnail ? (
							<p className="components-base-control__help">
								{__(
									'This post type does not support a featured image.',
									'jsontoimg'
								)}
							</p>
						) : null}
					</div>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				{error ? (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				) : null}
				{featuredNotice ? (
					<Notice status="success" isDismissible={false}>
						{featuredNotice}
					</Notice>
				) : null}
				{!template ? (
					<Placeholder
						icon="format-image"
						label={__('jsontoimg', 'jsontoimg')}
						instructions={__(
							'Select a template in the block settings.',
							'jsontoimg'
						)}
					/>
				) : null}
				{template && loadingPreview ? <Spinner /> : null}
				{template && previewUrl ? (
					<>
						<img
							src={previewUrl}
							alt={alt || ''}
							width={width || undefined}
							height={height || undefined}
						/>
						<div className="jsontoimg-featured-action">
							{featuredButton}
						</div>
					</>
				) : null}
			</div>
		</>
	);
}
