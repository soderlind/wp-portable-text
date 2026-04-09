import { useState } from 'react';
import type { PortableTextBlock } from '@portabletext/editor';
import { ptToHtml, ptToMarkdown } from '../serializers';

type PreviewTab = 'json' | 'html' | 'markdown';

/**
 * Preview panel that shows PT JSON, rendered HTML, or rendered Markdown.
 */
export function PreviewPanel(props: { value: PortableTextBlock[] | undefined }) {
	const [activeTab, setActiveTab] = useState<PreviewTab | null>(null);

	const toggle = (tab: PreviewTab) => {
		setActiveTab((prev) => (prev === tab ? null : tab));
	};

	const blocks = props.value ?? [];

	let content = '';
	if (activeTab === 'json') {
		content = JSON.stringify(blocks, null, 2);
	} else if (activeTab === 'html') {
		content = ptToHtml(blocks);
	} else if (activeTab === 'markdown') {
		content = ptToMarkdown(blocks);
	}

	return (
		<div className="wp-pt-preview">
			<div className="wp-pt-preview-tabs">
				<button
					type="button"
					className={`wp-pt-preview-tab ${activeTab === 'json' ? 'is-active' : ''}`}
					onClick={() => toggle('json')}
					title="Show Portable Text JSON"
				>
					{'{ } JSON'}
				</button>
				<button
					type="button"
					className={`wp-pt-preview-tab ${activeTab === 'html' ? 'is-active' : ''}`}
					onClick={() => toggle('html')}
					title="Show rendered HTML"
				>
					{'</> HTML'}
				</button>
				<button
					type="button"
					className={`wp-pt-preview-tab ${activeTab === 'markdown' ? 'is-active' : ''}`}
					onClick={() => toggle('markdown')}
					title="Show rendered Markdown"
				>
					{'# MD'}
				</button>
			</div>
			{activeTab && (
				<pre className="wp-pt-preview-content">{content}</pre>
			)}
		</div>
	);
}
