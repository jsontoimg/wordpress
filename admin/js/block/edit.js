import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
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
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				{error ? (
					<Notice status="error" isDismissible={false}>
						{error}
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
					<img
						src={previewUrl}
						alt={alt || ''}
						width={width || undefined}
						height={height || undefined}
					/>
				) : null}
			</div>
		</>
	);
}
