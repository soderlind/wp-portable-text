import { defineSchema } from '@portabletext/editor';

/**
 * Portable Text schema for the WordPress editor.
 *
 * Defines all supported block types, decorators, styles,
 * annotations, lists, and custom block/inline objects.
 */
export const schemaDefinition = defineSchema({
	// Decorators: simple marks that don't carry data.
	decorators: [
		{ name: 'strong' },
		{ name: 'em' },
		{ name: 'underline' },
		{ name: 'strike-through' },
		{ name: 'code' },
		{ name: 'subscript' },
		{ name: 'superscript' },
	],

	// Styles: block-level formatting.
	styles: [
		{ name: 'normal' },
		{ name: 'h1' },
		{ name: 'h2' },
		{ name: 'h3' },
		{ name: 'h4' },
		{ name: 'h5' },
		{ name: 'h6' },
		{ name: 'blockquote' },
	],

	// Annotations: data-carrying marks on text spans.
	annotations: [
		{
			name: 'link',
			fields: [
				{ name: 'href', type: 'string' },
			],
		},
	],

	// Lists: block-level list types.
	lists: [
		{ name: 'bullet' },
		{ name: 'number' },
	],

	// Block objects: custom blocks that sit alongside text blocks.
	blockObjects: [
		{
			name: 'break',
			fields: [],
		},
		{
			name: 'image',
			fields: [
				{ name: 'src', type: 'string' },
				{ name: 'alt', type: 'string' },
				{ name: 'caption', type: 'string' },
				{ name: 'attachmentId', type: 'number' },
			],
		},
		{
			name: 'codeBlock',
			fields: [
				{ name: 'code', type: 'string' },
				{ name: 'language', type: 'string' },
			],
		},
		{
			name: 'embed',
			fields: [
				{ name: 'url', type: 'string' },
			],
		},
	],

	// Inline objects: structured data within text flow.
	inlineObjects: [],
});
