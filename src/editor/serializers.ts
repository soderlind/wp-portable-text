import type { PortableTextBlock } from '@portabletext/editor';

type MarkDef = {
	_key: string;
	_type: string;
	href?: string;
	[key: string]: unknown;
};

type Span = {
	_type: 'span';
	text: string;
	marks?: string[];
};

type Block = PortableTextBlock & {
	_type: string;
	style?: string;
	listItem?: string;
	level?: number;
	children?: Span[];
	markDefs?: MarkDef[];
	[key: string]: unknown;
};

/**
 * Convert PT blocks to HTML string (client-side, matches PHP renderer logic).
 */
export function ptToHtml(blocks: PortableTextBlock[]): string {
	const items = blocks as Block[];
	const parts: string[] = [];
	let i = 0;

	while (i < items.length) {
		const block = items[i];

		if (block.listItem) {
			// Gather consecutive list items at same level/type.
			const listType = block.listItem === 'number' ? 'ol' : 'ul';
			const listItems: string[] = [];
			while (i < items.length && items[i].listItem === block.listItem) {
				listItems.push(`<li>${renderChildren(items[i])}</li>`);
				i++;
			}
			parts.push(`<${listType}>\n${listItems.join('\n')}\n</${listType}>`);
			continue;
		}

		parts.push(renderBlock(block));
		i++;
	}

	return parts.join('\n');
}

/**
 * Convert PT blocks to Markdown string.
 */
export function ptToMarkdown(blocks: PortableTextBlock[]): string {
	const items = blocks as Block[];
	const parts: string[] = [];
	let i = 0;

	while (i < items.length) {
		const block = items[i];

		if (block.listItem) {
			const ordered = block.listItem === 'number';
			let idx = 1;
			while (i < items.length && items[i].listItem === block.listItem) {
				const prefix = ordered ? `${idx}. ` : '- ';
				parts.push(prefix + renderChildrenMd(items[i]));
				idx++;
				i++;
			}
			parts.push('');
			continue;
		}

		parts.push(renderBlockMd(block));
		i++;
	}

	return parts.join('\n');
}

// --- HTML helpers ---

function renderBlock(block: Block): string {
	switch (block._type) {
		case 'block':
			return renderTextBlock(block);
		case 'break':
			return '<hr />';
		case 'image': {
			const alt = esc(String(block.alt ?? ''));
			const src = esc(String(block.src ?? ''));
			const caption = block.caption ? `<figcaption>${esc(String(block.caption))}</figcaption>` : '';
			return `<figure><img src="${src}" alt="${alt}" />${caption}</figure>`;
		}
		case 'codeBlock':
			return renderCodeBlockHtml(block);
		case 'embed':
			return `<p><a href="${esc(String(block.url ?? ''))}">${esc(String(block.url ?? ''))}</a></p>`;
		default:
			return '';
	}
}

function renderTextBlock(block: Block): string {
	const content = renderChildren(block);
	if (!content.trim()) return '';

	const style = block.style ?? 'normal';
	switch (style) {
		case 'h1': case 'h2': case 'h3':
		case 'h4': case 'h5': case 'h6':
			return `<${style}>${content}</${style}>`;
		case 'blockquote':
			return `<blockquote><p>${content}</p></blockquote>`;
		default:
			return `<p>${content}</p>`;
	}
}

function renderChildren(block: Block): string {
	const children = block.children ?? [];
	const markDefs = block.markDefs ?? [];
	const defMap = new Map(markDefs.map((d) => [d._key, d]));

	return children
		.map((child) => {
			if (child._type !== 'span') return '';
			let text = esc(child.text);
			for (const mark of child.marks ?? []) {
				const def = defMap.get(mark);
				if (def) {
					text = applyAnnotationHtml(text, def);
				} else {
					text = applyDecoratorHtml(text, mark);
				}
			}
			return text;
		})
		.join('');
}

function applyDecoratorHtml(text: string, dec: string): string {
	switch (dec) {
		case 'strong': return `<strong>${text}</strong>`;
		case 'em': return `<em>${text}</em>`;
		case 'underline': return `<u>${text}</u>`;
		case 'strike-through': return `<s>${text}</s>`;
		case 'code': return `<code class="wp-portable-text-inline-code">${text}</code>`;
		case 'subscript': return `<sub>${text}</sub>`;
		case 'superscript': return `<sup>${text}</sup>`;
		default: return text;
	}
}

function renderCodeBlockHtml(block: Block): string {
	const language = String(block.language ?? '');
	const classes = ['wp-portable-text-code'];
	const languageBadge = language
		? `<span class="wp-portable-text-code-language">${esc(language)}</span>`
		: '';
	const code = normalizeCodeContent(String(block.code ?? ''));

	if (language) {
		classes.push(`language-${escAttr(language)}`);
	}

	return `<pre class="wp-portable-text-code-block">${languageBadge}<code class="${classes.join(' ')}">${esc(code)}</code></pre>`;
}

function applyAnnotationHtml(text: string, def: MarkDef): string {
	if (def._type === 'link' && def.href) {
		return `<a href="${esc(def.href)}">${text}</a>`;
	}
	return text;
}

// --- Markdown helpers ---

function renderBlockMd(block: Block): string {
	switch (block._type) {
		case 'block':
			return renderTextBlockMd(block);
		case 'break':
			return '---\n';
		case 'image': {
			const alt = block.alt ?? '';
			const src = block.src ?? '';
			const md = `![${alt}](${src})`;
			return block.caption ? `${md}\n\n*${block.caption}*\n` : `${md}\n`;
		}
		case 'codeBlock': {
			const lang = block.language ?? '';
			return `\`\`\`${lang}\n${block.code ?? ''}\n\`\`\`\n`;
		}
		case 'embed':
			return `${block.url ?? ''}\n`;
		default:
			return '';
	}
}

function renderTextBlockMd(block: Block): string {
	const content = renderChildrenMd(block);
	if (!content.trim()) return '';

	const style = block.style ?? 'normal';
	switch (style) {
		case 'h1': return `# ${content}\n`;
		case 'h2': return `## ${content}\n`;
		case 'h3': return `### ${content}\n`;
		case 'h4': return `#### ${content}\n`;
		case 'h5': return `##### ${content}\n`;
		case 'h6': return `###### ${content}\n`;
		case 'blockquote': return `> ${content}\n`;
		default: return `${content}\n`;
	}
}

function renderChildrenMd(block: Block): string {
	const children = block.children ?? [];
	const markDefs = block.markDefs ?? [];
	const defMap = new Map(markDefs.map((d) => [d._key, d]));

	return children
		.map((child) => {
			if (child._type !== 'span') return '';
			let text = child.text;
			for (const mark of child.marks ?? []) {
				const def = defMap.get(mark);
				if (def) {
					text = applyAnnotationMd(text, def);
				} else {
					text = applyDecoratorMd(text, mark);
				}
			}
			return text;
		})
		.join('');
}

function applyDecoratorMd(text: string, dec: string): string {
	switch (dec) {
		case 'strong': return `**${text}**`;
		case 'em': return `*${text}*`;
		case 'underline': return `<u>${text}</u>`;
		case 'strike-through': return `~~${text}~~`;
		case 'code': return `\`${text}\``;
		case 'subscript': return `<sub>${text}</sub>`;
		case 'superscript': return `<sup>${text}</sup>`;
		default: return text;
	}
}

function applyAnnotationMd(text: string, def: MarkDef): string {
	if (def._type === 'link' && def.href) {
		return `[${text}](${def.href})`;
	}
	return text;
}

function esc(str: string): string {
	return str
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;');
}

function escAttr(str: string): string {
	return esc(str).replace(/'/g, '&#39;');
}

function normalizeCodeContent(str: string): string {
	return str
		.replace(/\r\n/g, '\n')
		.replace(/\r/g, '\n')
		.replace(/\\r\\n|\\n|\\r/g, '\n');
}
