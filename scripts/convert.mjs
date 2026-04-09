#!/usr/bin/env node

/**
 * Convert Gutenberg block HTML or plain HTML to Portable Text JSON.
 *
 * Usage:
 *   node convert.mjs --input <file>     Read HTML from file
 *   echo "<p>Hello</p>" | node convert.mjs   Read from stdin
 *
 * Outputs PT JSON array to stdout.
 */

import { readFileSync } from 'node:fs';
import { parseArgs } from 'node:util';

// Dynamic imports — these packages must be installed via npm.
let gutenbergToPortableText;
let htmlToPortableText;

try {
	const gutenbergMod = await import('@emdash-cms/gutenberg-to-portable-text');
	gutenbergToPortableText = gutenbergMod.default ?? gutenbergMod.gutenbergToPortableText ?? gutenbergMod;
} catch {
	// Package not available — will fall back to HTML conversion.
}

try {
	const htmlMod = await import('@portabletext/html');
	htmlToPortableText = htmlMod.default ?? htmlMod.htmlToBlocks ?? htmlMod.toPortableText ?? htmlMod;
} catch {
	// Package not available.
}

const { values } = parseArgs({
	options: {
		input: { type: 'string', short: 'i' },
	},
});

// Read HTML content.
let html = '';

if (values.input) {
	html = readFileSync(values.input, 'utf-8');
} else {
	// Read from stdin.
	const chunks = [];
	for await (const chunk of process.stdin) {
		chunks.push(chunk);
	}
	html = Buffer.concat(chunks).toString('utf-8');
}

if (!html.trim()) {
	console.log(JSON.stringify([]));
	process.exit(0);
}

// Detect Gutenberg content (HTML comments like <!-- wp:paragraph -->).
const isGutenberg = /<!--\s*wp:/.test(html);

let result = null;

if (isGutenberg && gutenbergToPortableText) {
	try {
		result = gutenbergToPortableText(html);
	} catch (err) {
		process.stderr.write(`Gutenberg conversion failed: ${err.message}\n`);
	}
}

// Fallback to generic HTML → PT conversion.
if (!result && htmlToPortableText) {
	try {
		result = htmlToPortableText(html);
	} catch (err) {
		process.stderr.write(`HTML conversion failed: ${err.message}\n`);
	}
}

// Last resort: wrap as a single text block.
if (!result) {
	result = [
		{
			_type: 'block',
			_key: crypto.randomUUID().slice(0, 12),
			style: 'normal',
			children: [
				{
					_type: 'span',
					_key: crypto.randomUUID().slice(0, 12),
					text: html.replace(/<[^>]+>/g, ''),
					marks: [],
				},
			],
			markDefs: [],
		},
	];
}

console.log(JSON.stringify(result, null, 2));
